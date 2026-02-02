from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from decimal import Decimal, ROUND_HALF_UP
from typing import List

from fastapi import HTTPException, status

from app.db import client
from app.models.pos import Sale, SaleItem, Refund, RefundItem, RefundStatus, SaleStatus, PaymentMethod
from app.schemas.pos import RefundRequest
from app.services.pos_inventory import apply_refund_items
from app.services.pos_registers import adjust_expected_cash
from app.services.audit import log_audit
from app.services.pos_loyalty import adjust_loyalty_points


@dataclass
class RestockItem:
    product_id: str | None
    variant_id: str | None
    quantity: int


def _round_cents(value: Decimal) -> int:
    return int(value.quantize(Decimal("1"), rounding=ROUND_HALF_UP))


async def create_refund(tenant_id: str, user_id: str, payload: RefundRequest) -> Refund:
    sale = await Sale.get(payload.sale_id)
    if not sale or sale.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Sale not found")
    if sale.status not in (SaleStatus.COMPLETED, SaleStatus.REFUNDED):
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Sale is not refundable")

    sale_items = await SaleItem.find(SaleItem.sale_id == payload.sale_id, SaleItem.tenant_id == tenant_id).to_list()
    sale_items_by_id = {str(item.id): item for item in sale_items}
    if not sale_items_by_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Sale has no items")

    refund_items: List[RefundItem] = []
    restock_items: List[RestockItem] = []
    total_refund_cents = 0
    refunded_existing_total = 0
    requested_total_qty = 0
    sale_items_total_qty = sum(item.quantity for item in sale_items)

    for item in payload.items:
        sale_item = sale_items_by_id.get(item.sale_item_id)
        if not sale_item:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Sale item not found")

        existing_refunds = await RefundItem.find(
            RefundItem.sale_item_id == item.sale_item_id,
            RefundItem.tenant_id == tenant_id,
        ).to_list()
        refunded_qty = sum(refund.quantity for refund in existing_refunds)
        refunded_existing_total += refunded_qty
        if refunded_qty + item.quantity > sale_item.quantity:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Refund quantity exceeds purchased quantity")

        unit_total = Decimal(sale_item.line_total_cents) / Decimal(sale_item.quantity)
        amount_cents = _round_cents(unit_total * Decimal(item.quantity))

        refund_items.append(
            RefundItem(
                tenant_id=tenant_id,
                refund_id="",
                sale_item_id=item.sale_item_id,
                quantity=item.quantity,
                amount_cents=amount_cents,
                restock=item.restock,
            )
        )

        total_refund_cents += amount_cents
        requested_total_qty += item.quantity

        if item.restock and not sale_item.is_service and not sale_item.is_subscription:
            restock_items.append(
                RestockItem(
                    product_id=sale_item.product_id,
                    variant_id=sale_item.variant_id,
                    quantity=item.quantity,
                )
            )

    if total_refund_cents <= 0:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Refund total must be greater than zero")

    if restock_items and not sale.location_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Sale missing location for restock")

    async with client.start_session() as session:
        async with session.start_transaction():
            refund = Refund(
                tenant_id=tenant_id,
                sale_id=payload.sale_id,
                created_by_user_id=user_id,
                reason=payload.reason or "",
                status=RefundStatus.COMPLETED,
                total_cents=total_refund_cents,
                payment_method=payload.payment_method,
                created_at=datetime.utcnow(),
            )
            await refund.insert(session=session)

            for refund_item in refund_items:
                refund_item.refund_id = str(refund.id)
                await refund_item.insert(session=session)

            if restock_items:
                await apply_refund_items(
                    tenant_id=tenant_id,
                    location_id=sale.location_id or "",
                    items=restock_items,
                    user_id=user_id,
                    refund_id=str(refund.id),
                    restock=True,
                    session=session,
                )

            if sale.status != SaleStatus.REFUNDED:
                refunded_total_after = refunded_existing_total + requested_total_qty
                if refunded_total_after >= sale_items_total_qty:
                    sale.status = SaleStatus.REFUNDED
                    await sale.save(session=session)

    if payload.payment_method == PaymentMethod.CASH and sale.register_session_id:
        await adjust_expected_cash(sale.register_session_id, -total_refund_cents)

    if sale.customer_id and (sale.loyalty_points_earned or sale.loyalty_points_redeemed):
        refund_ratio = min(1, total_refund_cents / max(sale.total_cents or 1, 1))
        if sale.loyalty_points_earned:
            revoke_points = int(round(sale.loyalty_points_earned * refund_ratio))
            if revoke_points:
                await adjust_loyalty_points(
                    tenant_id,
                    sale.customer_id,
                    -revoke_points,
                    user_id,
                )
        if sale.loyalty_points_redeemed:
            restore_points = int(round(sale.loyalty_points_redeemed * refund_ratio))
            if restore_points:
                await adjust_loyalty_points(
                    tenant_id,
                    sale.customer_id,
                    restore_points,
                    user_id,
                )

    await log_audit(
        tenant_id=tenant_id,
        actor_user_id=user_id,
        action="pos.sale.refund",
        target=str(refund.id),
        details={"sale_id": payload.sale_id, "total_cents": total_refund_cents},
    )

    return refund
