"""
Compliance Models - Stage 4

Privacy/CASL wording, Financial setup, HR policies, and employee acknowledgements.
"""
from datetime import datetime
from typing import Optional, List
from enum import StrEnum

from beanie import Document
from pydantic import Field


class PayrollType(StrEnum):
    WEEKLY = "weekly"
    BI_WEEKLY = "bi_weekly"
    MONTHLY = "monthly"
    SEMI_MONTHLY = "semi_monthly"


class PaySchedule(StrEnum):
    """Payday options."""
    MONDAY = "monday"
    TUESDAY = "tuesday"
    WEDNESDAY = "wednesday"
    THURSDAY = "thursday"
    FRIDAY = "friday"
    LAST_DAY_OF_MONTH = "last_day_of_month"
    FIRST_DAY_OF_MONTH = "first_day_of_month"
    FIFTEENTH = "fifteenth"
    LAST_FRIDAY = "last_friday"


class WSIBClass(StrEnum):
    """Predefined WSIB classes."""
    RETAIL = "retail"
    OFFICE = "office"
    CONSTRUCTION = "construction"
    MANUFACTURING = "manufacturing"
    HEALTHCARE = "healthcare"
    FOOD_SERVICE = "food_service"
    GROOMING = "grooming"
    DAYCARE = "daycare"
    OTHER = "other"


class FinancialSetup(Document):
    """Financial setup configuration for tenant."""
    
    tenant_id: str = Field(..., index=True, unique=True)
    payroll_type: Optional[PayrollType] = None
    pay_schedule: Optional[PaySchedule] = None
    wsib_class: Optional[WSIBClass] = None
    is_confirmed: bool = Field(default=False)  # Must confirm before activation
    confirmed_at: Optional[datetime] = None
    confirmed_by_user_id: Optional[str] = Field(default=None, index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "financial_setups"
        indexes = [
            "tenant_id",
            "confirmed_by_user_id",
        ]


class HRPolicyType(StrEnum):
    """Predefined HR policy types."""
    HEALTH_SAFETY = "health_safety"
    HARASSMENT = "harassment"
    TRAINING = "training"


class HRPolicy(Document):
    """HR policy templates (shared across all tenants)."""
    
    policy_type: HRPolicyType = Field(..., index=True, unique=True)
    title: str
    content: str  # Policy text content
    is_required: bool = Field(default=True)  # Required for all employees
    version: str = Field(default="1.0")
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "hr_policies"
        indexes = [
            "policy_type",
        ]


class PolicyAcknowledgement(Document):
    """Employee acknowledgement of HR policies."""
    
    user_id: str = Field(..., index=True)
    policy_id: str = Field(..., index=True)
    acknowledged_at: datetime = Field(default_factory=datetime.utcnow)
    ip_address: Optional[str] = None
    user_agent: Optional[str] = None

    class Settings:
        name = "policy_acknowledgements"
        indexes = [
            "user_id",
            "policy_id",
            ("user_id", "policy_id"),  # Compound unique index
        ]


class PrivacyWording(Document):
    """Hardcoded privacy policy wording (versioned)."""
    
    tenant_id: str = Field(..., index=True)
    wording_type: str = Field(..., index=True)  # "privacy_policy" or "casl"
    version: str = Field(default="1.0")
    content: str  # Hardcoded content
    confirmed_at: Optional[datetime] = None
    confirmed_by_user_id: Optional[str] = Field(default=None, index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "privacy_wordings"
        indexes = [
            "tenant_id",
            "wording_type",
            "confirmed_by_user_id",
        ]
