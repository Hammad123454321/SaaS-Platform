from __future__ import annotations

from typing import List, Optional

from fastapi import HTTPException, status

from app.models.pos import SaleItem, Sale, KitchenStatus
from beanie import PydanticObjectId


async def list_kitchen_items(tenant_id: str, location_id: Optional[str] = None) -> List[dict]:
    conditions = [SaleItem.tenant_id == tenant_id, SaleItem.is_kitchen_item == True]
    conditions.append(SaleItem.kitchen_status != KitchenStatus.SERVED)

    items = await SaleItem.find(*conditions).to_list()
    sale_ids = list({item.sale_id for item in items})
    if sale_ids:
        object_ids = [PydanticObjectId(sid) for sid in sale_ids]
        sales_list = await Sale.find({"_id": {"$in": object_ids}}).to_list()
        sales = {str(sale.id): sale for sale in sales_list}
    else:
        sales = {}

    result = []
    for item in items:
        sale = sales.get(item.sale_id)
        if location_id and sale and sale.location_id != location_id:
            continue
        result.append(
            {
                "id": str(item.id),
                "sale_id": item.sale_id,
                "location_id": sale.location_id if sale else None,
                "product_name": item.product_name,
                "variant_name": item.variant_name,
                "quantity": item.quantity,
                "status": item.kitchen_status,
                "created_at": sale.created_at if sale else None,
            }
        )
    return result


async def update_kitchen_status(
    tenant_id: str,
    sale_item_id: str,
    status: KitchenStatus,
) -> SaleItem:
    item = await SaleItem.get(sale_item_id)
    if not item or item.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Kitchen item not found")
    item.kitchen_status = status
    await item.save()
    return item
