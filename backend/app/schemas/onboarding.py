from typing import Optional

from pydantic import BaseModel, Field

from app.models.entitlement import ModuleCode
from app.schemas.entitlements import EntitlementRead


class OnboardingCompany(BaseModel):
    name: str
    industry: Optional[str] = None


class OnboardingBranding(BaseModel):
    color: str
    logoUrl: Optional[str] = None


class OnboardingRequest(BaseModel):
    company: OnboardingCompany
    modules: list[ModuleCode] = Field(default_factory=list)
    branding: Optional[OnboardingBranding] = None


class OnboardingResponse(BaseModel):
    status: str
    entitlements: list[EntitlementRead]


class TaskifyOnboardingRequest(BaseModel):
    base_url: str
    api_token: str
    workspace_id: Optional[int] = None
    verify: bool = True


class TaskifyOnboardingResponse(BaseModel):
    status: str
    health: Optional[dict] = None
