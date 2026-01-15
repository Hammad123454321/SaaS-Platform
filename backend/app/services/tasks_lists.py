"""
Task Lists Service

Business logic for task list management operations.
All operations are tenant-isolated.
"""
import logging
from typing import Optional, List, Dict, Any

from sqlmodel import Session, select, and_
from fastapi import HTTPException, status

from app.models import Project, TaskList, Task

logger = logging.getLogger(__name__)


# ========== Task List Operations ==========
def create_task_list(
    session: Session,
    tenant_id: int,
    project_id: int,
    list_data: Dict[str, Any]
) -> TaskList:
    """Create a new task list for a project."""
    # Verify project exists and belongs to tenant
    project = session.get(Project, project_id)
    if not project or project.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Project not found"
        )
    
    # Get max display_order for this project
    max_order = session.exec(
        select(TaskList.display_order)
        .where(
            and_(
                TaskList.tenant_id == tenant_id,
                TaskList.project_id == project_id
            )
        )
        .order_by(TaskList.display_order.desc())
        .limit(1)
    ).first()
    
    task_list = TaskList(
        tenant_id=tenant_id,
        project_id=project_id,
        name=list_data.get("name", ""),
        description=list_data.get("description"),
        display_order=(max_order + 1) if max_order is not None else 0,
    )
    session.add(task_list)
    session.commit()
    session.refresh(task_list)
    return task_list


def get_task_list(session: Session, tenant_id: int, list_id: int) -> Optional[TaskList]:
    """Get a task list by ID."""
    return session.exec(
        select(TaskList).where(
            and_(
                TaskList.id == list_id,
                TaskList.tenant_id == tenant_id
            )
        )
    ).first()


def list_task_lists(
    session: Session,
    tenant_id: int,
    project_id: Optional[int] = None
) -> List[TaskList]:
    """List all task lists for a tenant/project."""
    query = select(TaskList).where(TaskList.tenant_id == tenant_id)
    
    if project_id:
        query = query.where(TaskList.project_id == project_id)
    
    return list(session.exec(query.order_by(TaskList.display_order.asc(), TaskList.created_at.desc())).all())


def update_task_list(
    session: Session,
    tenant_id: int,
    list_id: int,
    updates: Dict[str, Any]
) -> TaskList:
    """Update a task list."""
    task_list = get_task_list(session, tenant_id, list_id)
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
    
    session.add(task_list)
    session.commit()
    session.refresh(task_list)
    return task_list


def delete_task_list(session: Session, tenant_id: int, list_id: int) -> None:
    """Delete a task list (tasks will have task_list_id set to None)."""
    task_list = get_task_list(session, tenant_id, list_id)
    if not task_list:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task list not found"
        )
    
    # Remove task_list_id from all tasks in this list
    tasks = session.exec(
        select(Task).where(
            and_(
                Task.tenant_id == tenant_id,
                Task.task_list_id == list_id
            )
        )
    ).all()
    
    for task in tasks:
        task.task_list_id = None
        session.add(task)
    
    session.delete(task_list)
    session.commit()


def reorder_task_lists(
    session: Session,
    tenant_id: int,
    project_id: int,
    list_orders: List[Dict[str, int]]
) -> List[TaskList]:
    """Reorder task lists in a project."""
    # Verify project exists
    project = session.get(Project, project_id)
    if not project or project.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Project not found"
        )
    
    # Update display orders
    for order_data in list_orders:
        list_id = order_data.get("list_id")
        display_order = order_data.get("display_order")
        
        if list_id and display_order is not None:
            task_list = get_task_list(session, tenant_id, list_id)
            if task_list and task_list.project_id == project_id:
                task_list.display_order = display_order
                session.add(task_list)
    
    session.commit()
    
    # Return updated lists
    return list_task_lists(session, tenant_id, project_id)


def get_task_list_tasks(session: Session, tenant_id: int, list_id: int) -> List[Task]:
    """Get all tasks in a task list."""
    task_list = get_task_list(session, tenant_id, list_id)
    if not task_list:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task list not found"
        )
    
    tasks = session.exec(
        select(Task).where(
            and_(
                Task.tenant_id == tenant_id,
                Task.task_list_id == list_id
            )
        )
    ).all()
    
    return list(tasks)


def get_task_list_stats(session: Session, tenant_id: int, list_id: int) -> Dict[str, Any]:
    """Get statistics for a task list."""
    tasks = get_task_list_tasks(session, tenant_id, list_id)
    
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





