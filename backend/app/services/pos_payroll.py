from __future__ import annotations

from datetime import datetime
from typing import Dict, List

from app.models import User
from app.models.pos import TimeClockEntry, EmployeeProfile, TimeClockStatus


async def get_payroll_summary(tenant_id: str, start: datetime, end: datetime) -> List[Dict]:
    entries = await TimeClockEntry.find(
        TimeClockEntry.tenant_id == tenant_id,
        TimeClockEntry.clock_in >= start,
        TimeClockEntry.clock_in <= end,
    ).to_list()

    profiles = await EmployeeProfile.find(EmployeeProfile.tenant_id == tenant_id).to_list()
    profile_map = {p.user_id: p for p in profiles}

    totals: Dict[str, Dict] = {}
    for entry in entries:
        clock_out = entry.clock_out or datetime.utcnow()
        duration_seconds = max((clock_out - entry.clock_in).total_seconds(), 0)
        break_seconds = max(entry.break_minutes or 0, 0) * 60
        hours = max((duration_seconds - break_seconds) / 3600, 0)

        if entry.user_id not in totals:
            profile = profile_map.get(entry.user_id)
            totals[entry.user_id] = {
                "user_id": entry.user_id,
                "hours": 0.0,
                "hourly_rate_cents": profile.hourly_rate_cents if profile else None,
                "gross_pay_cents": 0,
            }
        totals[entry.user_id]["hours"] += hours

    users = await User.find(User.tenant_id == tenant_id).to_list()
    user_map = {str(u.id): u for u in users}

    results = []
    for user_id, data in totals.items():
        hourly_rate = data.get("hourly_rate_cents") or 0
        gross_pay = int(round(data["hours"] * hourly_rate))
        user = user_map.get(user_id)
        results.append(
            {
                "user_id": user_id,
                "email": user.email if user else None,
                "hours": round(data["hours"], 2),
                "hourly_rate_cents": hourly_rate,
                "gross_pay_cents": gross_pay,
            }
        )

    return results
