"""
Task Dependencies Service

Business logic for task dependency management operations.
All operations are tenant-isolated.
"""
import logging
from typing import Optional, List, Dict, Any

from sqlmodel import Session, select, and_, or_
from fastapi import HTTPException, status

from app.models import Task
from app.models.tasks import TaskDependency

logger = logging.getLogger(__name__)


# ========== Dependency Operations ==========
def create_dependency(
    session: Session,
    tenant_id: int,
    task_id: int,
    depends_on_task_id: int,
    dependency_type: str = "blocks"
) -> TaskDependency:
    """Create a task dependency."""
    # Verify both tasks exist and belong to tenant
    task = session.get(Task, task_id)
    depends_on = session.get(Task, depends_on_task_id)
    
    if not task or task.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task not found"
        )
    
    if not depends_on or depends_on.tenant_id != tenant_id:
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
    if _would_create_circular_dependency(session, tenant_id, task_id, depends_on_task_id):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="This dependency would create a circular reference"
        )
    
    # Check if dependency already exists
    existing = session.exec(
        select(TaskDependency).where(
            and_(
                TaskDependency.tenant_id == tenant_id,
                TaskDependency.task_id == task_id,
                TaskDependency.depends_on_task_id == depends_on_task_id
            )
        )
    ).first()
    
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
    session.add(dependency)
    session.commit()
    session.refresh(dependency)
    return dependency


def _would_create_circular_dependency(
    session: Session,
    tenant_id: int,
    task_id: int,
    depends_on_task_id: int
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
        dependencies = session.exec(
            select(TaskDependency).where(
                and_(
                    TaskDependency.tenant_id == tenant_id,
                    TaskDependency.task_id == current_task_id
                )
            )
        ).all()
        
        for dep in dependencies:
            to_check.append(dep.depends_on_task_id)
    
    return False


def get_dependency(session: Session, tenant_id: int, dependency_id: int) -> Optional[TaskDependency]:
    """Get a dependency by ID."""
    return session.exec(
        select(TaskDependency).where(
            and_(
                TaskDependency.id == dependency_id,
                TaskDependency.tenant_id == tenant_id
            )
        )
    ).first()


def list_dependencies(
    session: Session,
    tenant_id: int,
    task_id: Optional[int] = None,
    depends_on_task_id: Optional[int] = None
) -> List[TaskDependency]:
    """List dependencies."""
    query = select(TaskDependency).where(TaskDependency.tenant_id == tenant_id)
    
    if task_id:
        query = query.where(TaskDependency.task_id == task_id)
    
    if depends_on_task_id:
        query = query.where(TaskDependency.depends_on_task_id == depends_on_task_id)
    
    return list(session.exec(query.order_by(TaskDependency.created_at.desc())).all())


def get_task_dependencies(session: Session, tenant_id: int, task_id: int) -> List[Task]:
    """Get all tasks that a task depends on (blocking tasks)."""
    dependencies = list_dependencies(session, tenant_id, task_id=task_id)
    depends_on_task_ids = [d.depends_on_task_id for d in dependencies]
    
    if not depends_on_task_ids:
        return []
    
    tasks = session.exec(
        select(Task).where(
            and_(
                Task.tenant_id == tenant_id,
                Task.id.in_(depends_on_task_ids)  # type: ignore
            )
        )
    ).all()
    
    return list(tasks)


def get_tasks_depending_on(session: Session, tenant_id: int, task_id: int) -> List[Task]:
    """Get all tasks that depend on a specific task."""
    dependencies = list_dependencies(session, tenant_id, depends_on_task_id=task_id)
    dependent_task_ids = [d.task_id for d in dependencies]
    
    if not dependent_task_ids:
        return []
    
    tasks = session.exec(
        select(Task).where(
            and_(
                Task.tenant_id == tenant_id,
                Task.id.in_(dependent_task_ids)  # type: ignore
            )
        )
    ).all()
    
    return list(tasks)


def delete_dependency(session: Session, tenant_id: int, dependency_id: int) -> None:
    """Delete a dependency."""
    dependency = get_dependency(session, tenant_id, dependency_id)
    if not dependency:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Dependency not found"
        )
    
    session.delete(dependency)
    session.commit()


def delete_task_dependencies(session: Session, tenant_id: int, task_id: int) -> None:
    """Delete all dependencies for a task (both as dependent and as dependency)."""
    dependencies = session.exec(
        select(TaskDependency).where(
            and_(
                TaskDependency.tenant_id == tenant_id,
                or_(
                    TaskDependency.task_id == task_id,
                    TaskDependency.depends_on_task_id == task_id
                )
            )
        )
    ).all()
    
    for dep in dependencies:
        session.delete(dep)
    
    session.commit()


def check_dependency_blocking(session: Session, tenant_id: int, task_id: int) -> Dict[str, Any]:
    """Check if a task is blocked by dependencies."""
    blocking_tasks = get_task_dependencies(session, tenant_id, task_id)
    
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
                "id": t.id,
                "title": t.title,
                "completion_percentage": t.completion_percentage
            }
            for t in blocking_tasks
        ],
        "incomplete_blockers": [
            {
                "id": t.id,
                "title": t.title,
                "completion_percentage": t.completion_percentage
            }
            for t in incomplete_blockers
        ]
    }





