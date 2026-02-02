from __future__ import annotations

from datetime import datetime
from typing import Optional

from fastapi import HTTPException, status

from app.models.pos import LoyaltyProgram, LoyaltyAccount, LoyaltyLedger, LoyaltyLedgerReason


async def get_active_loyalty_program(tenant_id: str) -> Optional[LoyaltyProgram]:
    return await LoyaltyProgram.find_one(
        LoyaltyProgram.tenant_id == tenant_id,
        LoyaltyProgram.is_active == True,
    )


async def get_or_create_loyalty_account(tenant_id: str, customer_id: str) -> LoyaltyAccount:
    account = await LoyaltyAccount.find_one(
        LoyaltyAccount.tenant_id == tenant_id,
        LoyaltyAccount.customer_id == customer_id,
    )
    if account:
        return account
    account = LoyaltyAccount(tenant_id=tenant_id, customer_id=customer_id, points_balance=0)
    await account.insert()
    return account


async def redeem_loyalty_points(
    tenant_id: str,
    customer_id: str,
    points: int,
    user_id: str,
    sale_id: Optional[str] = None,
    session=None,
) -> None:
    account = await get_or_create_loyalty_account(tenant_id, customer_id)
    if account.points_balance < points:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Insufficient loyalty points")

    collection = LoyaltyAccount.get_motor_collection()
    result = await collection.update_one(
        {"_id": account.id, "points_balance": {"$gte": points}},
        {"$inc": {"points_balance": -points}, "$set": {"updated_at": datetime.utcnow()}},
        session=session,
    )
    if result.modified_count == 0:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Insufficient loyalty points")

    ledger = LoyaltyLedger(
        tenant_id=tenant_id,
        customer_id=customer_id,
        sale_id=sale_id,
        points_delta=-points,
        reason=LoyaltyLedgerReason.REDEEM,
        created_by_user_id=user_id,
    )
    await ledger.insert(session=session)


async def earn_loyalty_points(
    tenant_id: str,
    customer_id: str,
    amount_cents: int,
    user_id: str,
    sale_id: Optional[str] = None,
    session=None,
) -> int:
    program = await get_active_loyalty_program(tenant_id)
    if not program:
        return 0
    if amount_cents <= 0:
        return 0

    points = int((amount_cents // 100) * program.points_per_currency_unit)
    if points <= 0:
        return 0

    account = await get_or_create_loyalty_account(tenant_id, customer_id)
    collection = LoyaltyAccount.get_motor_collection()
    await collection.update_one(
        {"_id": account.id},
        {"$inc": {"points_balance": points}, "$set": {"updated_at": datetime.utcnow()}},
        session=session,
    )

    ledger = LoyaltyLedger(
        tenant_id=tenant_id,
        customer_id=customer_id,
        sale_id=sale_id,
        points_delta=points,
        reason=LoyaltyLedgerReason.EARN,
        created_by_user_id=user_id,
    )
    await ledger.insert(session=session)
    return points


async def adjust_loyalty_points(
    tenant_id: str,
    customer_id: str,
    points_delta: int,
    user_id: str,
    reason: LoyaltyLedgerReason = LoyaltyLedgerReason.ADJUST,
) -> LoyaltyAccount:
    account = await get_or_create_loyalty_account(tenant_id, customer_id)
    collection = LoyaltyAccount.get_motor_collection()
    await collection.update_one(
        {"_id": account.id},
        {"$inc": {"points_balance": points_delta}, "$set": {"updated_at": datetime.utcnow()}},
    )
    ledger = LoyaltyLedger(
        tenant_id=tenant_id,
        customer_id=customer_id,
        points_delta=points_delta,
        reason=reason,
        created_by_user_id=user_id,
    )
    await ledger.insert()
    return await get_or_create_loyalty_account(tenant_id, customer_id)
