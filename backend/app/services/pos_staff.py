from __future__ import annotations

from datetime import datetime
from typing import List, Optional

from fastapi import HTTPException, status

from app.models import User
from app.models.pos import EmployeeProfile, TimeClockEntry, TimeClockStatus


async def upsert_employee_profile(tenant_id: str, payload) -> EmployeeProfile:
    user = await User.get(payload.user_id)
    if not user or user.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found")

    profile = await EmployeeProfile.find_one(
        EmployeeProfile.tenant_id == tenant_id,
        EmployeeProfile.user_id == payload.user_id,
    )
    if profile:
        profile.job_title = payload.job_title
        profile.hourly_rate_cents = payload.hourly_rate_cents
        profile.pos_pin = payload.pos_pin
        profile.location_ids = payload.location_ids or []
        profile.is_active = payload.is_active
        profile.hire_date = payload.hire_date
        profile.updated_at = datetime.utcnow()
        await profile.save()
        return profile

    profile = EmployeeProfile(
        tenant_id=tenant_id,
        user_id=payload.user_id,
        job_title=payload.job_title,
        hourly_rate_cents=payload.hourly_rate_cents,
        pos_pin=payload.pos_pin,
        location_ids=payload.location_ids or [],
        is_active=payload.is_active,
        hire_date=payload.hire_date,
    )
    await profile.insert()
    return profile


async def list_employee_profiles(tenant_id: str) -> List[EmployeeProfile]:
    return await EmployeeProfile.find(EmployeeProfile.tenant_id == tenant_id).to_list()


async def clock_in(
    tenant_id: str,
    user_id: str,
    location_id: Optional[str] = None,
    break_minutes: int = 0,
) -> TimeClockEntry:
    open_entry = await TimeClockEntry.find_one(
        TimeClockEntry.tenant_id == tenant_id,
        TimeClockEntry.user_id == user_id,
        TimeClockEntry.status == TimeClockStatus.OPEN,
    )
    if open_entry:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Already clocked in")

    entry = TimeClockEntry(
        tenant_id=tenant_id,
        user_id=user_id,
        location_id=location_id,
        status=TimeClockStatus.OPEN,
        clock_in=datetime.utcnow(),
        break_minutes=break_minutes or 0,
    )
    await entry.insert()
    return entry


async def clock_out(tenant_id: str, user_id: str, break_minutes: int = 0) -> TimeClockEntry:
    entry = await TimeClockEntry.find_one(
        TimeClockEntry.tenant_id == tenant_id,
        TimeClockEntry.user_id == user_id,
        TimeClockEntry.status == TimeClockStatus.OPEN,
    )
    if not entry:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="No active time clock entry")
    entry.status = TimeClockStatus.CLOSED
    entry.clock_out = datetime.utcnow()
    entry.break_minutes = break_minutes or entry.break_minutes
    await entry.save()
    return entry


async def list_time_entries(
    tenant_id: str,
    user_id: Optional[str] = None,
    start: Optional[datetime] = None,
    end: Optional[datetime] = None,
) -> List[TimeClockEntry]:
    conditions = [TimeClockEntry.tenant_id == tenant_id]
    if user_id:
        conditions.append(TimeClockEntry.user_id == user_id)
    if start:
        conditions.append(TimeClockEntry.clock_in >= start)
    if end:
        conditions.append(TimeClockEntry.clock_in <= end)
    return await TimeClockEntry.find(*conditions).sort(-TimeClockEntry.clock_in).to_list()
