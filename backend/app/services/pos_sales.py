from __future__ import annotations

from datetime import datetime, date
from typing import Optional, List

from fastapi import HTTPException, status

from app.db import client
from app.models.pos import (
    Sale,
    SaleItem,
    Payment,
    Receipt,
    SaleStatus,
    PaymentMethod,
    KitchenStatus,
    FulfillmentInfo,
    SalesChannel,
    Location,
    IdVerification,
)
from app.schemas.pos import SaleDraftRequest, SaleDraftUpdateRequest, FinalizeSaleRequest, SaleDiscountInput
from app.services.pos_pricing import calculate_pricing, PricedItem
from app.services.pos_inventory import apply_sale_items
from app.services.pos_registers import get_open_session_for_register, adjust_expected_cash
from app.services.audit import log_audit
from app.services.pos_marketing import resolve_coupon_discount, increment_coupon_usage
from app.services.pos_loyalty import (
    get_active_loyalty_program,
    get_or_create_loyalty_account,
    redeem_loyalty_points,
    earn_loyalty_points,
)


def _generate_receipt_number(tenant_id: str) -> str:
    stamp = datetime.utcnow().strftime("%Y%m%d%H%M%S")
    suffix = tenant_id[-4:] if tenant_id else "0000"
    return f"POS-{stamp}-{suffix}"


async def _create_sale_items(tenant_id: str, sale_id: str, items: List[PricedItem]) -> None:
    for item in items:
        sale_item = SaleItem(
            tenant_id=tenant_id,
            sale_id=sale_id,
            product_id=item.product_id,
            variant_id=item.variant_id,
            category_id=item.category_id,
            product_name=item.product_name,
            variant_name=item.variant_name,
            sku=item.sku,
            quantity=item.quantity,
            unit_price_cents=item.unit_price_cents,
            discount_cents=item.discount_cents,
            tax_cents=item.tax_cents,
            line_total_cents=item.line_total_cents,
            tax_ids=item.tax_ids,
            is_kitchen_item=item.is_kitchen_item,
            kitchen_status=KitchenStatus.QUEUED if item.is_kitchen_item else None,
            is_service=item.is_service,
            is_subscription=item.is_subscription,
            requires_id_check=item.requires_id_check,
            minimum_age=item.minimum_age,
        )
        await sale_item.insert()


async def create_sale_draft(tenant_id: str, user_id: str, payload: SaleDraftRequest) -> Sale:
    if not payload.items:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Sale requires at least one item")

    fallback_location_id = None
    if not payload.location_id and not payload.register_id:
        if payload.channel and payload.channel != SalesChannel.POS:
            location = await Location.find_one(Location.tenant_id == tenant_id, Location.is_active == True)
            if not location:
                raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="No active location available")
            fallback_location_id = str(location.id)
        else:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Sale must include location_id or register_id")

    order_discount = payload.order_discount
    if order_discount is None and (sale.order_discount_type or sale.order_discount_bps or sale.order_discount_cents):
        order_discount = SaleDiscountInput(
            discount_type=sale.order_discount_type,
            discount_bps=sale.order_discount_bps,
            discount_cents=sale.order_discount_cents,
        )

    base_pricing = await calculate_pricing(tenant_id, payload.items, order_discount)
    coupon = None
    coupon_discount_cents = 0
    coupon_code = payload.coupon_code if payload.coupon_code is not None else sale.applied_coupon_code
    if coupon_code:
        line_discount_cents = sum(item.discount_cents for item in base_pricing["items"])
        base_for_coupon = max(
            base_pricing["subtotal_cents"] - line_discount_cents - base_pricing.get("order_discount_cents", 0),
            0,
        )
        coupon, coupon_discount_cents = await resolve_coupon_discount(
            tenant_id,
            coupon_code,
            base_for_coupon,
        )

    loyalty_discount_cents = 0
    loyalty_points_redeemed = (
        payload.loyalty_points_redeemed
        if payload.loyalty_points_redeemed is not None
        else sale.loyalty_points_redeemed
    )
    if loyalty_points_redeemed:
        if not payload.customer_id:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Customer required for loyalty redemption")
        program = await get_active_loyalty_program(tenant_id)
        if not program:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="No active loyalty program")
        account = await get_or_create_loyalty_account(tenant_id, payload.customer_id)
        if account.points_balance < loyalty_points_redeemed:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Insufficient loyalty points")
        loyalty_discount_cents = loyalty_points_redeemed * program.redeem_rate_cents_per_point

    pricing = await calculate_pricing(
        tenant_id,
        payload.items,
        order_discount,
        extra_discount_cents=coupon_discount_cents + loyalty_discount_cents,
    )
    location_id = payload.location_id or fallback_location_id
    register_session_id = None

    if payload.register_id:
        session = await get_open_session_for_register(tenant_id, payload.register_id)
        if not session:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Register session must be open")
        register_session_id = str(session.id)
        if not location_id:
            location_id = session.location_id

    shipping_cents = 0
    fulfillment = None
    if payload.fulfillment:
        fulfillment = FulfillmentInfo(**payload.fulfillment.model_dump())
        shipping_cents = fulfillment.shipping_cost_cents or 0

    sale = Sale(
        tenant_id=tenant_id,
        location_id=location_id,
        register_id=payload.register_id,
        register_session_id=register_session_id,
        cashier_id=user_id,
        customer_id=payload.customer_id,
        channel=payload.channel or SalesChannel.POS,
        fulfillment=fulfillment,
        status=SaleStatus.DRAFT,
        subtotal_cents=pricing["subtotal_cents"],
        discount_cents=pricing["discount_cents"],
        tax_cents=pricing["tax_cents"],
        shipping_cents=shipping_cents,
        total_cents=pricing["total_cents"] + shipping_cents,
        order_discount_type=payload.order_discount.discount_type if payload.order_discount else None,
        order_discount_bps=payload.order_discount.discount_bps if payload.order_discount else None,
        order_discount_cents=payload.order_discount.discount_cents if payload.order_discount else None,
        items_count=pricing["items_count"],
        applied_coupon_code=coupon.code if coupon else None,
        campaign_id=coupon.campaign_id if coupon else None,
        loyalty_points_redeemed=loyalty_points_redeemed,
    )
    await sale.insert()

    await _create_sale_items(tenant_id, str(sale.id), pricing["items"])

    await log_audit(
        tenant_id=tenant_id,
        actor_user_id=user_id,
        action="pos.sale.draft.create",
        target=str(sale.id),
    )

    return sale


async def update_sale_draft(
    tenant_id: str,
    sale_id: str,
    user_id: str,
    payload: SaleDraftUpdateRequest,
) -> Sale:
    sale = await Sale.get(sale_id)
    if not sale or sale.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Sale not found")
    if sale.status != SaleStatus.DRAFT:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Only draft sales can be updated")

    base_pricing = await calculate_pricing(tenant_id, payload.items, payload.order_discount)
    coupon = None
    coupon_discount_cents = 0
    if payload.coupon_code:
        line_discount_cents = sum(item.discount_cents for item in base_pricing["items"])
        base_for_coupon = max(
            base_pricing["subtotal_cents"] - line_discount_cents - base_pricing.get("order_discount_cents", 0),
            0,
        )
        coupon, coupon_discount_cents = await resolve_coupon_discount(
            tenant_id,
            payload.coupon_code,
            base_for_coupon,
        )

    loyalty_discount_cents = 0
    loyalty_points_redeemed = payload.loyalty_points_redeemed or 0
    if loyalty_points_redeemed:
        customer_id = payload.customer_id or sale.customer_id
        if not customer_id:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Customer required for loyalty redemption")
        program = await get_active_loyalty_program(tenant_id)
        if not program:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="No active loyalty program")
        account = await get_or_create_loyalty_account(tenant_id, customer_id)
        if account.points_balance < loyalty_points_redeemed:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Insufficient loyalty points")
        loyalty_discount_cents = loyalty_points_redeemed * program.redeem_rate_cents_per_point

    pricing = await calculate_pricing(
        tenant_id,
        payload.items,
        payload.order_discount,
        extra_discount_cents=coupon_discount_cents + loyalty_discount_cents,
    )

    if payload.customer_id:
        sale.customer_id = payload.customer_id
    if payload.channel:
        sale.channel = payload.channel
    if payload.fulfillment:
        sale.fulfillment = FulfillmentInfo(**payload.fulfillment.model_dump())
        sale.shipping_cents = sale.fulfillment.shipping_cost_cents or 0
    sale.subtotal_cents = pricing["subtotal_cents"]
    sale.discount_cents = pricing["discount_cents"]
    sale.tax_cents = pricing["tax_cents"]
    sale.total_cents = pricing["total_cents"] + (sale.shipping_cents or 0)
    sale.items_count = pricing["items_count"]
    sale.applied_coupon_code = coupon.code if coupon else None
    sale.campaign_id = coupon.campaign_id if coupon else None
    sale.loyalty_points_redeemed = loyalty_points_redeemed
    sale.order_discount_type = order_discount.discount_type if order_discount else None
    sale.order_discount_bps = order_discount.discount_bps if order_discount else None
    sale.order_discount_cents = order_discount.discount_cents if order_discount else None
    sale.updated_at = datetime.utcnow()
    await sale.save()

    await SaleItem.find(SaleItem.sale_id == sale_id, SaleItem.tenant_id == tenant_id).delete()
    await _create_sale_items(tenant_id, sale_id, pricing["items"])

    await log_audit(
        tenant_id=tenant_id,
        actor_user_id=user_id,
        action="pos.sale.draft.update",
        target=str(sale.id),
    )

    return sale


async def list_sales(
    tenant_id: str,
    start_date: Optional[datetime] = None,
    end_date: Optional[datetime] = None,
    location_id: Optional[str] = None,
    register_id: Optional[str] = None,
    cashier_id: Optional[str] = None,
    status: Optional[str] = None,
    limit: int = 50,
    offset: int = 0,
) -> List[Sale]:
    conditions = [Sale.tenant_id == tenant_id]
    if start_date:
        conditions.append(Sale.created_at >= start_date)
    if end_date:
        conditions.append(Sale.created_at <= end_date)
    if location_id:
        conditions.append(Sale.location_id == location_id)
    if register_id:
        conditions.append(Sale.register_id == register_id)
    if cashier_id:
        conditions.append(Sale.cashier_id == cashier_id)
    if status:
        conditions.append(Sale.status == status)

    return await Sale.find(*conditions).sort(-Sale.created_at).skip(offset).limit(limit).to_list()


async def finalize_sale(
    tenant_id: str,
    sale_id: str,
    user_id: str,
    payload: FinalizeSaleRequest,
) -> Sale:
    sale = await Sale.get(sale_id)
    if not sale or sale.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Sale not found")
    if sale.status != SaleStatus.DRAFT:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Sale is not in draft state")

    if payload.customer_id:
        sale.customer_id = payload.customer_id
    if payload.channel:
        sale.channel = payload.channel
    if payload.fulfillment:
        old_shipping = sale.shipping_cents or 0
        sale.fulfillment = FulfillmentInfo(**payload.fulfillment.model_dump())
        sale.shipping_cents = sale.fulfillment.shipping_cost_cents or 0
        sale.total_cents = max(sale.total_cents - old_shipping + sale.shipping_cents, 0)

    if payload.coupon_code and payload.coupon_code != sale.applied_coupon_code:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Apply coupon on draft before finalizing",
        )
    if payload.loyalty_points_redeemed and payload.loyalty_points_redeemed != sale.loyalty_points_redeemed:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Apply loyalty redemption on draft before finalizing",
        )

    sale_items = await SaleItem.find(SaleItem.sale_id == sale_id, SaleItem.tenant_id == tenant_id).to_list()
    if not sale_items:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Sale has no items")

    if sale.register_id:
        session = await get_open_session_for_register(tenant_id, sale.register_id)
        if not session:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Register session must be open")
        sale.register_session_id = str(session.id)
        if not sale.location_id:
            sale.location_id = session.location_id

    if not sale.location_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Sale must have location_id")

    if sale.loyalty_points_redeemed and not sale.customer_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Customer required for loyalty redemption")

    required_min_age = 0
    for item in sale_items:
        if item.requires_id_check:
            required_min_age = max(required_min_age, item.minimum_age or 0)

    if required_min_age > 0:
        if not payload.id_verification or not payload.id_verification.birth_date:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="ID verification required")
        today = date.today()
        age = today.year - payload.id_verification.birth_date.year
        if (today.month, today.day) < (payload.id_verification.birth_date.month, payload.id_verification.birth_date.day):
            age -= 1
        if age < required_min_age:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Customer does not meet age requirement")
        sale.id_verification = IdVerification(
            id_type=payload.id_verification.id_type,
            id_last4=payload.id_verification.id_last4,
            birth_date=payload.id_verification.birth_date,
            verified_by_user_id=user_id,
            minimum_age=required_min_age,
        )

    paid_total = sum(p.amount_cents for p in payload.payments)
    if paid_total < sale.total_cents:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Payment amount is insufficient")

    change_due = paid_total - sale.total_cents
    if change_due > 0 and not any(p.method == PaymentMethod.CASH for p in payload.payments):
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Change due requires a cash payment")

    async with client.start_session() as session:
        async with session.start_transaction():
            await apply_sale_items(
                tenant_id=tenant_id,
                location_id=sale.location_id,
                items=sale_items,
                user_id=user_id,
                sale_id=sale_id,
                enforce_non_negative=True,
                session=session,
            )

            completed_at = datetime.utcnow()
            for payment in payload.payments:
                payment_doc = Payment(
                    tenant_id=tenant_id,
                    sale_id=sale_id,
                    method=payment.method,
                    amount_cents=payment.amount_cents,
                    reference=payment.reference,
                    sale_completed_at=completed_at,
                )
                await payment_doc.insert(session=session)

            sale.status = SaleStatus.COMPLETED
            sale.completed_at = completed_at
            sale.paid_cents = paid_total
            sale.change_due_cents = change_due
            sale.updated_at = datetime.utcnow()
            await sale.save(session=session)

            await SaleItem.find(
                SaleItem.sale_id == sale_id,
                SaleItem.tenant_id == tenant_id,
            ).update({"$set": {"sale_completed_at": sale.completed_at}}, session=session)

            receipt = Receipt(
                tenant_id=tenant_id,
                sale_id=sale_id,
                receipt_number=_generate_receipt_number(tenant_id),
                rendered={
                    "sale_id": sale_id,
                    "cashier_id": sale.cashier_id,
                    "location_id": sale.location_id,
                    "register_id": sale.register_id,
                    "channel": sale.channel,
                    "fulfillment": sale.fulfillment.model_dump() if sale.fulfillment else None,
                    "completed_at": sale.completed_at.isoformat() if sale.completed_at else None,
                    "items": [
                        {
                            "product_name": item.product_name,
                            "variant_name": item.variant_name,
                            "sku": item.sku,
                            "quantity": item.quantity,
                            "unit_price_cents": item.unit_price_cents,
                            "discount_cents": item.discount_cents,
                            "tax_cents": item.tax_cents,
                            "line_total_cents": item.line_total_cents,
                        }
                        for item in sale_items
                    ],
                    "payments": [
                        {
                            "method": payment.method,
                            "amount_cents": payment.amount_cents,
                            "reference": payment.reference,
                        }
                        for payment in payload.payments
                    ],
                    "totals": {
                        "subtotal_cents": sale.subtotal_cents,
                        "discount_cents": sale.discount_cents,
                        "tax_cents": sale.tax_cents,
                        "shipping_cents": sale.shipping_cents,
                        "total_cents": sale.total_cents,
                        "paid_cents": paid_total,
                        "change_due_cents": change_due,
                    },
                    "coupon_code": sale.applied_coupon_code,
                    "loyalty_points_redeemed": sale.loyalty_points_redeemed,
                    "loyalty_points_earned": sale.loyalty_points_earned,
                },
            )
            await receipt.insert(session=session)

            if sale.applied_coupon_code:
                await increment_coupon_usage(tenant_id, sale.applied_coupon_code, session=session)

            loyalty_points_earned = 0
            if sale.customer_id:
                if sale.loyalty_points_redeemed:
                    await redeem_loyalty_points(
                        tenant_id=tenant_id,
                        customer_id=sale.customer_id,
                        points=sale.loyalty_points_redeemed,
                        user_id=user_id,
                        sale_id=sale_id,
                        session=session,
                    )
                program = await get_active_loyalty_program(tenant_id)
                if program:
                    eligible_cents = max(sale.subtotal_cents - sale.discount_cents, 0)
                    loyalty_points_earned = await earn_loyalty_points(
                        tenant_id=tenant_id,
                        customer_id=sale.customer_id,
                        amount_cents=eligible_cents,
                        user_id=user_id,
                        sale_id=sale_id,
                        session=session,
                    )
                    if loyalty_points_earned:
                        sale.loyalty_points_earned = loyalty_points_earned
                        await sale.save(session=session)

    if sale.register_session_id:
        cash_net = 0
        for payment in payload.payments:
            if payment.method == PaymentMethod.CASH:
                cash_net += payment.amount_cents
        cash_net -= change_due
        if cash_net != 0:
            await adjust_expected_cash(sale.register_session_id, cash_net)

    await log_audit(
        tenant_id=tenant_id,
        actor_user_id=user_id,
        action="pos.sale.finalize",
        target=str(sale.id),
        details={"total_cents": sale.total_cents, "paid_cents": paid_total},
    )

    return sale
