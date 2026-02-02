import pytest
from types import SimpleNamespace
from unittest.mock import AsyncMock

from app.models.pos import SaleStatus, PaymentMethod
from app.schemas.pos import FinalizeSaleRequest, PaymentInput
from app.services import pos_sales


class DummySale:
    def __init__(self):
        self.id = "sale-1"
        self.tenant_id = "tenant-1"
        self.status = SaleStatus.DRAFT
        self.total_cents = 1000
        self.subtotal_cents = 900
        self.discount_cents = 0
        self.tax_cents = 100
        self.register_id = "reg-1"
        self.register_session_id = None
        self.location_id = None
        self.cashier_id = "user-1"
        self.completed_at = None
        self.paid_cents = 0
        self.change_due_cents = 0
        self.updated_at = None

    async def save(self, session=None):
        return None


class DummySaleItemQuery:
    def __init__(self, items):
        self._items = items

    async def to_list(self):
        return self._items

    async def update(self, *args, **kwargs):
        return None


class DummySession:
    async def __aenter__(self):
        return self

    async def __aexit__(self, exc_type, exc, tb):
        return False

    def start_transaction(self):
        return self


@pytest.mark.asyncio
async def test_finalize_sale_records_payments_and_receipt(monkeypatch):
    sale = DummySale()
    sale_items = [
        SimpleNamespace(
            product_id="prod-1",
            variant_id=None,
            quantity=1,
            product_name="Latte",
            variant_name=None,
            sku="LAT-1",
            unit_price_cents=1000,
            discount_cents=0,
            tax_cents=0,
            line_total_cents=1000,
        )
    ]

    monkeypatch.setattr(pos_sales.Sale, "get", AsyncMock(return_value=sale))
    class DummySaleItemModel:
        sale_id = object()
        tenant_id = object()

        @staticmethod
        def find(*args, **kwargs):
            return DummySaleItemQuery(sale_items)

    monkeypatch.setattr(pos_sales, "SaleItem", DummySaleItemModel)
    monkeypatch.setattr(pos_sales, "apply_sale_items", AsyncMock(return_value=None))
    monkeypatch.setattr(pos_sales, "get_open_session_for_register", AsyncMock(return_value=SimpleNamespace(id="sess-1", location_id="loc-1")))
    monkeypatch.setattr(pos_sales, "adjust_expected_cash", AsyncMock(return_value=None))
    monkeypatch.setattr(pos_sales, "log_audit", AsyncMock(return_value=None))

    payment_inserts = []
    receipt_inserts = []

    class DummyPayment:
        def __init__(self, **kwargs):
            self.kwargs = kwargs

        async def insert(self, session=None):
            payment_inserts.append(self.kwargs)

    class DummyReceipt:
        def __init__(self, **kwargs):
            self.kwargs = kwargs

        async def insert(self, session=None):
            receipt_inserts.append(self.kwargs)

    monkeypatch.setattr(pos_sales, "Payment", DummyPayment)
    monkeypatch.setattr(pos_sales, "Receipt", DummyReceipt)
    dummy_client = SimpleNamespace(start_session=lambda: DummySession())
    monkeypatch.setattr(pos_sales, "client", dummy_client)

    payload = FinalizeSaleRequest(payments=[PaymentInput(method=PaymentMethod.CASH, amount_cents=1000)])
    result = await pos_sales.finalize_sale("tenant-1", "sale-1", "user-1", payload)

    assert result.status == SaleStatus.COMPLETED
    assert len(payment_inserts) == 1
    assert len(receipt_inserts) == 1
