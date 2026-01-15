"""
Project and Task Duplication Service

Handles duplication of projects with all tasks, subtasks, and assignments.
"""
import logging
from datetime import datetime, date
from typing import Dict, Any, List
from copy import deepcopy

from sqlmodel import Session, select, and_
from fastapi import HTTPException, status

from app.models import Project, Task
from app.models.tasks import task_assignments_table
from app.services.tasks import create_project, create_task, get_task
from typing import Optional

logger = logging.getLogger(__name__)


def duplicate_project(
    session: Session,
    tenant_id: int,
    project_id: int,
    new_name: Optional[str] = None,
    created_by: int
) -> Project:
    """Duplicate a project with all tasks, subtasks, and assignments."""
    # Get original project
    original = session.get(Project, project_id)
    if not original or original.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Project not found"
        )
    
    # Create new project
    project_data = {
        "name": new_name or f"{original.name} (Copy)",
        "description": original.description,
        "client_id": original.client_id,
        "budget": original.budget,
        "start_date": original.start_date,
        "deadline": original.deadline,
        "status": original.status
    }
    
    new_project = create_project(session, tenant_id, created_by, project_data)
    
    # Get all tasks from original project
    original_tasks = list(
        session.exec(
            select(Task).where(
                and_(
                    Task.tenant_id == tenant_id,
                    Task.project_id == project_id,
                    Task.parent_id.is_(None)  # Only top-level tasks
                )
            )
        ).all()
    )
    
    # Map old task IDs to new task IDs for subtask relationships
    task_id_map: Dict[int, int] = {}
    
    # Duplicate tasks (top-level first)
    for original_task in original_tasks:
        new_task = _duplicate_task(
            session,
            tenant_id,
            original_task,
            new_project.id,
            created_by,
            None  # No parent for top-level tasks
        )
        task_id_map[original_task.id] = new_task.id
    
    # Duplicate subtasks
    for original_task in original_tasks:
        subtasks = list(
            session.exec(
                select(Task).where(
                    and_(
                        Task.tenant_id == tenant_id,
                        Task.parent_id == original_task.id
                    )
                )
            ).all()
        )
        
        for subtask in subtasks:
            new_parent_id = task_id_map.get(original_task.id)
            if new_parent_id:
                _duplicate_task(
                    session,
                    tenant_id,
                    subtask,
                    new_project.id,
                    created_by,
                    new_parent_id
                )
    
    session.commit()
    session.refresh(new_project)
    return new_project


def _duplicate_task(
    session: Session,
    tenant_id: int,
    original_task: Task,
    new_project_id: int,
    created_by: int,
    new_parent_id: Optional[int]
) -> Task:
    """Duplicate a single task."""
    # Get assignees
    assignee_ids = [a.id for a in (original_task.assignees or [])]
    
    # Create task data
    task_data = {
        "title": original_task.title,
        "description": original_task.description,
        "notes": original_task.notes,
        "status_id": original_task.status_id,
        "priority_id": original_task.priority_id,
        "start_date": original_task.start_date,
        "due_date": original_task.due_date,
        "completion_percentage": original_task.completion_percentage,
        "billing_type": original_task.billing_type,
        "client_can_discuss": original_task.client_can_discuss,
        "assignee_ids": assignee_ids
    }
    
    # Create task
    new_task = create_task(session, tenant_id, created_by, task_data)
    
    # Update project and parent
    new_task.project_id = new_project_id
    if new_parent_id:
        new_task.parent_id = new_parent_id
    
    session.add(new_task)
    session.commit()
    session.refresh(new_task)
    
    return new_task


def duplicate_task(
    session: Session,
    tenant_id: int,
    task_id: int,
    new_title: Optional[str] = None,
    include_subtasks: bool = True,
    created_by: int
) -> Task:
    """Duplicate a task (and optionally its subtasks)."""
    original = get_task(session, tenant_id, task_id)
    if not original:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task not found"
        )
    
    # Duplicate main task
    assignee_ids = [a.id for a in (original.assignees or [])]
    
    task_data = {
        "title": new_title or f"{original.title} (Copy)",
        "description": original.description,
        "notes": original.notes,
        "status_id": original.status_id,
        "priority_id": original.priority_id,
        "project_id": original.project_id,
        "start_date": original.start_date,
        "due_date": original.due_date,
        "completion_percentage": 0,  # Reset completion
        "billing_type": original.billing_type,
        "client_can_discuss": original.client_can_discuss,
        "assignee_ids": assignee_ids
    }
    
    new_task = create_task(session, tenant_id, created_by, task_data)
    
    # Duplicate subtasks if requested
    if include_subtasks:
        subtasks = list(
            session.exec(
                select(Task).where(
                    and_(
                        Task.tenant_id == tenant_id,
                        Task.parent_id == task_id
                    )
                )
            ).all()
        )
        
        task_id_map = {task_id: new_task.id}
        
        for subtask in subtasks:
            new_subtask = _duplicate_task(
                session,
                tenant_id,
                subtask,
                original.project_id,
                created_by,
                new_task.id
            )
            task_id_map[subtask.id] = new_subtask.id
    
    session.commit()
    session.refresh(new_task)
    return new_task

