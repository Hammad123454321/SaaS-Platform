"""
Subtask Management Service

Handles subtask CRUD operations, status inheritance, and parent task completion logic.
"""
import logging
from datetime import datetime
from typing import Optional, List, Dict, Any

from sqlmodel import Session, select, and_
from fastapi import HTTPException, status

from app.models import Task, TaskStatus, TaskStatusCategory
from app.services.tasks import get_task, update_task

logger = logging.getLogger(__name__)


def create_subtask(
    session: Session,
    tenant_id: int,
    parent_task_id: int,
    subtask_data: Dict[str, Any],
    created_by: int
) -> Task:
    """Create a new subtask."""
    # Verify parent task exists
    parent = get_task(session, tenant_id, parent_task_id)
    if not parent:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Parent task not found"
        )
    
    # Ensure subtask has required fields
    if "status_id" not in subtask_data:
        # Use parent's status or default
        subtask_data["status_id"] = parent.status_id
    
    if "project_id" not in subtask_data:
        subtask_data["project_id"] = parent.project_id
    
    # Create subtask
    subtask = Task(
        tenant_id=tenant_id,
        parent_id=parent_task_id,
        created_by=created_by,
        **subtask_data
    )
    session.add(subtask)
    session.commit()
    session.refresh(subtask)
    
    # Update parent task completion if needed
    _update_parent_completion(session, tenant_id, parent_task_id)
    
    return subtask


def get_subtask(session: Session, tenant_id: int, subtask_id: int) -> Optional[Task]:
    """Get a subtask by ID."""
    return session.exec(
        select(Task).where(
            and_(
                Task.id == subtask_id,
                Task.tenant_id == tenant_id,
                Task.parent_id.isnot(None)  # Must be a subtask
            )
        )
    ).first()


def list_subtasks(session: Session, tenant_id: int, parent_task_id: int) -> List[Task]:
    """List all subtasks for a parent task."""
    return list(
        session.exec(
            select(Task).where(
                and_(
                    Task.tenant_id == tenant_id,
                    Task.parent_id == parent_task_id
                )
            ).order_by(Task.created_at.asc())
        ).all()
    )


def update_subtask(
    session: Session,
    tenant_id: int,
    subtask_id: int,
    updates: Dict[str, Any]
) -> Task:
    """Update a subtask."""
    subtask = get_subtask(session, tenant_id, subtask_id)
    if not subtask:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Subtask not found"
        )
    
    # Update fields
    for key, value in updates.items():
        if hasattr(subtask, key):
            setattr(subtask, key, value)
    
    subtask.updated_at = datetime.utcnow()
    session.add(subtask)
    session.commit()
    session.refresh(subtask)
    
    # Update parent task completion
    if subtask.parent_id:
        _update_parent_completion(session, tenant_id, subtask.parent_id)
    
    # Handle status inheritance if status changed
    if "status_id" in updates:
        _apply_status_inheritance(session, tenant_id, subtask_id, updates["status_id"])
    
    return subtask


def delete_subtask(session: Session, tenant_id: int, subtask_id: int) -> None:
    """Delete a subtask."""
    subtask = get_subtask(session, tenant_id, subtask_id)
    if not subtask:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Subtask not found"
        )
    
    parent_id = subtask.parent_id
    session.delete(subtask)
    session.commit()
    
    # Update parent task completion
    if parent_id:
        _update_parent_completion(session, tenant_id, parent_id)


def _apply_status_inheritance(
    session: Session,
    tenant_id: int,
    subtask_id: int,
    new_status_id: int
) -> None:
    """
    Apply status inheritance: if subtask status is 'done', 
    parent task status may be updated based on all subtasks.
    """
    subtask = get_subtask(session, tenant_id, subtask_id)
    if not subtask or not subtask.parent_id:
        return
    
    # Get new status
    new_status = session.get(TaskStatus, new_status_id)
    if not new_status:
        return
    
    # If subtask is marked as done, check if all subtasks are done
    if new_status.category == TaskStatusCategory.DONE:
        parent = get_task(session, tenant_id, subtask.parent_id)
        if not parent:
            return
        
        # Get all subtasks
        subtasks = list_subtasks(session, tenant_id, parent.id)
        
        # Check if all subtasks are done
        all_done = True
        for st in subtasks:
            if st.status_id != new_status_id:
                status_obj = session.get(TaskStatus, st.status_id)
                if status_obj and status_obj.category != TaskStatusCategory.DONE:
                    all_done = False
                    break
        
        # If all subtasks are done, update parent status to done
        if all_done and subtasks:
            done_status = session.exec(
                select(TaskStatus).where(
                    and_(
                        TaskStatus.tenant_id == tenant_id,
                        TaskStatus.category == TaskStatusCategory.DONE
                    )
                )
            ).first()
            
            if done_status:
                update_task(session, tenant_id, parent.id, {"status_id": done_status.id})


def _update_parent_completion(session: Session, tenant_id: int, parent_task_id: int) -> None:
    """Update parent task completion percentage based on subtasks."""
    parent = get_task(session, tenant_id, parent_task_id)
    if not parent:
        return
    
    subtasks = list_subtasks(session, tenant_id, parent_task_id)
    
    if not subtasks:
        # No subtasks, reset completion
        update_task(session, tenant_id, parent_task_id, {"completion_percentage": 0})
        return
    
    # Calculate completion based on subtask completion percentages
    total_completion = sum(st.completion_percentage for st in subtasks)
    avg_completion = int(total_completion / len(subtasks))
    
    # Also check status-based completion
    done_count = 0
    for st in subtasks:
        status_obj = session.get(TaskStatus, st.status_id)
        if status_obj and status_obj.category == TaskStatusCategory.DONE:
            done_count += 1
    
    # Use the higher of percentage-based or status-based completion
    status_completion = int((done_count / len(subtasks)) * 100) if subtasks else 0
    final_completion = max(avg_completion, status_completion)
    
    # Update parent
    if parent.completion_percentage != final_completion:
        update_task(session, tenant_id, parent_task_id, {"completion_percentage": final_completion})
        
        # If all subtasks are done, mark parent as done
        if final_completion == 100:
            done_status = session.exec(
                select(TaskStatus).where(
                    and_(
                        TaskStatus.tenant_id == tenant_id,
                        TaskStatus.category == TaskStatusCategory.DONE
                    )
                )
            ).first()
            
            if done_status and parent.status_id != done_status.id:
                update_task(session, tenant_id, parent_task_id, {"status_id": done_status.id})















