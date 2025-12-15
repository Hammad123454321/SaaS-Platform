from typing import Optional

from sqlmodel import Session

from app.models import AuditLog


def log_audit(
    session: Session,
    tenant_id: int,
    actor_user_id: int,
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
    session.add(entry)
    session.commit()


