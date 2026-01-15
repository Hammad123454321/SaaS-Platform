"""
Tags Service

Business logic for tag management operations.
All operations are tenant-isolated.
"""
import logging
from typing import Optional, List, Dict, Any

from sqlmodel import Session, select, and_, or_
from fastapi import HTTPException, status

from app.models import Tag, Task
from app.models.tasks import task_tags_table

logger = logging.getLogger(__name__)


# ========== Tag Operations ==========
def create_tag(session: Session, tenant_id: int, tag_data: Dict[str, Any]) -> Tag:
    """Create a new tag."""
    # Check if tag name already exists for this tenant
    existing = session.exec(
        select(Tag).where(
            and_(
                Tag.tenant_id == tenant_id,
                Tag.name.ilike(tag_data["name"])
            )
        )
    ).first()
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Tag with this name already exists"
        )
    
    tag = Tag(
        tenant_id=tenant_id,
        name=tag_data["name"],
        color=tag_data.get("color", "#6b7280"),
    )
    session.add(tag)
    session.commit()
    session.refresh(tag)
    return tag


def get_tag(session: Session, tenant_id: int, tag_id: int) -> Optional[Tag]:
    """Get a tag by ID."""
    return session.exec(
        select(Tag).where(
            and_(
                Tag.id == tag_id,
                Tag.tenant_id == tenant_id
            )
        )
    ).first()


def list_tags(session: Session, tenant_id: int, search: Optional[str] = None) -> List[Tag]:
    """List all tags for a tenant."""
    query = select(Tag).where(Tag.tenant_id == tenant_id)
    
    if search:
        query = query.where(Tag.name.ilike(f"%{search}%"))
    
    return list(session.exec(query.order_by(Tag.name.asc())).all())


def update_tag(session: Session, tenant_id: int, tag_id: int, updates: Dict[str, Any]) -> Tag:
    """Update a tag."""
    tag = get_tag(session, tenant_id, tag_id)
    if not tag:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Tag not found"
        )
    
    # Check name uniqueness if name is being updated
    if "name" in updates and updates["name"] != tag.name:
        existing = session.exec(
            select(Tag).where(
                and_(
                    Tag.tenant_id == tenant_id,
                    Tag.name.ilike(updates["name"]),
                    Tag.id != tag_id
                )
            )
        ).first()
        
        if existing:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Tag with this name already exists"
            )
    
    if "name" in updates:
        tag.name = updates["name"]
    if "color" in updates:
        tag.color = updates["color"]
    
    session.add(tag)
    session.commit()
    session.refresh(tag)
    return tag


def delete_tag(session: Session, tenant_id: int, tag_id: int) -> None:
    """Delete a tag (removes all task associations)."""
    tag = get_tag(session, tenant_id, tag_id)
    if not tag:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Tag not found"
        )
    
    # Remove all task associations (handled by CASCADE in database)
    session.delete(tag)
    session.commit()


def assign_tags_to_task(
    session: Session,
    tenant_id: int,
    task_id: int,
    tag_ids: List[int]
) -> Task:
    """Assign tags to a task."""
    from sqlalchemy import delete, insert
    
    # Verify task exists
    task = session.get(Task, task_id)
    if not task or task.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task not found"
        )
    
    # Verify all tags exist and belong to tenant
    for tag_id in tag_ids:
        tag = get_tag(session, tenant_id, tag_id)
        if not tag:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Tag {tag_id} not found"
            )
    
    # Remove existing associations
    session.exec(
        delete(task_tags_table).where(
            task_tags_table.c.task_id == task_id
        )
    )
    
    # Add new associations
    if tag_ids:
        session.exec(
            insert(task_tags_table).values([
                {"task_id": task_id, "tag_id": tag_id}
                for tag_id in tag_ids
            ])
        )
    
    session.commit()
    session.refresh(task)
    return task


def get_task_tags(session: Session, tenant_id: int, task_id: int) -> List[Tag]:
    """Get all tags for a task."""
    task = session.get(Task, task_id)
    if not task or task.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task not found"
        )
    
    # Get tags via the association table
    tags = session.exec(
        select(Tag)
        .join(task_tags_table, Tag.id == task_tags_table.c.tag_id)
        .where(task_tags_table.c.task_id == task_id)
    ).all()
    
    return list(tags)


def get_tasks_by_tag(session: Session, tenant_id: int, tag_id: int) -> List[Task]:
    """Get all tasks with a specific tag."""
    tag = get_tag(session, tenant_id, tag_id)
    if not tag:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Tag not found"
        )
    
    tasks = session.exec(
        select(Task)
        .join(task_tags_table, Task.id == task_tags_table.c.task_id)
        .where(
            and_(
                Task.tenant_id == tenant_id,
                task_tags_table.c.tag_id == tag_id
            )
        )
    ).all()
    
    return list(tasks)


def merge_tags(session: Session, tenant_id: int, source_tag_id: int, target_tag_id: int) -> Tag:
    """Merge two tags (move all task associations from source to target, then delete source)."""
    source_tag = get_tag(session, tenant_id, source_tag_id)
    target_tag = get_tag(session, tenant_id, target_tag_id)
    
    if not source_tag or not target_tag:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Tag not found"
        )
    
    if source_tag_id == target_tag_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot merge tag with itself"
        )
    
    # Get all tasks with source tag
    tasks_with_source = get_tasks_by_tag(session, tenant_id, source_tag_id)
    
    # For each task, ensure it has the target tag
    for task in tasks_with_source:
        current_tags = get_task_tags(session, tenant_id, task.id)
        current_tag_ids = [t.id for t in current_tags]
        
        # Remove source tag, add target tag if not present
        if target_tag_id not in current_tag_ids:
            new_tag_ids = [tid for tid in current_tag_ids if tid != source_tag_id] + [target_tag_id]
            assign_tags_to_task(session, tenant_id, task.id, new_tag_ids)
        else:
            # Just remove source tag
            new_tag_ids = [tid for tid in current_tag_ids if tid != source_tag_id]
            assign_tags_to_task(session, tenant_id, task.id, new_tag_ids)
    
    # Delete source tag
    delete_tag(session, tenant_id, source_tag_id)
    
    return target_tag





