import pytest
from types import SimpleNamespace
from unittest.mock import AsyncMock

from fastapi import HTTPException

from app.models.pos import SaleStatus
from app.schemas.pos import RefundRequest, RefundItemInput
from app.services import pos_refunds


@pytest.mark.asyncio
async def test_refund_quantity_exceeds_purchase(monkeypatch):
    sale = SimpleNamespace(id="sale-1", tenant_id="tenant-1", status=SaleStatus.COMPLETED, location_id="loc-1")
    sale_item = SimpleNamespace(id="item-1", sale_id="sale-1", tenant_id="tenant-1", quantity=1, line_total_cents=1000, product_id="prod-1", variant_id=None)

    monkeypatch.setattr(pos_refunds.Sale, "get", AsyncMock(return_value=sale))
    class DummySaleItemModel:
        sale_id = object()
        tenant_id = object()

        @staticmethod
        def find(*args, **kwargs):
            return SimpleNamespace(to_list=AsyncMock(return_value=[sale_item]))

    class DummyRefundItemModel:
        sale_item_id = object()
        tenant_id = object()

        @staticmethod
        def find(*args, **kwargs):
            return SimpleNamespace(to_list=AsyncMock(return_value=[]))

    monkeypatch.setattr(pos_refunds, "SaleItem", DummySaleItemModel)
    monkeypatch.setattr(pos_refunds, "RefundItem", DummyRefundItemModel)

    payload = RefundRequest(
        sale_id="sale-1",
        items=[RefundItemInput(sale_item_id="item-1", quantity=2, restock=True)],
        reason="Over refund",
        payment_method=None,
    )

    with pytest.raises(HTTPException) as exc:
        await pos_refunds.create_refund("tenant-1", "user-1", payload)

    assert exc.value.status_code == 400
