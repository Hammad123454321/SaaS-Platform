"""
Workflow Models - Stage 5

Task templates, onboarding tasks, escalation rules.
"""
from datetime import datetime
from typing import Optional, List
from enum import StrEnum

from beanie import Document
from pydantic import Field


class TaskTemplate(Document):
    """Tenant-configurable task templates for incidents and safety."""
    
    tenant_id: str = Field(..., index=True)
    template_name: str = Field(..., index=True)
    template_type: str = Field(..., index=True)  # "incident", "safety", "custom"
    title: str
    description: Optional[str] = None
    priority: Optional[str] = None  # "low", "medium", "high", "urgent"
    status: Optional[str] = None  # Default status when created
    is_locked: bool = Field(default=False)  # Locked workflows cannot be modified
    template_data: Optional[dict] = None  # renamed from 'metadata' (reserved)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "task_templates"
        indexes = [
            "tenant_id",
            "template_name",
            "template_type",
        ]


class OnboardingTaskSource(StrEnum):
    """Source of auto-generated onboarding task."""
    MODULE = "module"
    INDUSTRY = "industry"
    COMPLIANCE_RULE = "compliance_rule"


class OnboardingTask(Document):
    """Auto-generated onboarding tasks."""
    
    tenant_id: str = Field(..., index=True)
    task_id: Optional[str] = Field(default=None, index=True)  # Link to actual Task if created
    source: OnboardingTaskSource = Field(..., index=True)
    source_id: Optional[str] = None  # e.g., module code, industry code, rule code
    title: str
    description: Optional[str] = None
    is_required: bool = Field(default=True)  # Non-deletable required tasks
    is_completed: bool = Field(default=False)
    completed_at: Optional[datetime] = None
    due_date: Optional[datetime] = None
    assigned_to_user_id: Optional[str] = Field(default=None, index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "onboarding_tasks"
        indexes = [
            "tenant_id",
            "task_id",
            "source",
            "assigned_to_user_id",
        ]


class EscalationRule(Document):
    """Escalation rules for overdue required tasks."""
    
    tenant_id: str = Field(..., index=True)
    rule_name: str
    trigger_condition: str  # "overdue_required_task", "not_completed_by_due_date"
    escalation_delay_hours: int = Field(default=24)  # Hours after due date before escalation
    notify_roles: Optional[List[str]] = None  # ["owner", "manager"]
    is_active: bool = Field(default=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "escalation_rules"
        indexes = [
            "tenant_id",
        ]


class EscalationEvent(Document):
    """Log of escalation events."""
    
    tenant_id: str = Field(..., index=True)
    task_id: str = Field(..., index=True)
    rule_id: Optional[str] = Field(default=None, index=True)
    escalated_at: datetime = Field(default_factory=datetime.utcnow)
    notified_user_ids: Optional[List[str]] = None
    resolved_at: Optional[datetime] = None

    class Settings:
        name = "escalation_events"
        indexes = [
            "tenant_id",
            "task_id",
            "rule_id",
        ]
