import pytest
from types import SimpleNamespace

from app.schemas.pos import SaleItemInput
from app.services import pos_pricing


@pytest.mark.asyncio
async def test_pricing_percent_discount_and_tax(monkeypatch):
    product = SimpleNamespace(
        id="prod-1",
        tenant_id="tenant-1",
        is_active=True,
        name="Widget",
        category_id=None,
        sku="W-1",
        barcode=None,
        base_price_cents=1000,
        tax_ids=["tax-1"],
    )
    tax = SimpleNamespace(
        id="tax-1",
        tenant_id="tenant-1",
        is_active=True,
        rate_bps=1000,
        is_inclusive=False,
    )

    async def fake_product_get(_id):
        return product

    async def fake_tax_get(_id):
        return tax

    monkeypatch.setattr(pos_pricing.Product, "get", fake_product_get)
    monkeypatch.setattr(pos_pricing.Tax, "get", fake_tax_get)

    items = [
        SaleItemInput(
            product_id="prod-1",
            quantity=2,
            discount_type="percent",
            discount_bps=1000,
            tax_ids=["tax-1"],
        )
    ]

    result = await pos_pricing.calculate_pricing("tenant-1", items, None)
    assert result["subtotal_cents"] == 2000
    assert result["discount_cents"] == 200
    assert result["tax_cents"] == 180
    assert result["total_cents"] == 1980
