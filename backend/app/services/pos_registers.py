from __future__ import annotations

from datetime import datetime
from typing import Optional, Dict

from fastapi import HTTPException, status

from app.models.pos import Register, RegisterSession, RegisterSessionStatus, CashMovement, CashMovementType, CashCount


async def get_register(tenant_id: str, register_id: str) -> Register:
    register = await Register.get(register_id)
    if not register or register.tenant_id != tenant_id or not register.is_active:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Register not found")
    return register


async def get_open_session_for_register(tenant_id: str, register_id: str) -> Optional[RegisterSession]:
    return await RegisterSession.find_one(
        RegisterSession.tenant_id == tenant_id,
        RegisterSession.register_id == register_id,
        RegisterSession.status == RegisterSessionStatus.OPEN,
    )


async def open_register_session(
    tenant_id: str,
    register_id: str,
    opening_cash_cents: int,
    user_id: str,
    denominations: Optional[Dict[str, int]] = None,
) -> RegisterSession:
    register = await get_register(tenant_id, register_id)
    existing = await get_open_session_for_register(tenant_id, register_id)
    if existing:
        raise HTTPException(status_code=status.HTTP_409_CONFLICT, detail="Register already has an open session")

    session = RegisterSession(
        tenant_id=tenant_id,
        register_id=register_id,
        location_id=register.location_id,
        opened_by_user_id=user_id,
        opened_at=datetime.utcnow(),
        opening_cash_cents=opening_cash_cents,
        expected_cash_cents=opening_cash_cents,
        status=RegisterSessionStatus.OPEN,
    )
    await session.insert()

    if denominations is not None:
        cash_count = CashCount(
            tenant_id=tenant_id,
            register_session_id=str(session.id),
            denominations=denominations,
            total_cents=opening_cash_cents,
            created_by_user_id=user_id,
        )
        await cash_count.insert()

    return session


async def close_register_session(
    tenant_id: str,
    register_id: str,
    closing_cash_cents: int,
    user_id: str,
    denominations: Optional[Dict[str, int]] = None,
) -> RegisterSession:
    session = await get_open_session_for_register(tenant_id, register_id)
    if not session:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Open session not found")

    session.status = RegisterSessionStatus.CLOSED
    session.closed_at = datetime.utcnow()
    session.closed_by_user_id = user_id
    session.closing_cash_cents = closing_cash_cents
    session.cash_difference_cents = closing_cash_cents - session.expected_cash_cents
    await session.save()

    if denominations is not None:
        cash_count = CashCount(
            tenant_id=tenant_id,
            register_session_id=str(session.id),
            denominations=denominations,
            total_cents=closing_cash_cents,
            created_by_user_id=user_id,
        )
        await cash_count.insert()

    return session


async def record_cash_movement(
    tenant_id: str,
    register_session_id: str,
    movement_type: CashMovementType,
    amount_cents: int,
    reason: str,
    user_id: str,
) -> CashMovement:
    session = await RegisterSession.get(register_session_id)
    if not session or session.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Register session not found")
    if session.status != RegisterSessionStatus.OPEN:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Register session is closed")

    movement = CashMovement(
        tenant_id=tenant_id,
        register_session_id=register_session_id,
        movement_type=movement_type,
        amount_cents=amount_cents,
        reason=reason or "",
        created_by_user_id=user_id,
    )
    await movement.insert()

    delta = amount_cents if movement_type == CashMovementType.PAID_IN else -amount_cents
    session.expected_cash_cents += delta
    await session.save()

    return movement


async def adjust_expected_cash(register_session_id: str, delta_cents: int) -> None:
    session = await RegisterSession.get(register_session_id)
    if not session:
        return
    if session.status != RegisterSessionStatus.OPEN:
        return
    session.expected_cash_cents += delta_cents
    await session.save()
