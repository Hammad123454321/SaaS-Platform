"""
TenantTaskifyConfig model for storing per-tenant Taskify workspace configuration.

Each tenant that subscribes to the Tasks module gets their own Taskify workspace.
This model stores the mapping between our tenants and Taskify workspaces.
"""
from __future__ import annotations

from datetime import datetime
from typing import Optional

from beanie import Document
from pydantic import Field


class TenantTaskifyConfig(Document):
    """
    Stores Taskify configuration for each tenant.
    
    Created automatically when a tenant subscribes to the Tasks module via Stripe.
    """
    tenant_id: str = Field(..., index=True, unique=True)
    
    # Taskify workspace details
    workspace_id: int = Field(..., index=True)  # Taskify workspace ID
    workspace_name: Optional[str] = None
    
    # API authentication
    api_token: str  # Taskify API token (encrypted in production)
    base_url: str = Field(default="http://taskify:8001")  # Taskify instance URL
    
    # Admin user in Taskify (created during provisioning)
    taskify_admin_user_id: Optional[int] = None
    taskify_admin_email: Optional[str] = None
    
    # Status tracking
    is_active: bool = Field(default=True)
    provisioned_at: datetime = Field(default_factory=datetime.utcnow)
    last_sync_at: Optional[datetime] = None
    
    # Metadata
    config: dict = Field(default_factory=dict)  # Extra config
    
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "tenant_taskify_configs"
        indexes = [
            "tenant_id",
            "workspace_id",
        ]


class TaskifyUserMapping(Document):
    """
    Maps SaaS platform users to their Taskify user accounts.
    
    Created when:
    1. Business Owner subscribes to Tasks module (owner account)
    2. Staff is created by Business Owner (staff accounts)
    """
    # Our platform user
    user_id: str = Field(..., index=True)
    tenant_id: str = Field(..., index=True)
    
    # Taskify user details
    taskify_user_id: int = Field(..., index=True)
    taskify_email: str
    taskify_workspace_id: int
    
    # Role in Taskify (admin, member, etc.)
    taskify_role: str = Field(default="member")
    
    # Status
    is_active: bool = Field(default=True)
    last_login_at: Optional[datetime] = None
    
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "taskify_user_mappings"
        indexes = [
            "user_id",
            "tenant_id",
            "taskify_user_id",
        ]
