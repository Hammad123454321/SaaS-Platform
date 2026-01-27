"""
Project and Task Duplication Service

Handles duplication of projects with all tasks, subtasks, and assignments.
"""
import logging
from datetime import datetime, date
from typing import Dict, Any, List, Optional

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import Project, Task
from app.services.tasks import create_project, create_task, get_task

logger = logging.getLogger(__name__)


async def duplicate_project(
    tenant_id: str,
    project_id: str,
    created_by: str,
    new_name: Optional[str] = None
) -> Project:
    """Duplicate a project with all tasks, subtasks, and assignments."""
    # Get original project
    original = await Project.find_one(
        Project.id == PydanticObjectId(project_id),
        Project.tenant_id == tenant_id
    )
    if not original:
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
    
    new_project = await create_project(tenant_id, created_by, project_data)
    
    # Get all tasks from original project (top-level first)
    original_tasks = await Task.find(
        Task.tenant_id == tenant_id,
        Task.project_id == project_id,
        Task.parent_id == None
    ).to_list()
    
    # Map old task IDs to new task IDs for subtask relationships
    task_id_map: Dict[str, str] = {}
    
    # Duplicate tasks (top-level first)
    for original_task in original_tasks:
        new_task = await _duplicate_task(
            tenant_id,
            original_task,
            str(new_project.id),
            created_by,
            None  # No parent for top-level tasks
        )
        task_id_map[str(original_task.id)] = str(new_task.id)
    
    # Duplicate subtasks
    for original_task in original_tasks:
        subtasks = await Task.find(
            Task.tenant_id == tenant_id,
            Task.parent_id == str(original_task.id)
        ).to_list()
        
        for subtask in subtasks:
            new_parent_id = task_id_map.get(str(original_task.id))
            if new_parent_id:
                await _duplicate_task(
                    tenant_id,
                    subtask,
                    str(new_project.id),
                    created_by,
                    new_parent_id
                )
    
    return new_project


async def _duplicate_task(
    tenant_id: str,
    original_task: Task,
    new_project_id: str,
    created_by: str,
    new_parent_id: Optional[str]
) -> Task:
    """Duplicate a single task."""
    # Get assignees
    assignee_ids = original_task.assignee_ids or []
    
    # Create task data
    task_data = {
        "title": original_task.title,
        "description": original_task.description,
        "notes": original_task.notes,
        "project_id": new_project_id,
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
    new_task = await create_task(tenant_id, created_by, task_data)
    
    # Update parent if needed
    if new_parent_id:
        new_task.parent_id = new_parent_id
        await new_task.save()
    
    return new_task


async def duplicate_task_with_subtasks(
    tenant_id: str,
    task_id: str,
    created_by: str,
    new_title: Optional[str] = None,
    include_subtasks: bool = True
) -> Task:
    """Duplicate a task (and optionally its subtasks)."""
    original = await get_task(tenant_id, task_id)
    if not original:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task not found"
        )
    
    # Duplicate main task
    assignee_ids = original.assignee_ids or []
    
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
    
    new_task = await create_task(tenant_id, created_by, task_data)
    
    # Duplicate subtasks if requested
    if include_subtasks:
        subtasks = await Task.find(
            Task.tenant_id == tenant_id,
            Task.parent_id == task_id
        ).to_list()
        
        for subtask in subtasks:
            await _duplicate_task(
                tenant_id,
                subtask,
                original.project_id,
                created_by,
                str(new_task.id)
            )
    
    return new_task
