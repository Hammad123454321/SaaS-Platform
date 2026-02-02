from __future__ import annotations

from datetime import datetime
from typing import List

from fastapi import HTTPException, status

from app.models.pos import Vendor, PurchaseOrder, PurchaseOrderStatus, InventoryReason, PurchaseOrderItem
from app.services.pos_inventory import apply_stock_delta, record_ledger


async def create_vendor(tenant_id: str, payload) -> Vendor:
    existing = await Vendor.find_one(Vendor.tenant_id == tenant_id, Vendor.name == payload.name)
    if existing:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Vendor already exists")
    vendor = Vendor(
        tenant_id=tenant_id,
        name=payload.name,
        contact_name=payload.contact_name,
        email=payload.email,
        phone=payload.phone,
        address=payload.address,
        notes=payload.notes,
        is_active=payload.is_active,
    )
    await vendor.insert()
    return vendor


async def list_vendors(tenant_id: str) -> List[Vendor]:
    return await Vendor.find(Vendor.tenant_id == tenant_id, Vendor.is_active == True).sort(Vendor.name).to_list()


async def create_purchase_order(tenant_id: str, user_id: str, payload) -> PurchaseOrder:
    vendor = await Vendor.get(payload.vendor_id)
    if not vendor or vendor.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Vendor not found")

    items: List[PurchaseOrderItem] = []
    total_cost = 0
    for item in payload.items:
        line_total = int(item.unit_cost_cents) * int(item.quantity)
        total_cost += line_total
        items.append(
            PurchaseOrderItem(
                product_id=item.product_id,
                variant_id=item.variant_id,
                quantity=int(item.quantity),
                unit_cost_cents=int(item.unit_cost_cents),
                received_quantity=0,
            )
        )

    order = PurchaseOrder(
        tenant_id=tenant_id,
        vendor_id=payload.vendor_id,
        location_id=payload.location_id,
        status=payload.status or PurchaseOrderStatus.DRAFT,
        created_by_user_id=user_id,
        items=items,
        total_cost_cents=total_cost,
        ordered_at=datetime.utcnow() if payload.status in [PurchaseOrderStatus.ORDERED, PurchaseOrderStatus.PARTIALLY_RECEIVED] else None,
        expected_at=payload.expected_at,
        created_at=datetime.utcnow(),
        updated_at=datetime.utcnow(),
    )
    await order.insert()
    return order


async def list_purchase_orders(tenant_id: str) -> List[PurchaseOrder]:
    return await PurchaseOrder.find(PurchaseOrder.tenant_id == tenant_id).sort(-PurchaseOrder.created_at).to_list()


async def receive_purchase_order(
    tenant_id: str,
    order_id: str,
    user_id: str,
    items,
    session=None,
) -> PurchaseOrder:
    order = await PurchaseOrder.get(order_id)
    if not order or order.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Purchase order not found")
    if order.status == PurchaseOrderStatus.CANCELLED:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Purchase order cancelled")

    order_item_map = {(i.product_id, i.variant_id): i for i in order.items}
    updated_items: List[PurchaseOrderItem] = []
    for item in items:
        key = (item.product_id, item.variant_id)
        order_item = order_item_map.get(key)
        if not order_item:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Item not in purchase order")
        received_qty = int(order_item.received_quantity) + int(item.received_quantity)
        if received_qty > int(order_item.quantity):
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Received quantity exceeds ordered")
        updated_items.append(
            PurchaseOrderItem(
                product_id=order_item.product_id,
                variant_id=order_item.variant_id,
                quantity=int(order_item.quantity),
                unit_cost_cents=int(order_item.unit_cost_cents),
                received_quantity=received_qty,
            )
        )
        if item.received_quantity:
            await apply_stock_delta(
                tenant_id,
                order.location_id,
                item.product_id,
                item.variant_id,
                int(item.received_quantity),
                enforce_non_negative=False,
                session=session,
            )
            await record_ledger(
                tenant_id,
                order.location_id,
                item.product_id,
                item.variant_id,
                int(item.received_quantity),
                InventoryReason.PURCHASE,
                created_by_user_id=user_id,
                notes=f"PO {order.id}",
                session=session,
            )

    remaining_items: List[PurchaseOrderItem] = []
    updated_keys = {(i.product_id, i.variant_id) for i in updated_items}
    for item in order.items:
        if (item.product_id, item.variant_id) in updated_keys:
            continue
        remaining_items.append(
            PurchaseOrderItem(
                product_id=item.product_id,
                variant_id=item.variant_id,
                quantity=int(item.quantity),
                unit_cost_cents=int(item.unit_cost_cents),
                received_quantity=int(item.received_quantity),
            )
        )
    order.items = updated_items + remaining_items
    order.updated_at = datetime.utcnow()
    if all(it.received_quantity >= it.quantity for it in order.items):
        order.status = PurchaseOrderStatus.RECEIVED
        order.received_at = datetime.utcnow()
    else:
        order.status = PurchaseOrderStatus.PARTIALLY_RECEIVED
    await order.save(session=session)
    return order
