from datetime import datetime
from typing import Optional, List

from beanie import Document, Link
from pydantic import Field, EmailStr

from app.models.tenant import Tenant
from app.models.role import Role


class User(Document):
    """User model for MongoDB."""
    
    tenant_id: Optional[str] = Field(default=None, index=True)
    email: EmailStr = Field(..., index=True)
    hashed_password: str
    is_active: bool = Field(default=True)
    is_super_admin: bool = Field(default=False)
    email_verified: bool = Field(default=False)  # Stage 0: Hard gate - must be True to login
    is_owner: bool = Field(default=False)  # Stage 2: Owner role flag
    password_change_required: bool = Field(default=False)  # Require password change on first login
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships (using Link for references)
    tenant: Optional[Link[Tenant]] = None
    role_ids: List[str] = Field(default_factory=list)  # Store role IDs

    class Settings:
        name = "users"
        indexes = [
            "tenant_id",
            "email",
        ]
