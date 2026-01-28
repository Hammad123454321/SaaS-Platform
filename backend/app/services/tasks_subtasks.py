"""
Subtask Management Service

Handles subtask CRUD operations, status inheritance, and parent task completion logic.
"""
import logging
from datetime import datetime
from typing import Optional, List, Dict, Any

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import Task, TaskStatus
from app.models.tasks import TaskStatusCategory
from app.services.tasks import get_task, update_task

logger = logging.getLogger(__name__)


async def create_subtask(
    tenant_id: str,
    parent_task_id: str,
    subtask_data: Dict[str, Any],
    created_by: str
) -> Task:
    """Create a new subtask."""
    # Verify parent task exists
    parent = await get_task(tenant_id, parent_task_id)
    if not parent:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Parent task not found"
        )
    
    # Ensure subtask has required fields
    if "status_id" not in subtask_data or subtask_data.get("status_id") is None:
        # Use parent's status if available, otherwise get default "To Do" status
        if parent.status_id:
            subtask_data["status_id"] = parent.status_id
        else:
            # Get default "To Do" status
            from app.services.tasks import ensure_default_statuses
            await ensure_default_statuses(tenant_id)
            default_status = await TaskStatus.find_one(
                TaskStatus.tenant_id == tenant_id,
                TaskStatus.name == "To Do"
            )
            if not default_status:
                # Fallback: get any status for this tenant
                default_status = await TaskStatus.find_one(
                    TaskStatus.tenant_id == tenant_id
                )
            if default_status:
                subtask_data["status_id"] = str(default_status.id)
            else:
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail="No status available. Please create a status first."
                )
    
    if "project_id" not in subtask_data:
        subtask_data["project_id"] = parent.project_id
    
    # Create subtask
    subtask = Task(
        tenant_id=tenant_id,
        parent_id=parent_task_id,
        created_by=created_by,
        **subtask_data
    )
    await subtask.insert()
    
    # Update parent task completion if needed
    await _update_parent_completion(tenant_id, parent_task_id)
    
    return subtask


async def get_subtask(tenant_id: str, subtask_id: str) -> Optional[Task]:
    """Get a subtask by ID."""
    return await Task.find_one(
        Task.id == PydanticObjectId(subtask_id),
        Task.tenant_id == tenant_id,
        Task.parent_id != None
    )


async def list_subtasks(tenant_id: str, parent_task_id: str) -> List[Task]:
    """List all subtasks for a parent task."""
    return await Task.find(
        Task.tenant_id == tenant_id,
        Task.parent_id == parent_task_id
    ).sort(+Task.created_at).to_list()


async def update_subtask(
    tenant_id: str,
    subtask_id: str,
    updates: Dict[str, Any]
) -> Task:
    """Update a subtask."""
    subtask = await get_subtask(tenant_id, subtask_id)
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
    await subtask.save()
    
    # Update parent task completion
    if subtask.parent_id:
        await _update_parent_completion(tenant_id, subtask.parent_id)
    
    # Handle status inheritance if status changed
    if "status_id" in updates:
        await _apply_status_inheritance(tenant_id, subtask_id, updates["status_id"])
    
    return subtask


async def delete_subtask(tenant_id: str, subtask_id: str) -> None:
    """Delete a subtask."""
    subtask = await get_subtask(tenant_id, subtask_id)
    if not subtask:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Subtask not found"
        )
    
    parent_id = subtask.parent_id
    await subtask.delete()
    
    # Update parent task completion
    if parent_id:
        await _update_parent_completion(tenant_id, parent_id)


async def _apply_status_inheritance(
    tenant_id: str,
    subtask_id: str,
    new_status_id: str
) -> None:
    """
    Apply status inheritance: if subtask status is 'done', 
    parent task status may be updated based on all subtasks.
    """
    subtask = await get_subtask(tenant_id, subtask_id)
    if not subtask or not subtask.parent_id:
        return
    
    # Get new status - safely handle invalid ObjectId
    try:
        new_status = await TaskStatus.get(PydanticObjectId(new_status_id))
    except Exception:
        return
    if not new_status:
        return
    
    # If subtask is marked as done, check if all subtasks are done
    if new_status.category == TaskStatusCategory.DONE:
        parent = await get_task(tenant_id, subtask.parent_id)
        if not parent:
            return
        
        # Get all subtasks
        subtasks = await list_subtasks(tenant_id, str(parent.id))
        
        # Check if all subtasks are done
        all_done = True
        for st in subtasks:
            if st.status_id != new_status_id:
                try:
                    status_obj = await TaskStatus.get(PydanticObjectId(st.status_id)) if st.status_id else None
                except Exception:
                    status_obj = None
                if status_obj and status_obj.category != TaskStatusCategory.DONE:
                    all_done = False
                    break
        
        # If all subtasks are done, update parent status to done
        if all_done and subtasks:
            done_status = await TaskStatus.find_one(
                TaskStatus.tenant_id == tenant_id,
                TaskStatus.category == TaskStatusCategory.DONE
            )
            
            if done_status:
                await update_task(tenant_id, str(parent.id), {"status_id": str(done_status.id)})


async def _update_parent_completion(tenant_id: str, parent_task_id: str) -> None:
    """Update parent task completion percentage based on subtasks."""
    parent = await get_task(tenant_id, parent_task_id)
    if not parent:
        return
    
    subtasks = await list_subtasks(tenant_id, parent_task_id)
    
    if not subtasks:
        # No subtasks, reset completion
        await update_task(tenant_id, parent_task_id, {"completion_percentage": 0})
        return
    
    # Calculate completion based on subtask completion percentages
    total_completion = sum(st.completion_percentage for st in subtasks)
    avg_completion = int(total_completion / len(subtasks))
    
    # Also check status-based completion
    done_count = 0
    for st in subtasks:
        try:
            status_obj = await TaskStatus.get(PydanticObjectId(st.status_id)) if st.status_id else None
        except Exception:
            status_obj = None
        if status_obj and status_obj.category == TaskStatusCategory.DONE:
            done_count += 1
    
    # Use the higher of percentage-based or status-based completion
    status_completion = int((done_count / len(subtasks)) * 100) if subtasks else 0
    final_completion = max(avg_completion, status_completion)
    
    # Update parent
    if parent.completion_percentage != final_completion:
        await update_task(tenant_id, parent_task_id, {"completion_percentage": final_completion})
        
        # If all subtasks are done, mark parent as done
        if final_completion == 100:
            done_status = await TaskStatus.find_one(
                TaskStatus.tenant_id == tenant_id,
                TaskStatus.category == TaskStatusCategory.DONE
            )
            
            if done_status and parent.status_id != str(done_status.id):
                await update_task(tenant_id, parent_task_id, {"status_id": str(done_status.id)})
