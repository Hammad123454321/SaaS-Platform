"""
Compliance Models - Stage 4

Privacy/CASL wording, Financial setup, HR policies, and employee acknowledgements.
"""
from datetime import datetime
from typing import Optional, TYPE_CHECKING
from enum import StrEnum

from sqlalchemy import JSON, Column, Text
from sqlmodel import SQLModel, Field, Relationship

if TYPE_CHECKING:
    from app.models.user import User
    from app.models.tenant import Tenant


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


class FinancialSetup(SQLModel, table=True):
    """Financial setup configuration for tenant."""
    __tablename__ = "financial_setups"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True, unique=True)
    payroll_type: Optional[PayrollType] = Field(default=None)
    pay_schedule: Optional[PaySchedule] = Field(default=None)
    wsib_class: Optional[WSIBClass] = Field(default=None)
    is_confirmed: bool = Field(default=False)  # Must confirm before activation
    confirmed_at: Optional[datetime] = Field(default=None)
    confirmed_by_user_id: Optional[int] = Field(default=None, foreign_key="users.id", index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    tenant: Optional["Tenant"] = Relationship()
    confirmed_by: Optional["User"] = Relationship()


class HRPolicyType(StrEnum):
    """Predefined HR policy types."""
    HEALTH_SAFETY = "health_safety"
    HARASSMENT = "harassment"
    TRAINING = "training"


class HRPolicy(SQLModel, table=True):
    """HR policy templates (shared across all tenants)."""
    __tablename__ = "hr_policies"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    policy_type: HRPolicyType = Field(index=True, unique=True)
    title: str = Field()
    content: str = Field(sa_column=Column(Text))  # Policy text content
    is_required: bool = Field(default=True)  # Required for all employees
    version: str = Field(default="1.0")
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)


class PolicyAcknowledgement(SQLModel, table=True):
    """Employee acknowledgement of HR policies."""
    __tablename__ = "policy_acknowledgements"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    policy_id: int = Field(foreign_key="hr_policies.id", index=True)
    acknowledged_at: datetime = Field(default_factory=datetime.utcnow)
    ip_address: Optional[str] = Field(default=None)
    user_agent: Optional[str] = Field(default=None, sa_column=Column(Text))
    
    user: Optional["User"] = Relationship()
    policy: Optional["HRPolicy"] = Relationship()
    
    __table_args__ = (
        # Ensure one acknowledgement per user per policy
        # This will be enforced at application level for now
    )


class PrivacyWording(SQLModel, table=True):
    """Hardcoded privacy policy wording (versioned)."""
    __tablename__ = "privacy_wordings"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    wording_type: str = Field(index=True)  # "privacy_policy" or "casl"
    version: str = Field(default="1.0")
    content: str = Field(sa_column=Column(Text))  # Hardcoded content
    confirmed_at: Optional[datetime] = Field(default=None)
    confirmed_by_user_id: Optional[int] = Field(default=None, foreign_key="users.id", index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    
    tenant: Optional["Tenant"] = Relationship()
    confirmed_by: Optional["User"] = Relationship()

