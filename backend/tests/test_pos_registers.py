import pytest
from types import SimpleNamespace
from unittest.mock import AsyncMock

from fastapi import HTTPException

from app.services import pos_registers


@pytest.mark.asyncio
async def test_open_register_session_conflict(monkeypatch):
    register = SimpleNamespace(id="reg-1", tenant_id="tenant-1", location_id="loc-1", is_active=True)

    async def fake_register_get(_id):
        return register

    class DummyRegisterSession:
        tenant_id = object()
        register_id = object()
        status = object()

        def __init__(self, **kwargs):
            for key, value in kwargs.items():
                setattr(self, key, value)

        @staticmethod
        async def find_one(*args, **kwargs):
            return SimpleNamespace(id="sess-1")

    monkeypatch.setattr(pos_registers.Register, "get", fake_register_get)
    monkeypatch.setattr(pos_registers, "RegisterSession", DummyRegisterSession)

    with pytest.raises(HTTPException) as exc:
        await pos_registers.open_register_session("tenant-1", "reg-1", 1000, "user-1")

    assert exc.value.status_code == 409


@pytest.mark.asyncio
async def test_open_register_session_success(monkeypatch):
    register = SimpleNamespace(id="reg-1", tenant_id="tenant-1", location_id="loc-1", is_active=True)

    async def fake_register_get(_id):
        return register

    class DummyRegisterSession:
        tenant_id = object()
        register_id = object()
        status = object()

        def __init__(self, **kwargs):
            for key, value in kwargs.items():
                setattr(self, key, value)

        @staticmethod
        async def find_one(*args, **kwargs):
            return None

        async def insert(self):
            return None

    monkeypatch.setattr(pos_registers.Register, "get", fake_register_get)
    monkeypatch.setattr(pos_registers, "RegisterSession", DummyRegisterSession)
    monkeypatch.setattr(pos_registers.CashCount, "insert", AsyncMock(return_value=None))

    session = await pos_registers.open_register_session("tenant-1", "reg-1", 2500, "user-1")
    assert session.opening_cash_cents == 2500
    assert session.expected_cash_cents == 2500
