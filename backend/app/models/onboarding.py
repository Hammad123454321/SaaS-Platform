"""
Onboarding Models - Stages 0, 1, 2

Stage 0: Account Creation & Email Verification
Stage 1: Business Profile & Jurisdiction
Stage 2: Roles & Access (Owner, Team Invites)
"""
from datetime import datetime, timedelta
from typing import Optional, TYPE_CHECKING
from enum import StrEnum
import secrets

from sqlalchemy import JSON, Column, Text, Index
from sqlmodel import SQLModel, Field, Relationship

if TYPE_CHECKING:
    from app.models.user import User
    from app.models.tenant import Tenant


# ========== Stage 0: Account Creation ==========

class EmailVerificationToken(SQLModel, table=True):
    """Email verification token for new user registrations."""
    __tablename__ = "email_verification_tokens"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    user_id: int = Field(foreign_key="users.id", index=True, unique=True)
    token: str = Field(index=True, unique=True)  # Hashed token stored in DB
    expires_at: datetime = Field()
    created_at: datetime = Field(default_factory=datetime.utcnow)
    verified_at: Optional[datetime] = Field(default=None)
    
    user: Optional["User"] = Relationship()


class PolicyType(StrEnum):
    PRIVACY_POLICY = "privacy_policy"
    TERMS_OF_SERVICE = "terms_of_service"


class PolicyAcceptance(SQLModel, table=True):
    """Tracks user acceptance of Privacy Policy and Terms of Service."""
    __tablename__ = "policy_acceptances"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    policy_type: PolicyType = Field(index=True)
    policy_version: str = Field(default="1.0")  # Hardcoded for now
    accepted_at: datetime = Field(default_factory=datetime.utcnow)
    ip_address: Optional[str] = Field(default=None)
    user_agent: Optional[str] = Field(default=None, sa_column=Column(Text))
    
    user: Optional["User"] = Relationship()
    
    __table_args__ = (
        Index("ix_policy_acceptances_user_policy", "user_id", "policy_type", unique=True),
    )


class CommunicationPreferences(SQLModel, table=True):
    """User communication preferences for email/SMS."""
    __tablename__ = "communication_preferences"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    user_id: int = Field(foreign_key="users.id", index=True, unique=True)
    email_enabled: bool = Field(default=True)
    sms_enabled: bool = Field(default=False)
    marketing_email_consent: bool = Field(default=False)  # CASL compliance
    marketing_email_consent_at: Optional[datetime] = Field(default=None)
    marketing_email_consent_source: Optional[str] = Field(default=None)  # e.g., "signup"
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    user: Optional["User"] = Relationship()


class RegistrationEvent(SQLModel, table=True):
    """Audit log for registration events."""
    __tablename__ = "registration_events"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    event_type: str = Field(index=True)  # "registration", "verification", "resend_verification"
    ip_address: Optional[str] = Field(default=None)
    user_agent: Optional[str] = Field(default=None, sa_column=Column(Text))
    event_data: Optional[dict] = Field(default=None, sa_column=Column(JSON))  # renamed from 'metadata' (reserved)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    
    user: Optional["User"] = Relationship()


# ========== Stage 1: Business Profile & Jurisdiction ==========

class ProvinceCode(StrEnum):
    ONTARIO = "ON"
    QUEBEC = "QC"
    BRITISH_COLUMBIA = "BC"
    ALBERTA = "AB"
    MANITOBA = "MB"
    SASKATCHEWAN = "SK"
    NOVA_SCOTIA = "NS"
    NEW_BRUNSWICK = "NB"
    NEWFOUNDLAND = "NL"
    PRINCE_EDWARD_ISLAND = "PE"
    NORTHWEST_TERRITORIES = "NT"
    YUKON = "YT"
    NUNAVUT = "NU"
    OTHER = "OTHER"


class BusinessProfile(SQLModel, table=True):
    """Business profile with jurisdiction information."""
    __tablename__ = "business_profiles"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True, unique=True)
    legal_business_name: str = Field()
    operating_name: Optional[str] = Field(default=None)
    province: ProvinceCode = Field(index=True)
    country: str = Field(default="Canada")
    timezone: str = Field(default="America/Toronto")
    primary_location: Optional[str] = Field(default=None, sa_column=Column(Text))
    business_email: Optional[str] = Field(default=None)
    business_phone: Optional[str] = Field(default=None)
    preferred_notification_channels: Optional[dict] = Field(default=None, sa_column=Column(JSON))
    is_confirmed: bool = Field(default=False)  # "Unconfirmed" state until later confirmations
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    tenant: Optional["Tenant"] = Relationship()
    compliance_rules: list["TenantComplianceRule"] = Relationship(back_populates="business_profile")


class ComplianceRuleCode(StrEnum):
    """Compliance rules that can be activated based on jurisdiction/industry."""
    PAWS = "PAWS"  # Provincial Animal Welfare Services (Ontario)
    WSIB = "WSIB"  # Workplace Safety and Insurance Board (Ontario)
    CFIA = "CFIA"  # Canadian Food Inspection Agency
    PIPEDA = "PIPEDA"  # Personal Information Protection and Electronic Documents Act
    CASL = "CASL"  # Canada's Anti-Spam Legislation
    # Add more as needed


class TenantComplianceRule(SQLModel, table=True):
    """Tracks which compliance rules are active for a tenant."""
    __tablename__ = "tenant_compliance_rules"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    business_profile_id: Optional[int] = Field(default=None, foreign_key="business_profiles.id", index=True)
    rule_code: ComplianceRuleCode = Field(index=True)
    activated_at: datetime = Field(default_factory=datetime.utcnow)
    activated_by_jurisdiction: bool = Field(default=True)  # True if auto-activated by jurisdiction mapping
    rule_data: Optional[dict] = Field(default=None, sa_column=Column(JSON))  # renamed from 'metadata' (reserved)
    
    business_profile: Optional["BusinessProfile"] = Relationship(back_populates="compliance_rules")
    
    __table_args__ = (
        Index("ix_tenant_compliance_rules_tenant_rule", "tenant_id", "rule_code", unique=True),
    )


# ========== Stage 2: Roles & Access ==========

class OwnerConfirmation(SQLModel, table=True):
    """Tracks Owner role confirmation and responsibility disclaimer."""
    __tablename__ = "owner_confirmations"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    user_id: int = Field(foreign_key="users.id", index=True, unique=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    confirmed_at: datetime = Field(default_factory=datetime.utcnow)
    responsibility_disclaimer_accepted: bool = Field(default=True)
    ip_address: Optional[str] = Field(default=None)
    user_agent: Optional[str] = Field(default=None, sa_column=Column(Text))
    
    user: Optional["User"] = Relationship()


class RoleTemplateCode(StrEnum):
    """Predefined role templates."""
    MANAGER = "manager"
    STAFF = "staff"
    ACCOUNTANT = "accountant"


class TeamInvitation(SQLModel, table=True):
    """Team member invitation with token-based acceptance."""
    __tablename__ = "team_invitations"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    invited_by_user_id: int = Field(foreign_key="users.id", index=True)
    email: str = Field(index=True)
    role_id: Optional[int] = Field(default=None, foreign_key="roles.id", index=True)
    token: str = Field(index=True, unique=True)  # Hashed token
    expires_at: datetime = Field()
    accepted_at: Optional[datetime] = Field(default=None)
    policy_accepted_at: Optional[datetime] = Field(default=None)  # Policy acceptance required per invitee
    created_at: datetime = Field(default_factory=datetime.utcnow)
    
    tenant: Optional["Tenant"] = Relationship()
    invited_by: Optional["User"] = Relationship()
    role: Optional["Role"] = Relationship()


class StaffOnboardingTask(SQLModel, table=True):
    """Simple onboarding tasks for staff (NOT in Task Management module)."""
    __tablename__ = "staff_onboarding_tasks"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    invitation_id: Optional[int] = Field(default=None, foreign_key="team_invitations.id", index=True)
    assigned_to_email: str = Field(index=True)  # Email of invited user (before they accept)
    task_title: str = Field()
    task_description: Optional[str] = Field(default=None, sa_column=Column(Text))
    is_completed: bool = Field(default=False)
    completed_at: Optional[datetime] = Field(default=None)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    due_date: Optional[datetime] = Field(default=None)
    
    invitation: Optional["TeamInvitation"] = Relationship()

