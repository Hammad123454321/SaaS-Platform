"""API routes for Stage 5 - Tasks & Workflows."""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlmodel import Session, select
from typing import List

from app.db import get_session
from app.api.deps import get_current_user
from app.models import User
from app.models.workflows import (
    TaskTemplate,
    OnboardingTask,
    EscalationRule,
)
from app.schemas.compliance_stages import (
    TaskTemplateCreate,
    TaskTemplateResponse,
    OnboardingTaskResponse,
    CreateTaskFromTemplateRequest,
    EscalationRuleCreate,
    EscalationRuleResponse,
)
from app.services.task_template_service import (
    create_task_template,
    get_task_templates,
    update_task_template,
    delete_task_template,
    create_task_from_template,
)
from app.services.task_generation_service import (
    generate_onboarding_tasks_for_tenant,
    create_task_from_onboarding_task,
)
from app.services.escalation_service import (
    create_default_escalation_rule,
    check_and_escalate_overdue_tasks,
)
from app.services.owner_service import is_user_owner
from app.models.tasks import Task, Project

router = APIRouter(prefix="/workflows", tags=["workflows"])


# ========== Stage 5: Task Templates ==========

@router.post("/templates", response_model=TaskTemplateResponse, status_code=status.HTTP_201_CREATED)
def create_template(
    payload: TaskTemplateCreate,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> TaskTemplateResponse:
    """Create a task template."""
    # Only owner can create templates
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can create task templates."
        )
    
    template = create_task_template(
        session=session,
        tenant_id=current_user.tenant_id,
        template_name=payload.template_name,
        template_type=payload.template_type,
        title=payload.title,
        description=payload.description,
        priority=payload.priority,
        status=payload.status,
        is_locked=payload.is_locked,
        metadata=payload.metadata
    )
    
    return TaskTemplateResponse(**template.model_dump())


@router.get("/templates", response_model=List[TaskTemplateResponse])
def list_templates(
    template_type: str = None,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> List[TaskTemplateResponse]:
    """List task templates for tenant."""
    templates = get_task_templates(session, current_user.tenant_id, template_type)
    return [TaskTemplateResponse(**t.model_dump()) for t in templates]


@router.put("/templates/{template_id}", response_model=TaskTemplateResponse)
def update_template(
    template_id: int,
    payload: TaskTemplateCreate,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> TaskTemplateResponse:
    """Update a task template."""
    # Only owner can update templates
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can update task templates."
        )
    
    updates = payload.model_dump(exclude_unset=True)
    template = update_task_template(
        session=session,
        template_id=template_id,
        tenant_id=current_user.tenant_id,
        **updates
    )
    
    return TaskTemplateResponse(**template.model_dump())


@router.delete("/templates/{template_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_template(
    template_id: int,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> None:
    """Delete a task template."""
    # Only owner can delete templates
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can delete task templates."
        )
    
    delete_task_template(session, template_id, current_user.tenant_id)


@router.post("/templates/{template_id}/create-task")
def create_task_from_template_endpoint(
    template_id: int,
    payload: CreateTaskFromTemplateRequest,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> dict:
    """Create a Task from a template."""
    task = create_task_from_template(
        session=session,
        template_id=template_id,
        tenant_id=current_user.tenant_id,
        project_id=payload.project_id,
        created_by_user_id=current_user.id,
        title=payload.title,
        description=payload.description
    )
    
    return {
        "status": "success",
        "task_id": task.id,
        "message": "Task created from template successfully."
    }


# ========== Stage 5: Onboarding Tasks ==========

@router.post("/onboarding-tasks/generate")
def generate_onboarding_tasks(
    assigned_to_user_id: int = None,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> dict:
    """Generate onboarding tasks for tenant."""
    # Only owner can trigger task generation
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can generate onboarding tasks."
        )
    
    tasks = generate_onboarding_tasks_for_tenant(
        session=session,
        tenant_id=current_user.tenant_id,
        assigned_to_user_id=assigned_to_user_id or current_user.id
    )
    
    return {
        "status": "success",
        "tasks_generated": len(tasks),
        "tasks": [OnboardingTaskResponse(**t.model_dump()) for t in tasks]
    }


@router.get("/onboarding-tasks", response_model=List[OnboardingTaskResponse])
def list_onboarding_tasks(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> List[OnboardingTaskResponse]:
    """List onboarding tasks for tenant."""
    tasks = session.exec(
        select(OnboardingTask).where(OnboardingTask.tenant_id == current_user.tenant_id)
    ).all()
    return [OnboardingTaskResponse(**t.model_dump()) for t in tasks]


@router.post("/onboarding-tasks/{task_id}/create-task")
def create_task_from_onboarding_task_endpoint(
    task_id: int,
    project_id: int,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> dict:
    """Create an actual Task from an OnboardingTask."""
    onboarding_task = session.get(OnboardingTask, task_id)
    if not onboarding_task or onboarding_task.tenant_id != current_user.tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Onboarding task not found."
        )
    
    task = create_task_from_onboarding_task(
        session=session,
        onboarding_task=onboarding_task,
        project_id=project_id,
        created_by_user_id=current_user.id
    )
    
    return {
        "status": "success",
        "task_id": task.id,
        "message": "Task created from onboarding task successfully."
    }


# ========== Stage 5: Escalation Rules ==========

@router.post("/escalation-rules", response_model=EscalationRuleResponse, status_code=status.HTTP_201_CREATED)
def create_escalation_rule(
    payload: EscalationRuleCreate,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> EscalationRuleResponse:
    """Create an escalation rule."""
    # Only owner can create escalation rules
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can create escalation rules."
        )
    
    rule = EscalationRule(
        tenant_id=current_user.tenant_id,
        rule_name=payload.rule_name,
        trigger_condition=payload.trigger_condition,
        escalation_delay_hours=payload.escalation_delay_hours,
        notify_roles=payload.notify_roles or ["owner", "manager"],
        is_active=True
    )
    session.add(rule)
    session.commit()
    session.refresh(rule)
    
    return EscalationRuleResponse(**rule.model_dump())


@router.get("/escalation-rules", response_model=List[EscalationRuleResponse])
def list_escalation_rules(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> List[EscalationRuleResponse]:
    """List escalation rules for tenant."""
    rules = session.exec(
        select(EscalationRule).where(EscalationRule.tenant_id == current_user.tenant_id)
    ).all()
    return [EscalationRuleResponse(**r.model_dump()) for r in rules]


@router.post("/escalation-rules/check")
def check_escalations(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> dict:
    """Manually trigger escalation check (usually done by cron)."""
    # Only owner can trigger escalation check
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can trigger escalation checks."
        )
    
    events = check_and_escalate_overdue_tasks(session, current_user.tenant_id)
    
    return {
        "status": "success",
        "escalations_triggered": len(events),
        "events": [{"id": e.id, "task_id": e.task_id} for e in events]
    }

