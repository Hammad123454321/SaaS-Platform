"""
Task Dependencies Service

Business logic for task dependency management operations.
All operations are tenant-isolated.
"""
import logging
from typing import Optional, List, Dict, Any

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import Task
from app.models.tasks import TaskDependency

logger = logging.getLogger(__name__)


# ========== Dependency Operations ==========
async def create_dependency(
    tenant_id: str,
    task_id: str,
    depends_on_task_id: str,
    dependency_type: str = "blocks"
) -> TaskDependency:
    """Create a task dependency."""
    # Verify both tasks exist and belong to tenant
    task = await Task.find_one(
        Task.id == PydanticObjectId(task_id),
        Task.tenant_id == tenant_id
    )
    depends_on = await Task.find_one(
        Task.id == PydanticObjectId(depends_on_task_id),
        Task.tenant_id == tenant_id
    )
    
    if not task:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task not found"
        )
    
    if not depends_on:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Dependency task not found"
        )
    
    if task_id == depends_on_task_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Task cannot depend on itself"
        )
    
    # Check for circular dependencies
    if await _would_create_circular_dependency(tenant_id, task_id, depends_on_task_id):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="This dependency would create a circular reference"
        )
    
    # Check if dependency already exists
    existing = await TaskDependency.find_one(
        TaskDependency.tenant_id == tenant_id,
        TaskDependency.task_id == task_id,
        TaskDependency.depends_on_task_id == depends_on_task_id
    )
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Dependency already exists"
        )
    
    dependency = TaskDependency(
        tenant_id=tenant_id,
        task_id=task_id,
        depends_on_task_id=depends_on_task_id,
        dependency_type=dependency_type
    )
    await dependency.insert()
    return dependency


async def _would_create_circular_dependency(
    tenant_id: str,
    task_id: str,
    depends_on_task_id: str
) -> bool:
    """Check if adding this dependency would create a circular reference."""
    # If depends_on_task_id depends on task_id (directly or indirectly), it's circular
    visited = set()
    to_check = [depends_on_task_id]
    
    while to_check:
        current_task_id = to_check.pop()
        if current_task_id == task_id:
            return True
        
        if current_task_id in visited:
            continue
        visited.add(current_task_id)
        
        # Get all tasks that current_task depends on
        dependencies = await TaskDependency.find(
            TaskDependency.tenant_id == tenant_id,
            TaskDependency.task_id == current_task_id
        ).to_list()
        
        for dep in dependencies:
            to_check.append(dep.depends_on_task_id)
    
    return False


async def get_dependency(tenant_id: str, dependency_id: str) -> Optional[TaskDependency]:
    """Get a dependency by ID."""
    return await TaskDependency.find_one(
        TaskDependency.id == PydanticObjectId(dependency_id),
        TaskDependency.tenant_id == tenant_id
    )


async def list_dependencies(
    tenant_id: str,
    task_id: Optional[str] = None,
    depends_on_task_id: Optional[str] = None
) -> List[TaskDependency]:
    """List dependencies."""
    conditions = [TaskDependency.tenant_id == tenant_id]
    
    if task_id:
        conditions.append(TaskDependency.task_id == task_id)
    
    if depends_on_task_id:
        conditions.append(TaskDependency.depends_on_task_id == depends_on_task_id)
    
    return await TaskDependency.find(*conditions).sort(-TaskDependency.created_at).to_list()


async def get_task_dependencies(tenant_id: str, task_id: str) -> List[Task]:
    """Get all tasks that a task depends on (blocking tasks)."""
    dependencies = await list_dependencies(tenant_id, task_id=task_id)
    depends_on_task_ids = [d.depends_on_task_id for d in dependencies]
    
    if not depends_on_task_ids:
        return []
    
    tasks = await Task.find(
        Task.tenant_id == tenant_id,
        {"_id": {"$in": [PydanticObjectId(tid) for tid in depends_on_task_ids]}}
    ).to_list()
    
    return tasks


async def get_tasks_depending_on(tenant_id: str, task_id: str) -> List[Task]:
    """Get all tasks that depend on a specific task."""
    dependencies = await list_dependencies(tenant_id, depends_on_task_id=task_id)
    dependent_task_ids = [d.task_id for d in dependencies]
    
    if not dependent_task_ids:
        return []
    
    tasks = await Task.find(
        Task.tenant_id == tenant_id,
        {"_id": {"$in": [PydanticObjectId(tid) for tid in dependent_task_ids]}}
    ).to_list()
    
    return tasks


async def delete_dependency(tenant_id: str, dependency_id: str) -> None:
    """Delete a dependency."""
    dependency = await get_dependency(tenant_id, dependency_id)
    if not dependency:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Dependency not found"
        )
    
    await dependency.delete()


async def delete_task_dependencies(tenant_id: str, task_id: str) -> None:
    """Delete all dependencies for a task (both as dependent and as dependency)."""
    dependencies = await TaskDependency.find(
        TaskDependency.tenant_id == tenant_id,
        {"$or": [
            {"task_id": task_id},
            {"depends_on_task_id": task_id}
        ]}
    ).to_list()
    
    for dep in dependencies:
        await dep.delete()


async def check_dependency_blocking(tenant_id: str, task_id: str) -> Dict[str, Any]:
    """Check if a task is blocked by dependencies."""
    blocking_tasks = await get_task_dependencies(tenant_id, task_id)
    
    # Check which blocking tasks are not completed
    incomplete_blockers = [
        t for t in blocking_tasks
        if t.completion_percentage < 100
    ]
    
    is_blocked = len(incomplete_blockers) > 0
    
    return {
        "is_blocked": is_blocked,
        "blocking_tasks": [
            {
                "id": str(t.id),
                "title": t.title,
                "completion_percentage": t.completion_percentage
            }
            for t in blocking_tasks
        ],
        "incomplete_blockers": [
            {
                "id": str(t.id),
                "title": t.title,
                "completion_percentage": t.completion_percentage
            }
            for t in incomplete_blockers
        ]
    }
