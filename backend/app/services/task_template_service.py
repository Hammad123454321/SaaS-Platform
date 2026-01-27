"""Task Template Service - Stage 5

Tenant-configurable task templates for incidents and safety.
"""
from typing import List, Optional

from beanie import PydanticObjectId

from app.models.workflows import TaskTemplate
from app.models.tasks import Task, Project, TaskStatus
from app.models.user import User


async def create_task_template(
    tenant_id: str,
    template_name: str,
    template_type: str,
    title: str,
    description: Optional[str] = None,
    priority: Optional[str] = None,
    status: Optional[str] = None,
    is_locked: bool = False,
    metadata: Optional[dict] = None
) -> TaskTemplate:
    """Create a new task template."""
    template = TaskTemplate(
        tenant_id=tenant_id,
        template_name=template_name,
        template_type=template_type,
        title=title,
        description=description,
        priority=priority,
        status=status,
        is_locked=is_locked,
        metadata=metadata or {}
    )
    await template.insert()
    return template


async def get_task_templates(
    tenant_id: str,
    template_type: Optional[str] = None
) -> List[TaskTemplate]:
    """Get task templates for tenant."""
    conditions = [TaskTemplate.tenant_id == tenant_id]
    
    if template_type:
        conditions.append(TaskTemplate.template_type == template_type)
    
    templates = await TaskTemplate.find(*conditions).to_list()
    return templates


async def update_task_template(
    template_id: str,
    tenant_id: str,
    **updates
) -> TaskTemplate:
    """Update a task template."""
    template = await TaskTemplate.find_one(
        TaskTemplate.id == PydanticObjectId(template_id),
        TaskTemplate.tenant_id == tenant_id
    )
    
    if not template:
        raise ValueError("Template not found")
    
    if template.is_locked:
        raise ValueError("Cannot update locked template")
    
    # Update fields
    for key, value in updates.items():
        if hasattr(template, key) and value is not None:
            setattr(template, key, value)
    
    await template.save()
    return template


async def delete_task_template(template_id: str, tenant_id: str) -> bool:
    """Delete a task template."""
    template = await TaskTemplate.find_one(
        TaskTemplate.id == PydanticObjectId(template_id),
        TaskTemplate.tenant_id == tenant_id
    )
    
    if not template:
        return False
    
    if template.is_locked:
        raise ValueError("Cannot delete locked template")
    
    await template.delete()
    return True


async def create_task_from_template(
    template_id: str,
    tenant_id: str,
    project_id: str,
    created_by_user_id: str,
    **override_fields
) -> Task:
    """Create a Task from a template."""
    template = await TaskTemplate.find_one(
        TaskTemplate.id == PydanticObjectId(template_id),
        TaskTemplate.tenant_id == tenant_id
    )
    
    if not template:
        raise ValueError("Template not found")
    
    # Get default status
    default_status = await TaskStatus.find_one(TaskStatus.tenant_id == tenant_id)
    
    if not default_status:
        raise ValueError("No task status found")
    
    # Create task from template
    task = Task(
        tenant_id=tenant_id,
        project_id=project_id,
        title=override_fields.get("title", template.title),
        description=override_fields.get("description", template.description),
        status_id=str(default_status.id),
        created_by=created_by_user_id
    )
    
    await task.insert()
    return task
