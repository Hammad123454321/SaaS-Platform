from typing import Optional

from app.models import AuditLog


async def log_audit(
    tenant_id: str,
    actor_user_id: str,
    action: str,
    target: str = "",
    details: Optional[dict] = None,
) -> None:
    entry = AuditLog(
        tenant_id=tenant_id,
        actor_user_id=actor_user_id,
        action=action,
        target=target,
        details=details or {},
    )
    await entry.insert()
