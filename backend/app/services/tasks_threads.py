"""
Communication Threads Service

Handles nested comment threads for tasks and projects with search functionality.
"""
import logging
import re
from datetime import datetime
from typing import Optional, List, Dict, Any

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import Task, Project, TaskComment, User

logger = logging.getLogger(__name__)


async def create_thread(
    tenant_id: str,
    user_id: str,
    comment: str,
    task_id: Optional[str] = None,
    project_id: Optional[str] = None,
    parent_id: Optional[str] = None
) -> TaskComment:
    """Create a new thread/comment (supports nesting)."""
    # Verify task or project exists
    if task_id:
        task = await Task.find_one(
            Task.id == PydanticObjectId(task_id),
            Task.tenant_id == tenant_id
        )
        if not task:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Task not found"
            )
    elif project_id:
        project = await Project.find_one(
            Project.id == PydanticObjectId(project_id),
            Project.tenant_id == tenant_id
        )
        if not project:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Project not found"
            )
    else:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Either task_id or project_id is required"
        )
    
    # Verify parent comment if replying
    if parent_id:
        parent = await TaskComment.find_one(
            TaskComment.id == PydanticObjectId(parent_id),
            TaskComment.tenant_id == tenant_id
        )
        if not parent:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Parent comment not found"
            )
        # Inherit task/project from parent
        task_id = parent.task_id
        project_id = parent.project_id
    
    thread = TaskComment(
        tenant_id=tenant_id,
        task_id=task_id,
        project_id=project_id,
        user_id=user_id,
        parent_id=parent_id,
        comment=comment
    )
    await thread.insert()
    return thread


async def list_threads(
    tenant_id: str,
    task_id: Optional[str] = None,
    project_id: Optional[str] = None,
    parent_id: Optional[str] = None,
    search: Optional[str] = None,
    include_replies: bool = True
) -> List[TaskComment]:
    """List threads/comments with filters."""
    conditions = [TaskComment.tenant_id == tenant_id]
    
    if task_id:
        conditions.append(TaskComment.task_id == task_id)
    
    if project_id:
        conditions.append(TaskComment.project_id == project_id)
    
    if parent_id is not None:
        conditions.append(TaskComment.parent_id == parent_id)
    elif not include_replies:
        # Only top-level comments (no parent)
        conditions.append(TaskComment.parent_id == None)
    
    if search:
        search_regex = re.compile(f".*{re.escape(search)}.*", re.IGNORECASE)
        conditions.append({"comment": {"$regex": search_regex}})
    
    return await TaskComment.find(*conditions).sort(+TaskComment.created_at).to_list()


async def get_thread_with_replies(tenant_id: str, thread_id: str) -> Optional[TaskComment]:
    """Get a thread with all nested replies."""
    thread = await TaskComment.find_one(
        TaskComment.id == PydanticObjectId(thread_id),
        TaskComment.tenant_id == tenant_id
    )
    if not thread:
        return None
    
    return thread


async def update_thread(
    tenant_id: str,
    thread_id: str,
    user_id: str,
    comment: str
) -> TaskComment:
    """Update a thread/comment."""
    thread = await TaskComment.find_one(
        TaskComment.id == PydanticObjectId(thread_id),
        TaskComment.tenant_id == tenant_id
    )
    if not thread:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Thread not found"
        )
    
    # Only author can edit
    if thread.user_id != user_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You can only edit your own comments"
        )
    
    thread.comment = comment
    thread.is_edited = True
    thread.updated_at = datetime.utcnow()
    await thread.save()
    return thread


async def delete_thread(tenant_id: str, thread_id: str, user_id: str) -> None:
    """Delete a thread (and all replies if any)."""
    thread = await TaskComment.find_one(
        TaskComment.id == PydanticObjectId(thread_id),
        TaskComment.tenant_id == tenant_id
    )
    if not thread:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Thread not found"
        )
    
    # Only author can delete
    if thread.user_id != user_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You can only delete your own comments"
        )
    
    # Delete all replies first
    replies = await TaskComment.find(
        TaskComment.tenant_id == tenant_id,
        TaskComment.parent_id == thread_id
    ).to_list()
    
    for reply in replies:
        await reply.delete()
    
    await thread.delete()


async def search_threads(
    tenant_id: str,
    search_query: str,
    task_id: Optional[str] = None,
    project_id: Optional[str] = None,
    user_id: Optional[str] = None
) -> List[TaskComment]:
    """Search threads/comments."""
    search_regex = re.compile(f".*{re.escape(search_query)}.*", re.IGNORECASE)
    conditions = [
        TaskComment.tenant_id == tenant_id,
        {"comment": {"$regex": search_regex}}
    ]
    
    if task_id:
        conditions.append(TaskComment.task_id == task_id)
    
    if project_id:
        conditions.append(TaskComment.project_id == project_id)
    
    if user_id:
        conditions.append(TaskComment.user_id == user_id)
    
    return await TaskComment.find(*conditions).sort(-TaskComment.created_at).to_list()
