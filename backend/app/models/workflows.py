"""
Workflow Models - Stage 5

Task templates, onboarding tasks, escalation rules.
"""
from datetime import datetime
from typing import Optional, TYPE_CHECKING
from enum import StrEnum

from sqlalchemy import JSON, Column, Text
from sqlmodel import SQLModel, Field, Relationship

if TYPE_CHECKING:
    from app.models.user import User
    from app.models.tenant import Tenant
    from app.models.tasks import Task, Project


class TaskTemplate(SQLModel, table=True):
    """Tenant-configurable task templates for incidents and safety."""
    __tablename__ = "task_templates"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    template_name: str = Field(index=True)
    template_type: str = Field(index=True)  # "incident", "safety", "custom"
    title: str = Field()
    description: Optional[str] = Field(default=None, sa_column=Column(Text))
    priority: Optional[str] = Field(default=None)  # "low", "medium", "high", "urgent"
    status: Optional[str] = Field(default=None)  # Default status when created
    is_locked: bool = Field(default=False)  # Locked workflows cannot be modified
    template_data: Optional[dict] = Field(default=None, sa_column=Column(JSON))  # renamed from 'metadata' (reserved)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    tenant: Optional["Tenant"] = Relationship()


class OnboardingTaskSource(StrEnum):
    """Source of auto-generated onboarding task."""
    MODULE = "module"
    INDUSTRY = "industry"
    COMPLIANCE_RULE = "compliance_rule"


class OnboardingTask(SQLModel, table=True):
    """Auto-generated onboarding tasks."""
    __tablename__ = "onboarding_tasks"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    task_id: Optional[int] = Field(default=None, foreign_key="tasks.id", index=True)  # Link to actual Task if created
    source: OnboardingTaskSource = Field(index=True)
    source_id: Optional[str] = Field(default=None)  # e.g., module code, industry code, rule code
    title: str = Field()
    description: Optional[str] = Field(default=None, sa_column=Column(Text))
    is_required: bool = Field(default=True)  # Non-deletable required tasks
    is_completed: bool = Field(default=False)
    completed_at: Optional[datetime] = Field(default=None)
    due_date: Optional[datetime] = Field(default=None)
    assigned_to_user_id: Optional[int] = Field(default=None, foreign_key="users.id", index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    
    tenant: Optional["Tenant"] = Relationship()
    task: Optional["Task"] = Relationship()
    assigned_to: Optional["User"] = Relationship()


class EscalationRule(SQLModel, table=True):
    """Escalation rules for overdue required tasks."""
    __tablename__ = "escalation_rules"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    rule_name: str = Field()
    trigger_condition: str = Field()  # "overdue_required_task", "not_completed_by_due_date"
    escalation_delay_hours: int = Field(default=24)  # Hours after due date before escalation
    notify_roles: Optional[list] = Field(default=None, sa_column=Column(JSON))  # ["owner", "manager"]
    is_active: bool = Field(default=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    tenant: Optional["Tenant"] = Relationship()


class EscalationEvent(SQLModel, table=True):
    """Log of escalation events."""
    __tablename__ = "escalation_events"
    
    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    task_id: int = Field(foreign_key="tasks.id", index=True)
    rule_id: Optional[int] = Field(default=None, foreign_key="escalation_rules.id", index=True)
    escalated_at: datetime = Field(default_factory=datetime.utcnow)
    notified_user_ids: Optional[list] = Field(default=None, sa_column=Column(JSON))
    resolved_at: Optional[datetime] = Field(default=None)
    
    tenant: Optional["Tenant"] = Relationship()
    task: Optional["Task"] = Relationship()
    rule: Optional["EscalationRule"] = Relationship()

