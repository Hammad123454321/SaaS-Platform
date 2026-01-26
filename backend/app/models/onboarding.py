"""
Onboarding Models - Stages 0, 1, 2

Stage 0: Account Creation & Email Verification
Stage 1: Business Profile & Jurisdiction
Stage 2: Roles & Access (Owner, Team Invites)
"""
from datetime import datetime, timedelta
from typing import Optional, List
from enum import StrEnum

from beanie import Document
from pydantic import Field


# ========== Stage 0: Account Creation ==========

class EmailVerificationToken(Document):
    """Email verification token for new user registrations."""
    
    user_id: str = Field(..., index=True, unique=True)
    token: str = Field(..., index=True, unique=True)  # Hashed token stored in DB
    expires_at: datetime
    created_at: datetime = Field(default_factory=datetime.utcnow)
    verified_at: Optional[datetime] = None

    class Settings:
        name = "email_verification_tokens"
        indexes = [
            "user_id",
            "token",
        ]


class PolicyType(StrEnum):
    PRIVACY_POLICY = "privacy_policy"
    TERMS_OF_SERVICE = "terms_of_service"


class PolicyAcceptance(Document):
    """Tracks user acceptance of Privacy Policy and Terms of Service."""
    
    user_id: str = Field(..., index=True)
    policy_type: PolicyType = Field(..., index=True)
    policy_version: str = Field(default="1.0")  # Hardcoded for now
    accepted_at: datetime = Field(default_factory=datetime.utcnow)
    ip_address: Optional[str] = None
    user_agent: Optional[str] = None

    class Settings:
        name = "policy_acceptances"
        indexes = [
            "user_id",
            "policy_type",
            ("user_id", "policy_type"),  # Compound unique index
        ]


class CommunicationPreferences(Document):
    """User communication preferences for email/SMS."""
    
    user_id: str = Field(..., index=True, unique=True)
    email_enabled: bool = Field(default=True)
    sms_enabled: bool = Field(default=False)
    marketing_email_consent: bool = Field(default=False)  # CASL compliance
    marketing_email_consent_at: Optional[datetime] = None
    marketing_email_consent_source: Optional[str] = None  # e.g., "signup"
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "communication_preferences"
        indexes = [
            "user_id",
        ]


class RegistrationEvent(Document):
    """Audit log for registration events."""
    
    user_id: str = Field(..., index=True)
    tenant_id: str = Field(..., index=True)
    event_type: str = Field(..., index=True)  # "registration", "verification", "resend_verification"
    ip_address: Optional[str] = None
    user_agent: Optional[str] = None
    event_data: Optional[dict] = None  # renamed from 'metadata' (reserved)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "registration_events"
        indexes = [
            "user_id",
            "tenant_id",
            "event_type",
        ]


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


class BusinessProfile(Document):
    """Business profile with jurisdiction information."""
    
    tenant_id: str = Field(..., index=True, unique=True)
    legal_business_name: str
    operating_name: Optional[str] = None
    province: ProvinceCode = Field(..., index=True)
    country: str = Field(default="Canada")
    timezone: str = Field(default="America/Toronto")
    primary_location: Optional[str] = None
    business_email: Optional[str] = None
    business_phone: Optional[str] = None
    preferred_notification_channels: Optional[dict] = None
    is_confirmed: bool = Field(default=False)  # "Unconfirmed" state until later confirmations
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "business_profiles"
        indexes = [
            "tenant_id",
            "province",
        ]


class ComplianceRuleCode(StrEnum):
    """Compliance rules that can be activated based on jurisdiction/industry."""
    PAWS = "PAWS"  # Provincial Animal Welfare Services (Ontario)
    WSIB = "WSIB"  # Workplace Safety and Insurance Board (Ontario)
    CFIA = "CFIA"  # Canadian Food Inspection Agency
    PIPEDA = "PIPEDA"  # Personal Information Protection and Electronic Documents Act
    CASL = "CASL"  # Canada's Anti-Spam Legislation
    # Add more as needed


class TenantComplianceRule(Document):
    """Tracks which compliance rules are active for a tenant."""
    
    tenant_id: str = Field(..., index=True)
    business_profile_id: Optional[str] = Field(default=None, index=True)
    rule_code: ComplianceRuleCode = Field(..., index=True)
    activated_at: datetime = Field(default_factory=datetime.utcnow)
    activated_by_jurisdiction: bool = Field(default=True)  # True if auto-activated by jurisdiction mapping
    rule_data: Optional[dict] = None  # renamed from 'metadata' (reserved)

    class Settings:
        name = "tenant_compliance_rules"
        indexes = [
            "tenant_id",
            "business_profile_id",
            "rule_code",
            ("tenant_id", "rule_code"),  # Compound unique index
        ]


# ========== Stage 2: Roles & Access ==========

class OwnerConfirmation(Document):
    """Tracks Owner role confirmation and responsibility disclaimer."""
    
    user_id: str = Field(..., index=True, unique=True)
    tenant_id: str = Field(..., index=True)
    confirmed_at: datetime = Field(default_factory=datetime.utcnow)
    responsibility_disclaimer_accepted: bool = Field(default=True)
    ip_address: Optional[str] = None
    user_agent: Optional[str] = None

    class Settings:
        name = "owner_confirmations"
        indexes = [
            "user_id",
            "tenant_id",
        ]


class RoleTemplateCode(StrEnum):
    """Predefined role templates."""
    MANAGER = "manager"
    STAFF = "staff"
    ACCOUNTANT = "accountant"


class TeamInvitation(Document):
    """Team member invitation with token-based acceptance."""
    
    tenant_id: str = Field(..., index=True)
    invited_by_user_id: str = Field(..., index=True)
    email: str = Field(..., index=True)
    role_id: Optional[str] = Field(default=None, index=True)
    token: str = Field(..., index=True, unique=True)  # Hashed token
    expires_at: datetime
    accepted_at: Optional[datetime] = None
    policy_accepted_at: Optional[datetime] = None  # Policy acceptance required per invitee
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "team_invitations"
        indexes = [
            "tenant_id",
            "invited_by_user_id",
            "email",
            "role_id",
            "token",
        ]


class StaffOnboardingTask(Document):
    """Simple onboarding tasks for staff (NOT in Task Management module)."""
    
    tenant_id: str = Field(..., index=True)
    invitation_id: Optional[str] = Field(default=None, index=True)
    assigned_to_email: str = Field(..., index=True)  # Email of invited user (before they accept)
    task_title: str
    task_description: Optional[str] = None
    is_completed: bool = Field(default=False)
    completed_at: Optional[datetime] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)
    due_date: Optional[datetime] = None

    class Settings:
        name = "staff_onboarding_tasks"
        indexes = [
            "tenant_id",
            "invitation_id",
            "assigned_to_email",
        ]
