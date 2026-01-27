"""
Task Lists Service

Business logic for task list management operations.
All operations are tenant-isolated.
"""
import logging
from typing import Optional, List, Dict, Any

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import Project, TaskList, Task

logger = logging.getLogger(__name__)


# ========== Task List Operations ==========
async def create_task_list(
    tenant_id: str,
    project_id: str,
    list_data: Dict[str, Any]
) -> TaskList:
    """Create a new task list for a project."""
    # Verify project exists and belongs to tenant
    project = await Project.find_one(
        Project.id == PydanticObjectId(project_id),
        Project.tenant_id == tenant_id
    )
    if not project:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Project not found"
        )
    
    # Get max display_order for this project
    existing_lists = await TaskList.find(
        TaskList.tenant_id == tenant_id,
        TaskList.project_id == project_id
    ).to_list()
    
    max_order = max((tl.display_order for tl in existing_lists), default=-1)
    
    task_list = TaskList(
        tenant_id=tenant_id,
        project_id=project_id,
        name=list_data.get("name", ""),
        description=list_data.get("description"),
        display_order=max_order + 1,
    )
    await task_list.insert()
    return task_list


async def get_task_list(tenant_id: str, list_id: str) -> Optional[TaskList]:
    """Get a task list by ID."""
    return await TaskList.find_one(
        TaskList.id == PydanticObjectId(list_id),
        TaskList.tenant_id == tenant_id
    )


async def list_task_lists(
    tenant_id: str,
    project_id: Optional[str] = None
) -> List[TaskList]:
    """List all task lists for a tenant/project."""
    conditions = [TaskList.tenant_id == tenant_id]
    
    if project_id:
        conditions.append(TaskList.project_id == project_id)
    
    return await TaskList.find(*conditions).sort(+TaskList.display_order, -TaskList.created_at).to_list()


async def update_task_list(
    tenant_id: str,
    list_id: str,
    updates: Dict[str, Any]
) -> TaskList:
    """Update a task list."""
    task_list = await get_task_list(tenant_id, list_id)
    if not task_list:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task list not found"
        )
    
    if "name" in updates:
        task_list.name = updates["name"]
    if "description" in updates:
        task_list.description = updates["description"]
    if "display_order" in updates:
        task_list.display_order = updates["display_order"]
    
    await task_list.save()
    return task_list


async def delete_task_list(tenant_id: str, list_id: str) -> None:
    """Delete a task list (tasks will have task_list_id set to None)."""
    task_list = await get_task_list(tenant_id, list_id)
    if not task_list:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task list not found"
        )
    
    # Remove task_list_id from all tasks in this list
    tasks = await Task.find(
        Task.tenant_id == tenant_id,
        Task.task_list_id == list_id
    ).to_list()
    
    for task in tasks:
        task.task_list_id = None
        await task.save()
    
    await task_list.delete()


async def reorder_task_lists(
    tenant_id: str,
    project_id: str,
    list_orders: List[Dict[str, Any]]
) -> List[TaskList]:
    """Reorder task lists in a project."""
    # Verify project exists
    project = await Project.find_one(
        Project.id == PydanticObjectId(project_id),
        Project.tenant_id == tenant_id
    )
    if not project:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Project not found"
        )
    
    # Update display orders
    for order_data in list_orders:
        list_id = order_data.get("list_id")
        display_order = order_data.get("display_order")
        
        if list_id and display_order is not None:
            task_list = await get_task_list(tenant_id, list_id)
            if task_list and task_list.project_id == project_id:
                task_list.display_order = display_order
                await task_list.save()
    
    # Return updated lists
    return await list_task_lists(tenant_id, project_id)


async def get_task_list_tasks(tenant_id: str, list_id: str) -> List[Task]:
    """Get all tasks in a task list."""
    task_list = await get_task_list(tenant_id, list_id)
    if not task_list:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task list not found"
        )
    
    tasks = await Task.find(
        Task.tenant_id == tenant_id,
        Task.task_list_id == list_id
    ).to_list()
    
    return tasks


async def get_task_list_stats(tenant_id: str, list_id: str) -> Dict[str, Any]:
    """Get statistics for a task list."""
    tasks = await get_task_list_tasks(tenant_id, list_id)
    
    total_tasks = len(tasks)
    completed_tasks = sum(1 for t in tasks if t.completion_percentage == 100)
    in_progress_tasks = sum(1 for t in tasks if 0 < t.completion_percentage < 100)
    not_started_tasks = sum(1 for t in tasks if t.completion_percentage == 0)
    
    return {
        "list_id": list_id,
        "total_tasks": total_tasks,
        "completed_tasks": completed_tasks,
        "in_progress_tasks": in_progress_tasks,
        "not_started_tasks": not_started_tasks,
        "completion_rate": (completed_tasks / total_tasks * 100) if total_tasks > 0 else 0,
    }
