"""Schemas for Stages 4 and 5."""
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime

from app.models.compliance import PayrollType, PaySchedule, WSIBClass, HRPolicyType
from app.models.workflows import OnboardingTaskSource


# ========== Stage 4: Compliance Confirmations ==========

class PrivacyWordingConfirm(BaseModel):
    wording_type: str = Field(description="privacy_policy or casl")


class FinancialSetupCreate(BaseModel):
    payroll_type: Optional[PayrollType] = None
    pay_schedule: Optional[PaySchedule] = None
    wsib_class: Optional[WSIBClass] = None


class FinancialSetupResponse(BaseModel):
    id: str
    tenant_id: str
    payroll_type: Optional[str]
    pay_schedule: Optional[str]
    wsib_class: Optional[str]
    is_confirmed: bool
    confirmed_at: Optional[datetime]
    created_at: datetime
    updated_at: datetime
    
    class Config:
        from_attributes = True


class FinancialSetupConfirm(BaseModel):
    confirm: bool = Field(description="Must be True to confirm")


class HRPolicyResponse(BaseModel):
    id: int
    policy_type: str
    title: str
    content: str
    is_required: bool
    version: str
    
    class Config:
        from_attributes = True


class PolicyAcknowledgementRequest(BaseModel):
    policy_ids: List[int] = Field(description="List of HR policy IDs to acknowledge")


# ========== Stage 5: Tasks & Workflows ==========

class TaskTemplateCreate(BaseModel):
    template_name: str = Field(min_length=1, max_length=255)
    template_type: str = Field(description="incident, safety, or custom")
    title: str = Field(min_length=1, max_length=255)
    description: Optional[str] = None
    priority: Optional[str] = None
    status: Optional[str] = None
    is_locked: bool = False
    metadata: Optional[dict] = None


class TaskTemplateResponse(BaseModel):
    id: int
    tenant_id: int
    template_name: str
    template_type: str
    title: str
    description: Optional[str]
    priority: Optional[str]
    status: Optional[str]
    is_locked: bool
    metadata: Optional[dict]
    created_at: datetime
    updated_at: datetime
    
    class Config:
        from_attributes = True


class OnboardingTaskResponse(BaseModel):
    id: int
    tenant_id: int
    task_id: Optional[int]
    source: str
    source_id: Optional[str]
    title: str
    description: Optional[str]
    is_required: bool
    is_completed: bool
    completed_at: Optional[datetime]
    due_date: Optional[datetime]
    assigned_to_user_id: Optional[int]
    created_at: datetime
    
    class Config:
        from_attributes = True


class CreateTaskFromTemplateRequest(BaseModel):
    template_id: int
    project_id: int
    title: Optional[str] = None
    description: Optional[str] = None


class EscalationRuleCreate(BaseModel):
    rule_name: str
    trigger_condition: str = Field(description="overdue_required_task or not_completed_by_due_date")
    escalation_delay_hours: int = Field(default=24)
    notify_roles: Optional[List[str]] = Field(default=None, description="List of roles to notify: owner, manager")


class EscalationRuleResponse(BaseModel):
    id: int
    tenant_id: int
    rule_name: str
    trigger_condition: str
    escalation_delay_hours: int
    notify_roles: Optional[List[str]]
    is_active: bool
    created_at: datetime
    updated_at: datetime
    
    class Config:
        from_attributes = True

