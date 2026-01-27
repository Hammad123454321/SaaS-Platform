"""
Tags Service

Business logic for tag management operations.
All operations are tenant-isolated.
"""
import logging
import re
from typing import Optional, List, Dict, Any

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import Tag, Task
from app.models.tasks import TaskTagLink

logger = logging.getLogger(__name__)


# ========== Tag Operations ==========
async def create_tag(tenant_id: str, tag_data: Dict[str, Any]) -> Tag:
    """Create a new tag."""
    # Check if tag name already exists for this tenant (case-insensitive)
    tag_name = tag_data["name"]
    existing = await Tag.find_one(
        Tag.tenant_id == tenant_id,
        {"name": {"$regex": f"^{re.escape(tag_name)}$", "$options": "i"}}
    )
    
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
    await tag.insert()
    return tag


async def get_tag(tenant_id: str, tag_id: str) -> Optional[Tag]:
    """Get a tag by ID."""
    return await Tag.find_one(
        Tag.id == PydanticObjectId(tag_id),
        Tag.tenant_id == tenant_id
    )


async def list_tags(tenant_id: str, search: Optional[str] = None) -> List[Tag]:
    """List all tags for a tenant."""
    conditions = [Tag.tenant_id == tenant_id]
    
    if search:
        search_regex = re.compile(f".*{re.escape(search)}.*", re.IGNORECASE)
        conditions.append({"name": {"$regex": search_regex}})
    
    return await Tag.find(*conditions).sort(+Tag.name).to_list()


async def update_tag(tenant_id: str, tag_id: str, updates: Dict[str, Any]) -> Tag:
    """Update a tag."""
    tag = await get_tag(tenant_id, tag_id)
    if not tag:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Tag not found"
        )
    
    # Check name uniqueness if name is being updated
    if "name" in updates and updates["name"] != tag.name:
        existing = await Tag.find_one(
            Tag.tenant_id == tenant_id,
            {"name": {"$regex": f"^{re.escape(updates['name'])}$", "$options": "i"}},
            Tag.id != PydanticObjectId(tag_id)
        )
        
        if existing:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Tag with this name already exists"
            )
    
    if "name" in updates:
        tag.name = updates["name"]
    if "color" in updates:
        tag.color = updates["color"]
    
    await tag.save()
    return tag


async def delete_tag(tenant_id: str, tag_id: str) -> None:
    """Delete a tag (removes all task associations)."""
    tag = await get_tag(tenant_id, tag_id)
    if not tag:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Tag not found"
        )
    
    # Remove all task associations
    links = await TaskTagLink.find(TaskTagLink.tag_id == tag_id).to_list()
    for link in links:
        await link.delete()
    
    await tag.delete()


async def assign_tags_to_task(
    tenant_id: str,
    task_id: str,
    tag_ids: List[str]
) -> Task:
    """Assign tags to a task."""
    # Verify task exists
    task = await Task.find_one(
        Task.id == PydanticObjectId(task_id),
        Task.tenant_id == tenant_id
    )
    if not task:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task not found"
        )
    
    # Verify all tags exist and belong to tenant
    for tag_id in tag_ids:
        tag = await get_tag(tenant_id, tag_id)
        if not tag:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Tag {tag_id} not found"
            )
    
    # Remove existing associations
    existing_links = await TaskTagLink.find(TaskTagLink.task_id == task_id).to_list()
    for link in existing_links:
        await link.delete()
    
    # Add new associations
    for tag_id in tag_ids:
        link = TaskTagLink(task_id=task_id, tag_id=tag_id)
        await link.insert()
    
    return task


async def get_task_tags(tenant_id: str, task_id: str) -> List[Tag]:
    """Get all tags for a task."""
    task = await Task.find_one(
        Task.id == PydanticObjectId(task_id),
        Task.tenant_id == tenant_id
    )
    if not task:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task not found"
        )
    
    # Get tags via the link collection
    links = await TaskTagLink.find(TaskTagLink.task_id == task_id).to_list()
    tag_ids = [link.tag_id for link in links]
    
    if not tag_ids:
        return []
    
    tags = await Tag.find(
        {"_id": {"$in": [PydanticObjectId(tid) for tid in tag_ids]}}
    ).to_list()
    
    return tags


async def get_tasks_by_tag(tenant_id: str, tag_id: str) -> List[Task]:
    """Get all tasks with a specific tag."""
    tag = await get_tag(tenant_id, tag_id)
    if not tag:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Tag not found"
        )
    
    # Get task IDs via the link collection
    links = await TaskTagLink.find(TaskTagLink.tag_id == tag_id).to_list()
    task_ids = [link.task_id for link in links]
    
    if not task_ids:
        return []
    
    tasks = await Task.find(
        Task.tenant_id == tenant_id,
        {"_id": {"$in": [PydanticObjectId(tid) for tid in task_ids]}}
    ).to_list()
    
    return tasks


async def merge_tags(tenant_id: str, source_tag_id: str, target_tag_id: str) -> Tag:
    """Merge two tags (move all task associations from source to target, then delete source)."""
    source_tag = await get_tag(tenant_id, source_tag_id)
    target_tag = await get_tag(tenant_id, target_tag_id)
    
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
    tasks_with_source = await get_tasks_by_tag(tenant_id, source_tag_id)
    
    # For each task, ensure it has the target tag
    for task in tasks_with_source:
        current_tags = await get_task_tags(tenant_id, str(task.id))
        current_tag_ids = [str(t.id) for t in current_tags]
        
        # Remove source tag, add target tag if not present
        if target_tag_id not in current_tag_ids:
            new_tag_ids = [tid for tid in current_tag_ids if tid != source_tag_id] + [target_tag_id]
            await assign_tags_to_task(tenant_id, str(task.id), new_tag_ids)
        else:
            # Just remove source tag
            new_tag_ids = [tid for tid in current_tag_ids if tid != source_tag_id]
            await assign_tags_to_task(tenant_id, str(task.id), new_tag_ids)
    
    # Delete source tag
    await delete_tag(tenant_id, source_tag_id)
    
    return target_tag
