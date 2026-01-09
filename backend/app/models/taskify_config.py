"""
TenantTaskifyConfig model for storing per-tenant Taskify workspace configuration.

Each tenant that subscribes to the Tasks module gets their own Taskify workspace.
This model stores the mapping between our tenants and Taskify workspaces.
"""
from __future__ import annotations

from datetime import datetime
from typing import Optional

from sqlalchemy import JSON, Column
from sqlmodel import SQLModel, Field


class TenantTaskifyConfig(SQLModel, table=True):
    """
    Stores Taskify configuration for each tenant.
    
    Created automatically when a tenant subscribes to the Tasks module via Stripe.
    """
    __tablename__ = "tenant_taskify_configs"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True, unique=True)
    
    # Taskify workspace details
    workspace_id: int = Field(index=True)  # Taskify workspace ID
    workspace_name: Optional[str] = Field(default=None)
    
    # API authentication
    api_token: str  # Taskify API token (encrypted in production)
    base_url: str = Field(default="http://taskify:8001")  # Taskify instance URL
    
    # Admin user in Taskify (created during provisioning)
    taskify_admin_user_id: Optional[int] = Field(default=None)
    taskify_admin_email: Optional[str] = Field(default=None)
    
    # Status tracking
    is_active: bool = Field(default=True)
    provisioned_at: datetime = Field(default_factory=datetime.utcnow)
    last_sync_at: Optional[datetime] = Field(default=None)
    
    # Metadata
    config: dict = Field(default_factory=dict, sa_column=Column(JSON))  # Extra config
    
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)


class TaskifyUserMapping(SQLModel, table=True):
    """
    Maps SaaS platform users to their Taskify user accounts.
    
    Created when:
    1. Business Owner subscribes to Tasks module (owner account)
    2. Staff is created by Business Owner (staff accounts)
    """
    __tablename__ = "taskify_user_mappings"

    id: Optional[int] = Field(default=None, primary_key=True)
    
    # Our platform user
    user_id: int = Field(foreign_key="users.id", index=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    
    # Taskify user details
    taskify_user_id: int = Field(index=True)
    taskify_email: str
    taskify_workspace_id: int
    
    # Role in Taskify (admin, member, etc.)
    taskify_role: str = Field(default="member")
    
    # Status
    is_active: bool = Field(default=True)
    last_login_at: Optional[datetime] = Field(default=None)
    
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)





