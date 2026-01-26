from __future__ import annotations

from datetime import datetime
from enum import StrEnum
from typing import Optional

from beanie import Document
from pydantic import Field


class ModuleCode(StrEnum):
    CRM = "crm"
    HRM = "hrm"
    POS = "pos"
    TASKS = "tasks"
    BOOKING = "booking"
    LANDING = "landing"
    AI = "ai"


class ModuleEntitlement(Document):
    tenant_id: str = Field(..., index=True)
    module_code: ModuleCode = Field(..., index=True)
    enabled: bool = Field(default=False)
    seats: int = Field(default=0)
    ai_access: bool = Field(default=False)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "module_entitlements"
        indexes = [
            "tenant_id",
            "module_code",
        ]


class Subscription(Document):
    tenant_id: str = Field(..., index=True)
    stripe_customer_id: Optional[str] = Field(default=None, index=True)
    stripe_subscription_id: Optional[str] = Field(default=None, index=True)
    stripe_price_id: Optional[str] = None
    status: str = Field(default="inactive")  # active, inactive, canceled, past_due
    amount: int = Field(default=0)  # Amount in cents (e.g., 2999 = $29.99)
    currency: str = Field(default="usd")
    interval: str = Field(default="month")  # month, year
    current_period_start: Optional[datetime] = None
    current_period_end: Optional[datetime] = None
    plan_name: Optional[str] = None
    modules: dict = Field(default_factory=dict)  # {"tasks": true, "crm": false}
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "subscriptions"
        indexes = [
            "tenant_id",
            "stripe_customer_id",
            "stripe_subscription_id",
        ]


class BillingHistory(Document):
    tenant_id: str = Field(..., index=True)
    event_type: str = Field(..., index=True)
    amount: Optional[int] = None
    currency: Optional[str] = None
    raw: dict = Field(default_factory=dict)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "billing_history"
        indexes = [
            "tenant_id",
            "event_type",
        ]


class WebhookEvent(Document):
    event_id: str = Field(..., index=True, unique=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "webhook_events"
        indexes = [
            "event_id",
        ]
