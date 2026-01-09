from __future__ import annotations

from datetime import datetime
from enum import StrEnum
from typing import Optional

from sqlalchemy import JSON, Column
from sqlmodel import SQLModel, Field


class ModuleCode(StrEnum):
    CRM = "crm"
    HRM = "hrm"
    POS = "pos"
    TASKS = "tasks"
    BOOKING = "booking"
    LANDING = "landing"
    AI = "ai"


class ModuleEntitlement(SQLModel, table=True):
    __tablename__ = "module_entitlements"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    module_code: ModuleCode = Field(index=True)
    enabled: bool = Field(default=False)
    seats: int = Field(default=0)
    ai_access: bool = Field(default=False)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)


class Subscription(SQLModel, table=True):
    __tablename__ = "subscriptions"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    stripe_customer_id: Optional[str] = Field(default=None, index=True)
    stripe_subscription_id: Optional[str] = Field(default=None, index=True)
    stripe_price_id: Optional[str] = Field(default=None)  # Stripe price ID
    status: str = Field(default="inactive")  # active, inactive, canceled, past_due
    amount: int = Field(default=0)  # Amount in cents (e.g., 2999 = $29.99)
    currency: str = Field(default="usd")
    interval: str = Field(default="month")  # month, year
    current_period_start: Optional[datetime] = Field(default=None)
    current_period_end: Optional[datetime] = Field(default=None)
    plan_name: Optional[str] = Field(default=None)
    modules: dict = Field(default_factory=dict, sa_column=Column(JSON))  # {"tasks": true, "crm": false}
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)


class BillingHistory(SQLModel, table=True):
    __tablename__ = "billing_history"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    event_type: str = Field(index=True)
    amount: Optional[int] = Field(default=None)
    currency: Optional[str] = Field(default=None)
    raw: dict = Field(default_factory=dict, sa_column=Column(JSON))
    created_at: datetime = Field(default_factory=datetime.utcnow)


class WebhookEvent(SQLModel, table=True):
    __tablename__ = "webhook_events"

    id: Optional[int] = Field(default=None, primary_key=True)
    event_id: str = Field(index=True, unique=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)

