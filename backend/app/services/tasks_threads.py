"""
Communication Threads Service

Handles nested comment threads for tasks and projects with search functionality.
"""
import logging
from datetime import datetime
from typing import Optional, List, Dict, Any

from sqlmodel import Session, select, and_, or_
from fastapi import HTTPException, status

from app.models import Task, Project, TaskComment, User

logger = logging.getLogger(__name__)


def create_thread(
    session: Session,
    tenant_id: int,
    user_id: int,
    comment: str,
    task_id: Optional[int] = None,
    project_id: Optional[int] = None,
    parent_id: Optional[int] = None
) -> TaskComment:
    """Create a new thread/comment (supports nesting)."""
    # Verify task or project exists
    if task_id:
        task = session.get(Task, task_id)
        if not task or task.tenant_id != tenant_id:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Task not found"
            )
    elif project_id:
        project = session.get(Project, project_id)
        if not project or project.tenant_id != tenant_id:
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
        parent = session.get(TaskComment, parent_id)
        if not parent or parent.tenant_id != tenant_id:
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
    session.add(thread)
    session.commit()
    session.refresh(thread)
    return thread


def list_threads(
    session: Session,
    tenant_id: int,
    task_id: Optional[int] = None,
    project_id: Optional[int] = None,
    parent_id: Optional[int] = None,
    search: Optional[str] = None,
    include_replies: bool = True
) -> List[TaskComment]:
    """List threads/comments with filters."""
    query = select(TaskComment).where(TaskComment.tenant_id == tenant_id)
    
    if task_id:
        query = query.where(TaskComment.task_id == task_id)
    
    if project_id:
        query = query.where(TaskComment.project_id == project_id)
    
    if parent_id is not None:
        query = query.where(TaskComment.parent_id == parent_id)
    elif include_replies:
        # Only top-level comments (no parent)
        query = query.where(TaskComment.parent_id.is_(None))
    
    if search:
        query = query.where(TaskComment.comment.ilike(f"%{search}%"))
    
    return list(session.exec(query.order_by(TaskComment.created_at.asc())).all())


def get_thread_with_replies(session: Session, tenant_id: int, thread_id: int) -> Optional[TaskComment]:
    """Get a thread with all nested replies."""
    thread = session.get(TaskComment, thread_id)
    if not thread or thread.tenant_id != tenant_id:
        return None
    
    # Load replies recursively
    replies = list_threads(session, tenant_id, parent_id=thread_id, include_replies=True)
    thread.replies = replies
    
    return thread


def update_thread(
    session: Session,
    tenant_id: int,
    thread_id: int,
    user_id: int,
    comment: str
) -> TaskComment:
    """Update a thread/comment."""
    thread = session.get(TaskComment, thread_id)
    if not thread or thread.tenant_id != tenant_id:
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
    session.add(thread)
    session.commit()
    session.refresh(thread)
    return thread


def delete_thread(session: Session, tenant_id: int, thread_id: int, user_id: int) -> None:
    """Delete a thread (and all replies if any)."""
    thread = session.get(TaskComment, thread_id)
    if not thread or thread.tenant_id != tenant_id:
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
    replies = list_threads(session, tenant_id, parent_id=thread_id, include_replies=True)
    for reply in replies:
        session.delete(reply)
    
    session.delete(thread)
    session.commit()


def search_threads(
    session: Session,
    tenant_id: int,
    search_query: str,
    task_id: Optional[int] = None,
    project_id: Optional[int] = None,
    user_id: Optional[int] = None
) -> List[TaskComment]:
    """Search threads/comments."""
    query = select(TaskComment).where(
        and_(
            TaskComment.tenant_id == tenant_id,
            TaskComment.comment.ilike(f"%{search_query}%")
        )
    )
    
    if task_id:
        query = query.where(TaskComment.task_id == task_id)
    
    if project_id:
        query = query.where(TaskComment.project_id == project_id)
    
    if user_id:
        query = query.where(TaskComment.user_id == user_id)
    
    return list(session.exec(query.order_by(TaskComment.created_at.desc())).all())

















