"""Task Template Service - Stage 5

Tenant-configurable task templates for incidents and safety.
"""
from typing import List, Optional
from sqlmodel import Session, select

from app.models.workflows import TaskTemplate
from app.models.tasks import Task, Project, TaskStatus
from app.models.user import User


def create_task_template(
    session: Session,
    tenant_id: int,
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
    session.add(template)
    session.commit()
    session.refresh(template)
    return template


def get_task_templates(
    session: Session,
    tenant_id: int,
    template_type: Optional[str] = None
) -> List[TaskTemplate]:
    """Get task templates for tenant."""
    query = select(TaskTemplate).where(TaskTemplate.tenant_id == tenant_id)
    
    if template_type:
        query = query.where(TaskTemplate.template_type == template_type)
    
    templates = session.exec(query).all()
    return list(templates)


def update_task_template(
    session: Session,
    template_id: int,
    tenant_id: int,
    **updates
) -> TaskTemplate:
    """Update a task template."""
    template = session.exec(
        select(TaskTemplate).where(
            TaskTemplate.id == template_id,
            TaskTemplate.tenant_id == tenant_id
        )
    ).first()
    
    if not template:
        raise ValueError("Template not found")
    
    if template.is_locked:
        raise ValueError("Cannot update locked template")
    
    # Update fields
    for key, value in updates.items():
        if hasattr(template, key) and value is not None:
            setattr(template, key, value)
    
    session.add(template)
    session.commit()
    session.refresh(template)
    return template


def delete_task_template(session: Session, template_id: int, tenant_id: int) -> bool:
    """Delete a task template."""
    template = session.exec(
        select(TaskTemplate).where(
            TaskTemplate.id == template_id,
            TaskTemplate.tenant_id == tenant_id
        )
    ).first()
    
    if not template:
        return False
    
    if template.is_locked:
        raise ValueError("Cannot delete locked template")
    
    session.delete(template)
    session.commit()
    return True


def create_task_from_template(
    session: Session,
    template_id: int,
    tenant_id: int,
    project_id: int,
    created_by_user_id: int,
    **override_fields
) -> Task:
    """Create a Task from a template."""
    template = session.exec(
        select(TaskTemplate).where(
            TaskTemplate.id == template_id,
            TaskTemplate.tenant_id == tenant_id
        )
    ).first()
    
    if not template:
        raise ValueError("Template not found")
    
    # Get default status
    default_status = session.exec(
        select(TaskStatus).where(
            TaskStatus.tenant_id == tenant_id
        ).limit(1)
    ).first()
    
    if not default_status:
        raise ValueError("No task status found")
    
    # Create task from template
    task = Task(
        tenant_id=tenant_id,
        project_id=project_id,
        title=override_fields.get("title", template.title),
        description=override_fields.get("description", template.description),
        status_id=default_status.id,
        created_by=created_by_user_id
    )
    
    # Apply template data if any
    if template.template_data:
        # Can store template-specific data here
        pass
    
    session.add(task)
    session.commit()
    session.refresh(task)
    return task

