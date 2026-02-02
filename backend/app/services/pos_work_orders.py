from __future__ import annotations

from datetime import datetime
from typing import List, Optional

from fastapi import HTTPException, status

from app.models.pos import WorkOrder, WorkOrderStatus


async def create_work_order(tenant_id: str, user_id: str, payload) -> WorkOrder:
    order = WorkOrder(
        tenant_id=tenant_id,
        location_id=payload.location_id,
        customer_id=payload.customer_id,
        sale_id=payload.sale_id,
        title=payload.title,
        description=payload.description,
        status=payload.status,
        priority=payload.priority,
        assigned_to_user_id=payload.assigned_to_user_id,
        due_at=payload.due_at,
        items=payload.items or [],
        notes=payload.notes,
        created_by_user_id=user_id,
    )
    await order.insert()
    return order


async def list_work_orders(tenant_id: str, status_filter: Optional[WorkOrderStatus] = None) -> List[WorkOrder]:
    conditions = [WorkOrder.tenant_id == tenant_id]
    if status_filter:
        conditions.append(WorkOrder.status == status_filter)
    return await WorkOrder.find(*conditions).sort(-WorkOrder.created_at).to_list()


async def update_work_order(tenant_id: str, work_order_id: str, payload) -> WorkOrder:
    order = await WorkOrder.get(work_order_id)
    if not order or order.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Work order not found")

    if payload.status:
        order.status = payload.status
    if payload.priority is not None:
        order.priority = payload.priority
    if payload.assigned_to_user_id is not None:
        order.assigned_to_user_id = payload.assigned_to_user_id
    if payload.due_at is not None:
        order.due_at = payload.due_at
    if payload.items is not None:
        order.items = payload.items
    if payload.notes is not None:
        order.notes = payload.notes

    order.updated_at = datetime.utcnow()
    await order.save()
    return order
