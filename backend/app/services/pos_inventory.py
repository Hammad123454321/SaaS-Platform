from __future__ import annotations

from datetime import datetime
from typing import Iterable, Optional

from fastapi import HTTPException, status
from pymongo import ReturnDocument

from app.models.pos import (
    StockOnHand,
    InventoryLedger,
    InventoryReason,
    StockTransfer,
    StockTransferStatus,
    StockCount,
    StockCountStatus,
)


async def ensure_stock_record(
    tenant_id: str,
    location_id: str,
    product_id: Optional[str],
    variant_id: Optional[str],
    session=None,
) -> None:
    collection = StockOnHand.get_motor_collection()
    now = datetime.utcnow()
    await collection.update_one(
        {
            "tenant_id": tenant_id,
            "location_id": location_id,
            "product_id": product_id,
            "variant_id": variant_id,
        },
        {
            "$setOnInsert": {
                "qty_on_hand": 0,
                "reorder_point": 0,
                "updated_at": now,
            }
        },
        upsert=True,
        session=session,
    )


async def apply_stock_delta(
    tenant_id: str,
    location_id: str,
    product_id: Optional[str],
    variant_id: Optional[str],
    qty_delta: int,
    enforce_non_negative: bool,
    session=None,
) -> int:
    collection = StockOnHand.get_motor_collection()
    now = datetime.utcnow()

    await ensure_stock_record(tenant_id, location_id, product_id, variant_id, session=session)

    filter_doc = {
        "tenant_id": tenant_id,
        "location_id": location_id,
        "product_id": product_id,
        "variant_id": variant_id,
    }
    if enforce_non_negative and qty_delta < 0:
        filter_doc["qty_on_hand"] = {"$gte": abs(qty_delta)}

    result = await collection.find_one_and_update(
        filter_doc,
        {
            "$inc": {"qty_on_hand": qty_delta},
            "$set": {"updated_at": now},
        },
        return_document=ReturnDocument.AFTER,
        session=session,
    )

    if result is None:
        raise HTTPException(status_code=status.HTTP_409_CONFLICT, detail="Insufficient stock")

    return int(result.get("qty_on_hand", 0))


async def record_ledger(
    tenant_id: str,
    location_id: str,
    product_id: Optional[str],
    variant_id: Optional[str],
    qty_delta: int,
    reason: InventoryReason,
    created_by_user_id: str,
    related_sale_id: Optional[str] = None,
    related_refund_id: Optional[str] = None,
    notes: str = "",
    session=None,
) -> None:
    now = datetime.utcnow()
    collection = InventoryLedger.get_motor_collection()
    await collection.insert_one(
        {
            "tenant_id": tenant_id,
            "location_id": location_id,
            "product_id": product_id,
            "variant_id": variant_id,
            "qty_delta": qty_delta,
            "reason": reason,
            "related_sale_id": related_sale_id,
            "related_refund_id": related_refund_id,
            "created_by_user_id": created_by_user_id,
            "created_at": now,
            "notes": notes,
        },
        session=session,
    )


async def apply_sale_items(
    tenant_id: str,
    location_id: str,
    items: Iterable,
    user_id: str,
    sale_id: str,
    enforce_non_negative: bool = True,
    session=None,
) -> None:
    for item in items:
        if getattr(item, "is_service", False) or getattr(item, "is_subscription", False):
            continue
        await apply_stock_delta(
            tenant_id,
            location_id,
            item.product_id,
            item.variant_id,
            -int(item.quantity),
            enforce_non_negative=enforce_non_negative,
            session=session,
        )
        await record_ledger(
            tenant_id,
            location_id,
            item.product_id,
            item.variant_id,
            -int(item.quantity),
            InventoryReason.SALE,
            created_by_user_id=user_id,
            related_sale_id=sale_id,
            session=session,
        )


async def apply_refund_items(
    tenant_id: str,
    location_id: str,
    items: Iterable,
    user_id: str,
    refund_id: str,
    restock: bool,
    session=None,
) -> None:
    if not restock:
        return

    for item in items:
        await apply_stock_delta(
            tenant_id,
            location_id,
            item.product_id,
            item.variant_id,
            int(item.quantity),
            enforce_non_negative=False,
            session=session,
        )
        await record_ledger(
            tenant_id,
            location_id,
            item.product_id,
            item.variant_id,
            int(item.quantity),
            InventoryReason.REFUND,
            created_by_user_id=user_id,
            related_refund_id=refund_id,
            session=session,
        )


async def adjust_inventory(
    tenant_id: str,
    location_id: str,
    product_id: Optional[str],
    variant_id: Optional[str],
    qty_delta: int,
    user_id: str,
    notes: str = "",
    reorder_point: Optional[int] = None,
) -> int:
    new_qty = await apply_stock_delta(
        tenant_id,
        location_id,
        product_id,
        variant_id,
        qty_delta,
        enforce_non_negative=False,
    )
    await record_ledger(
        tenant_id,
        location_id,
        product_id,
        variant_id,
        qty_delta,
        InventoryReason.ADJUSTMENT,
        created_by_user_id=user_id,
        notes=notes,
    )

    if reorder_point is not None:
        collection = StockOnHand.get_motor_collection()
        await collection.update_one(
            {
                "tenant_id": tenant_id,
                "location_id": location_id,
                "product_id": product_id,
                "variant_id": variant_id,
            },
            {"$set": {"reorder_point": reorder_point, "updated_at": datetime.utcnow()}},
        )

    return new_qty


async def create_stock_transfer(
    tenant_id: str,
    from_location_id: str,
    to_location_id: str,
    items,
    user_id: str,
    status: StockTransferStatus = StockTransferStatus.DRAFT,
) -> StockTransfer:
    transfer = StockTransfer(
        tenant_id=tenant_id,
        from_location_id=from_location_id,
        to_location_id=to_location_id,
        status=status,
        created_by_user_id=user_id,
        items=items,
        created_at=datetime.utcnow(),
        updated_at=datetime.utcnow(),
    )
    await transfer.insert()
    return transfer


async def list_stock_transfers(
    tenant_id: str,
    status: Optional[StockTransferStatus] = None,
    from_location_id: Optional[str] = None,
    to_location_id: Optional[str] = None,
    limit: int = 50,
    offset: int = 0,
) -> list[StockTransfer]:
    conditions = [StockTransfer.tenant_id == tenant_id]
    if status:
        conditions.append(StockTransfer.status == status)
    if from_location_id:
        conditions.append(StockTransfer.from_location_id == from_location_id)
    if to_location_id:
        conditions.append(StockTransfer.to_location_id == to_location_id)
    return await (
        StockTransfer.find(*conditions)
        .sort(-StockTransfer.created_at)
        .skip(offset)
        .limit(limit)
        .to_list()
    )


async def receive_stock_transfer(
    tenant_id: str,
    transfer_id: str,
    user_id: str,
    session=None,
) -> StockTransfer:
    transfer = await StockTransfer.get(transfer_id)
    if not transfer or transfer.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Stock transfer not found")
    if transfer.status == StockTransferStatus.RECEIVED:
        return transfer

    for item in transfer.items:
        await apply_stock_delta(
            tenant_id,
            transfer.from_location_id,
            item.product_id,
            item.variant_id,
            -int(item.quantity),
            enforce_non_negative=True,
            session=session,
        )
        await record_ledger(
            tenant_id,
            transfer.from_location_id,
            item.product_id,
            item.variant_id,
            -int(item.quantity),
            InventoryReason.TRANSFER_OUT,
            created_by_user_id=user_id,
            notes=f"Transfer to {transfer.to_location_id}",
            session=session,
        )
        await apply_stock_delta(
            tenant_id,
            transfer.to_location_id,
            item.product_id,
            item.variant_id,
            int(item.quantity),
            enforce_non_negative=False,
            session=session,
        )
        await record_ledger(
            tenant_id,
            transfer.to_location_id,
            item.product_id,
            item.variant_id,
            int(item.quantity),
            InventoryReason.TRANSFER_IN,
            created_by_user_id=user_id,
            notes=f"Transfer from {transfer.from_location_id}",
            session=session,
        )

    transfer.status = StockTransferStatus.RECEIVED
    transfer.received_at = datetime.utcnow()
    transfer.updated_at = datetime.utcnow()
    await transfer.save(session=session)
    return transfer


async def create_stock_count(
    tenant_id: str,
    location_id: str,
    user_id: str,
) -> StockCount:
    stock = await StockOnHand.find(
        StockOnHand.tenant_id == tenant_id,
        StockOnHand.location_id == location_id,
    ).to_list()

    items = [
        {
            "product_id": s.product_id,
            "variant_id": s.variant_id,
            "expected_qty": s.qty_on_hand,
            "counted_qty": s.qty_on_hand,
        }
        for s in stock
    ]

    count = StockCount(
        tenant_id=tenant_id,
        location_id=location_id,
        status=StockCountStatus.OPEN,
        counted_by_user_id=user_id,
        items=items,
        started_at=datetime.utcnow(),
        created_at=datetime.utcnow(),
    )
    await count.insert()
    return count


async def list_stock_counts(
    tenant_id: str,
    status: Optional[StockCountStatus] = None,
    location_id: Optional[str] = None,
    limit: int = 50,
    offset: int = 0,
) -> list[StockCount]:
    conditions = [StockCount.tenant_id == tenant_id]
    if status:
        conditions.append(StockCount.status == status)
    if location_id:
        conditions.append(StockCount.location_id == location_id)
    return await (
        StockCount.find(*conditions)
        .sort(-StockCount.started_at)
        .skip(offset)
        .limit(limit)
        .to_list()
    )


async def complete_stock_count(
    tenant_id: str,
    count_id: str,
    user_id: str,
    items,
    session=None,
) -> StockCount:
    count = await StockCount.get(count_id)
    if not count or count.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Stock count not found")
    if count.status != StockCountStatus.OPEN:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Stock count already completed")

    expected_map = {(i.product_id, i.variant_id): i.expected_qty for i in count.items}
    updated_items = []
    for item in items:
        key = (item.product_id, item.variant_id)
        expected_qty = expected_map.get(key, 0)
        counted_qty = int(item.counted_qty)
        delta = counted_qty - int(expected_qty)
        if delta != 0:
            await apply_stock_delta(
                tenant_id,
                count.location_id,
                item.product_id,
                item.variant_id,
                delta,
                enforce_non_negative=False,
                session=session,
            )
            await record_ledger(
                tenant_id,
                count.location_id,
                item.product_id,
                item.variant_id,
                delta,
                InventoryReason.COUNT_ADJUSTMENT,
                created_by_user_id=user_id,
                notes="Stock count adjustment",
                session=session,
            )
        updated_items.append(
            {
                "product_id": item.product_id,
                "variant_id": item.variant_id,
                "expected_qty": expected_qty,
                "counted_qty": counted_qty,
            }
        )

    count.items = updated_items
    count.status = StockCountStatus.COMPLETED
    count.completed_at = datetime.utcnow()
    await count.save(session=session)
    return count
