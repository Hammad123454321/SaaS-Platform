"""
Milestones Service

Business logic for milestone management operations.
All operations are tenant-isolated.
"""
import logging
from datetime import datetime, date
from typing import Optional, List, Dict, Any

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import Project, Milestone, Task

logger = logging.getLogger(__name__)


# ========== Milestone Operations ==========
async def create_milestone(
    tenant_id: str,
    project_id: str,
    milestone_data: Dict[str, Any]
) -> Milestone:
    """Create a new milestone for a project."""
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
    
    milestone = Milestone(
        tenant_id=tenant_id,
        project_id=project_id,
        title=milestone_data.get("title", ""),
        description=milestone_data.get("description"),
        due_date=milestone_data.get("due_date"),
        is_completed=milestone_data.get("is_completed", False),
    )
    
    if milestone.is_completed:
        milestone.completed_at = datetime.utcnow()
    
    await milestone.insert()
    return milestone


async def get_milestone(tenant_id: str, milestone_id: str) -> Optional[Milestone]:
    """Get a milestone by ID."""
    return await Milestone.find_one(
        Milestone.id == PydanticObjectId(milestone_id),
        Milestone.tenant_id == tenant_id
    )


async def list_milestones(
    tenant_id: str,
    project_id: Optional[str] = None,
    is_completed: Optional[bool] = None
) -> List[Milestone]:
    """List milestones for a tenant/project."""
    conditions = [Milestone.tenant_id == tenant_id]
    
    if project_id:
        conditions.append(Milestone.project_id == project_id)
    
    if is_completed is not None:
        conditions.append(Milestone.is_completed == is_completed)
    
    return await Milestone.find(*conditions).sort(+Milestone.due_date, -Milestone.created_at).to_list()


async def update_milestone(
    tenant_id: str,
    milestone_id: str,
    updates: Dict[str, Any]
) -> Milestone:
    """Update a milestone."""
    milestone = await get_milestone(tenant_id, milestone_id)
    if not milestone:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Milestone not found"
        )
    
    # Update fields
    if "title" in updates:
        milestone.title = updates["title"]
    if "description" in updates:
        milestone.description = updates["description"]
    if "due_date" in updates:
        milestone.due_date = updates["due_date"]
    
    # Handle completion status
    if "is_completed" in updates:
        milestone.is_completed = updates["is_completed"]
        if milestone.is_completed and not milestone.completed_at:
            milestone.completed_at = datetime.utcnow()
        elif not milestone.is_completed:
            milestone.completed_at = None
    
    milestone.updated_at = datetime.utcnow()
    await milestone.save()
    return milestone


async def delete_milestone(tenant_id: str, milestone_id: str) -> None:
    """Delete a milestone."""
    milestone = await get_milestone(tenant_id, milestone_id)
    if not milestone:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Milestone not found"
        )
    
    await milestone.delete()


async def get_milestone_tasks(tenant_id: str, milestone_id: str) -> List[Task]:
    """Get all tasks associated with a milestone (via project)."""
    milestone = await get_milestone(tenant_id, milestone_id)
    if not milestone:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Milestone not found"
        )
    
    # Get all tasks in the milestone's project
    tasks = await Task.find(
        Task.tenant_id == tenant_id,
        Task.project_id == milestone.project_id
    ).to_list()
    
    return tasks


async def get_milestone_completion_stats(tenant_id: str, milestone_id: str) -> Dict[str, Any]:
    """Get completion statistics for a milestone."""
    milestone = await get_milestone(tenant_id, milestone_id)
    if not milestone:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Milestone not found"
        )
    
    # Get all tasks in the project
    tasks = await Task.find(
        Task.tenant_id == tenant_id,
        Task.project_id == milestone.project_id
    ).to_list()
    
    total_tasks = len(tasks)
    completed_tasks = sum(1 for t in tasks if t.completion_percentage == 100)
    completion_rate = (completed_tasks / total_tasks * 100) if total_tasks > 0 else 0
    
    return {
        "milestone_id": milestone_id,
        "is_completed": milestone.is_completed,
        "total_tasks": total_tasks,
        "completed_tasks": completed_tasks,
        "completion_rate": round(completion_rate, 2),
        "due_date": str(milestone.due_date) if milestone.due_date else None,
    }
