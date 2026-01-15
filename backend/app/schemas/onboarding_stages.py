"""Schemas for onboarding stages 0, 1, 2."""
from pydantic import BaseModel, EmailStr, Field
from typing import Optional, List
from datetime import datetime

from app.models.onboarding import ProvinceCode, ComplianceRuleCode


# ========== Stage 0: Verification ==========

class VerifyEmailRequest(BaseModel):
    token: str = Field(description="Email verification token")


class ResendVerificationRequest(BaseModel):
    email: EmailStr


class VerificationStatusResponse(BaseModel):
    email_verified: bool
    email: str


# ========== Stage 1: Business Profile ==========

class BusinessProfileCreate(BaseModel):
    legal_business_name: str = Field(min_length=1, max_length=255)
    operating_name: Optional[str] = Field(default=None, max_length=255)
    province: ProvinceCode
    country: str = Field(default="Canada")
    timezone: str = Field(default="America/Toronto")
    primary_location: Optional[str] = Field(default=None)
    business_email: Optional[EmailStr] = None
    business_phone: Optional[str] = Field(default=None, max_length=50)
    preferred_notification_channels: Optional[dict] = Field(default=None)


class BusinessProfileResponse(BaseModel):
    id: int
    tenant_id: int
    legal_business_name: str
    operating_name: Optional[str]
    province: str
    country: str
    timezone: str
    primary_location: Optional[str]
    business_email: Optional[str]
    business_phone: Optional[str]
    preferred_notification_channels: Optional[dict]
    is_confirmed: bool
    created_at: datetime
    updated_at: datetime
    activated_compliance_rules: list[str]  # List of ComplianceRuleCode values
    
    class Config:
        from_attributes = True


# ========== Stage 2: Owner & Roles ==========

class OwnerConfirmationRequest(BaseModel):
    responsibility_disclaimer_accepted: bool = Field(description="Must be True to confirm owner role")


class OwnerConfirmationResponse(BaseModel):
    user_id: int
    tenant_id: int
    confirmed_at: datetime
    responsibility_disclaimer_accepted: bool
    
    class Config:
        from_attributes = True


class RoleTemplateResponse(BaseModel):
    id: int
    tenant_id: int
    name: str
    created_at: datetime
    updated_at: datetime
    
    class Config:
        from_attributes = True


class RoleCreate(BaseModel):
    name: str = Field(min_length=1, max_length=100)
    permission_codes: Optional[list[str]] = Field(default=None)


class RoleResponse(BaseModel):
    id: int
    tenant_id: int
    name: str
    created_at: datetime
    updated_at: datetime
    
    class Config:
        from_attributes = True


class TeamInvitationCreate(BaseModel):
    email: EmailStr
    role_id: Optional[int] = None


class TeamInvitationResponse(BaseModel):
    id: int
    tenant_id: int
    invited_by_user_id: int
    email: str
    role_id: Optional[int]
    expires_at: datetime
    accepted_at: Optional[datetime]
    created_at: datetime
    
    class Config:
        from_attributes = True


class AcceptInvitationRequest(BaseModel):
    token: str
    password: str
    accept_privacy_policy: bool = Field(description="Must be True")
    accept_terms_of_service: bool = Field(description="Must be True")
    email_enabled: bool = Field(default=True)
    sms_enabled: bool = Field(default=False)
    marketing_email_consent: bool = Field(default=False)
    hr_policy_acknowledgements: List[int] = Field(default=[], description="List of HR policy IDs to acknowledge")

