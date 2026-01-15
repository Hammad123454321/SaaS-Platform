"""
User Permission Models - Stage 3

Per-user permissions (e.g., Staff can create projects if granted).
"""
from datetime import datetime
from typing import Optional, TYPE_CHECKING

from sqlmodel import SQLModel, Field, Relationship

if TYPE_CHECKING:
    from app.models.tenant import Tenant


class UserPermission(SQLModel, table=True):
    """Per-user permissions (extends role-based permissions).
    
    Note: User relationships are omitted due to multiple FKs to users table.
    Access user/granted_by via direct queries using user_id/granted_by_user_id.
    """
    __tablename__ = "user_permissions"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    permission_code: str = Field(index=True)  # e.g., "create_projects"
    granted_at: datetime = Field(default_factory=datetime.utcnow)
    granted_by_user_id: Optional[int] = Field(default=None, foreign_key="users.id", index=True)
    
    # Only keep tenant relationship (single FK, no ambiguity)
    tenant: Optional["Tenant"] = Relationship()
