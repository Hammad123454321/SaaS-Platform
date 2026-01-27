"""Service for audit logging of registration and onboarding events (Mongo/Beanie)."""
from datetime import datetime
from typing import Optional, Dict, Any

from app.models.onboarding import RegistrationEvent


async def log_registration_event(
    user_id: str,
    tenant_id: str,
    event_type: str,
    ip_address: Optional[str] = None,
    user_agent: Optional[str] = None,
    metadata: Optional[Dict[str, Any]] = None,
) -> RegistrationEvent:
    """Log a registration-related event in MongoDB."""
    event = RegistrationEvent(
        user_id=user_id,
        tenant_id=tenant_id,
        event_type=event_type,
        ip_address=ip_address,
        user_agent=user_agent,
        event_data=metadata or {},
        created_at=datetime.utcnow(),
    )
    await event.insert()
    return event

