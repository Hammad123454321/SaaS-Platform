from pydantic import BaseModel, EmailStr, Field
from typing import List


class SignupRequest(BaseModel):
    tenant_name: str = Field(min_length=2, max_length=255)
    email: EmailStr
    password: str
    # Stage 0: Policy acceptance and communication preferences
    accept_privacy_policy: bool = Field(description="Must be True to register")
    accept_terms_of_service: bool = Field(description="Must be True to register")
    email_enabled: bool = Field(default=True, description="Email communication preference")
    sms_enabled: bool = Field(default=False, description="SMS communication preference")
    marketing_email_consent: bool = Field(default=False, description="CASL marketing email consent")


class LoginRequest(BaseModel):
    email: EmailStr
    password: str


class TokenResponse(BaseModel):
    access_token: str
    refresh_token: str
    token_type: str = "bearer"


class RefreshRequest(BaseModel):
    refresh_token: str


class PasswordResetRequest(BaseModel):
    email: EmailStr


class PasswordResetConfirm(BaseModel):
    token: str
    new_password: str


class ImpersonateRequest(BaseModel):
    target_user_id: int
    reason: str | None = None

