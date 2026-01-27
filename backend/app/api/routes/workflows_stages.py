"""API routes for Stage 5 - Tasks & Workflows."""
from fastapi import APIRouter, Depends, HTTPException, status
from typing import List
from beanie import PydanticObjectId

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
async def create_template(
    payload: TaskTemplateCreate,
    current_user: User = Depends(get_current_user),
) -> TaskTemplateResponse:
    """Create a task template."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can create task templates."
        )
    
    template = await create_task_template(
        tenant_id=tenant_id,
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
async def list_templates(
    template_type: str = None,
    current_user: User = Depends(get_current_user),
) -> List[TaskTemplateResponse]:
    """List task templates for tenant."""
    tenant_id = str(current_user.tenant_id)
    templates = await get_task_templates(tenant_id, template_type)
    return [TaskTemplateResponse(**t.model_dump()) for t in templates]


@router.put("/templates/{template_id}", response_model=TaskTemplateResponse)
async def update_template(
    template_id: str,
    payload: TaskTemplateCreate,
    current_user: User = Depends(get_current_user),
) -> TaskTemplateResponse:
    """Update a task template."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can update task templates."
        )
    
    updates = payload.model_dump(exclude_unset=True)
    template = await update_task_template(
        template_id=template_id,
        tenant_id=tenant_id,
        **updates
    )
    
    return TaskTemplateResponse(**template.model_dump())


@router.delete("/templates/{template_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_template(
    template_id: str,
    current_user: User = Depends(get_current_user),
) -> None:
    """Delete a task template."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can delete task templates."
        )
    
    await delete_task_template(template_id, tenant_id)


@router.post("/templates/{template_id}/create-task")
async def create_task_from_template_endpoint(
    template_id: str,
    payload: CreateTaskFromTemplateRequest,
    current_user: User = Depends(get_current_user),
) -> dict:
    """Create a Task from a template."""
    tenant_id = str(current_user.tenant_id)
    
    task = await create_task_from_template(
        template_id=template_id,
        tenant_id=tenant_id,
        project_id=payload.project_id,
        created_by_user_id=str(current_user.id),
        title=payload.title,
        description=payload.description
    )
    
    return {
        "status": "success",
        "task_id": str(task.id),
        "message": "Task created from template successfully."
    }


# ========== Stage 5: Onboarding Tasks ==========

@router.post("/onboarding-tasks/generate")
async def generate_onboarding_tasks(
    assigned_to_user_id: str = None,
    current_user: User = Depends(get_current_user),
) -> dict:
    """Generate onboarding tasks for tenant."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can generate onboarding tasks."
        )
    
    tasks = await generate_onboarding_tasks_for_tenant(
        tenant_id=tenant_id,
        assigned_to_user_id=assigned_to_user_id or str(current_user.id)
    )
    
    return {
        "status": "success",
        "tasks_generated": len(tasks),
        "tasks": [OnboardingTaskResponse(**t.model_dump()) for t in tasks]
    }


@router.get("/onboarding-tasks", response_model=List[OnboardingTaskResponse])
async def list_onboarding_tasks(
    current_user: User = Depends(get_current_user),
) -> List[OnboardingTaskResponse]:
    """List onboarding tasks for tenant."""
    tenant_id = str(current_user.tenant_id)
    tasks = await OnboardingTask.find(
        OnboardingTask.tenant_id == tenant_id
    ).to_list()
    return [OnboardingTaskResponse(**t.model_dump()) for t in tasks]


@router.post("/onboarding-tasks/{task_id}/create-task")
async def create_task_from_onboarding_task_endpoint(
    task_id: str,
    project_id: str,
    current_user: User = Depends(get_current_user),
) -> dict:
    """Create an actual Task from an OnboardingTask."""
    tenant_id = str(current_user.tenant_id)
    
    onboarding_task = await OnboardingTask.get(PydanticObjectId(task_id))
    if not onboarding_task or onboarding_task.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Onboarding task not found."
        )
    
    task = await create_task_from_onboarding_task(
        onboarding_task=onboarding_task,
        project_id=project_id,
        created_by_user_id=str(current_user.id)
    )
    
    return {
        "status": "success",
        "task_id": str(task.id),
        "message": "Task created from onboarding task successfully."
    }


# ========== Stage 5: Escalation Rules ==========

@router.post("/escalation-rules", response_model=EscalationRuleResponse, status_code=status.HTTP_201_CREATED)
async def create_escalation_rule(
    payload: EscalationRuleCreate,
    current_user: User = Depends(get_current_user),
) -> EscalationRuleResponse:
    """Create an escalation rule."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can create escalation rules."
        )
    
    rule = EscalationRule(
        tenant_id=tenant_id,
        rule_name=payload.rule_name,
        trigger_condition=payload.trigger_condition,
        escalation_delay_hours=payload.escalation_delay_hours,
        notify_roles=payload.notify_roles or ["owner", "manager"],
        is_active=True
    )
    await rule.insert()
    
    return EscalationRuleResponse(**rule.model_dump())


@router.get("/escalation-rules", response_model=List[EscalationRuleResponse])
async def list_escalation_rules(
    current_user: User = Depends(get_current_user),
) -> List[EscalationRuleResponse]:
    """List escalation rules for tenant."""
    tenant_id = str(current_user.tenant_id)
    rules = await EscalationRule.find(
        EscalationRule.tenant_id == tenant_id
    ).to_list()
    return [EscalationRuleResponse(**r.model_dump()) for r in rules]


@router.post("/escalation-rules/check")
async def check_escalations(
    current_user: User = Depends(get_current_user),
) -> dict:
    """Manually trigger escalation check (usually done by cron)."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can trigger escalation checks."
        )
    
    events = await check_and_escalate_overdue_tasks(tenant_id)
    
    return {
        "status": "success",
        "escalations_triggered": len(events),
        "events": [{"id": str(e.id), "task_id": e.task_id} for e in events]
    }
