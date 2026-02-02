from __future__ import annotations

from datetime import datetime
from typing import List, Optional

from fastapi import HTTPException, status

from app.models.pos import Sale, FulfillmentInfo, FulfillmentStatus


async def update_fulfillment(
    tenant_id: str,
    sale_id: str,
    payload,
) -> Sale:
    sale = await Sale.get(sale_id)
    if not sale or sale.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Sale not found")

    old_shipping = sale.shipping_cents or 0
    sale.fulfillment = FulfillmentInfo(**payload.model_dump())
    sale.shipping_cents = sale.fulfillment.shipping_cost_cents or 0
    sale.total_cents = max(sale.total_cents - old_shipping + sale.shipping_cents, 0)
    sale.updated_at = datetime.utcnow()
    await sale.save()
    return sale


async def list_fulfillment_orders(
    tenant_id: str,
    status: Optional[FulfillmentStatus] = None,
    location_id: Optional[str] = None,
) -> List[Sale]:
    conditions = [Sale.tenant_id == tenant_id]
    if status:
        conditions.append({"fulfillment.status": status})
    if location_id:
        conditions.append(Sale.location_id == location_id)
    return await Sale.find(*conditions).sort(-Sale.created_at).to_list()
