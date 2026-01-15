"""Service for audit logging of registration and onboarding events."""
from datetime import datetime
from typing import Optional, Dict, Any
from sqlmodel import Session

from app.models.onboarding import RegistrationEvent


def log_registration_event(
    session: Session,
    user_id: int,
    tenant_id: int,
    event_type: str,
    ip_address: Optional[str] = None,
    user_agent: Optional[str] = None,
    metadata: Optional[Dict[str, Any]] = None
) -> RegistrationEvent:
    """Log a registration-related event."""
    event = RegistrationEvent(
        user_id=user_id,
        tenant_id=tenant_id,
        event_type=event_type,
        ip_address=ip_address,
        user_agent=user_agent,
        metadata=metadata or {}
    )
    session.add(event)
    session.commit()
    session.refresh(event)
    return event

