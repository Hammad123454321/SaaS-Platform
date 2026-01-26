from datetime import datetime, timedelta
from typing import Optional

from beanie import Document
from pydantic import Field


class PasswordResetToken(Document):
    user_id: str = Field(..., index=True)
    token_hash: str = Field(..., index=True)
    expires_at: datetime = Field(default_factory=lambda: datetime.utcnow() + timedelta(hours=2))
    used_at: Optional[datetime] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "password_reset_tokens"
        indexes = [
            "user_id",
            "token_hash",
        ]


class ImpersonationAudit(Document):
    actor_user_id: str = Field(..., index=True)
    target_user_id: str = Field(..., index=True)
    reason: str = Field(default="")
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "impersonation_audit"
        indexes = [
            "actor_user_id",
            "target_user_id",
        ]


class AuditLog(Document):
    tenant_id: str = Field(..., index=True)
    actor_user_id: str = Field(..., index=True)
    action: str = Field(..., index=True)
    target: str = Field(default="")
    details: dict = Field(default_factory=dict)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "audit_log"
        indexes = [
            "tenant_id",
            "actor_user_id",
            "action",
        ]
