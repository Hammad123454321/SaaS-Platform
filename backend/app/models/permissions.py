"""
User Permission Models - Stage 3

Per-user permissions (e.g., Staff can create projects if granted).
"""
from datetime import datetime
from typing import Optional

from beanie import Document
from pydantic import Field


class UserPermission(Document):
    """Per-user permissions (extends role-based permissions).
    
    Note: User relationships are omitted due to multiple FKs to users table.
    Access user/granted_by via direct queries using user_id/granted_by_user_id.
    """
    user_id: str = Field(..., index=True)
    tenant_id: str = Field(..., index=True)
    permission_code: str = Field(..., index=True)  # e.g., "create_projects"
    granted_at: datetime = Field(default_factory=datetime.utcnow)
    granted_by_user_id: Optional[str] = Field(default=None, index=True)

    class Settings:
        name = "user_permissions"
        indexes = [
            "user_id",
            "tenant_id",
            "permission_code",
        ]
