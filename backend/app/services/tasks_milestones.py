"""
Milestones Service

Business logic for milestone management operations.
All operations are tenant-isolated.
"""
import logging
from datetime import datetime, date
from typing import Optional, List, Dict, Any

from sqlmodel import Session, select, and_
from fastapi import HTTPException, status

from app.models import Project, Milestone, Task

logger = logging.getLogger(__name__)


# ========== Milestone Operations ==========
def create_milestone(
    session: Session,
    tenant_id: int,
    project_id: int,
    milestone_data: Dict[str, Any]
) -> Milestone:
    """Create a new milestone for a project."""
    # Verify project exists and belongs to tenant
    project = session.get(Project, project_id)
    if not project or project.tenant_id != tenant_id:
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
    
    session.add(milestone)
    session.commit()
    session.refresh(milestone)
    return milestone


def get_milestone(session: Session, tenant_id: int, milestone_id: int) -> Optional[Milestone]:
    """Get a milestone by ID."""
    return session.exec(
        select(Milestone).where(
            and_(
                Milestone.id == milestone_id,
                Milestone.tenant_id == tenant_id
            )
        )
    ).first()


def list_milestones(
    session: Session,
    tenant_id: int,
    project_id: Optional[int] = None,
    is_completed: Optional[bool] = None
) -> List[Milestone]:
    """List milestones for a tenant/project."""
    query = select(Milestone).where(Milestone.tenant_id == tenant_id)
    
    if project_id:
        query = query.where(Milestone.project_id == project_id)
    
    if is_completed is not None:
        query = query.where(Milestone.is_completed == is_completed)
    
    return list(session.exec(query.order_by(Milestone.due_date.asc(), Milestone.created_at.desc())).all())


def update_milestone(
    session: Session,
    tenant_id: int,
    milestone_id: int,
    updates: Dict[str, Any]
) -> Milestone:
    """Update a milestone."""
    milestone = get_milestone(session, tenant_id, milestone_id)
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
    session.add(milestone)
    session.commit()
    session.refresh(milestone)
    return milestone


def delete_milestone(session: Session, tenant_id: int, milestone_id: int) -> None:
    """Delete a milestone."""
    milestone = get_milestone(session, tenant_id, milestone_id)
    if not milestone:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Milestone not found"
        )
    
    session.delete(milestone)
    session.commit()


def get_milestone_tasks(session: Session, tenant_id: int, milestone_id: int) -> List[Task]:
    """Get all tasks associated with a milestone (via project)."""
    milestone = get_milestone(session, tenant_id, milestone_id)
    if not milestone:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Milestone not found"
        )
    
    # Get all tasks in the milestone's project
    # Note: In a more advanced implementation, you might want a direct task-milestone relationship
    tasks = session.exec(
        select(Task).where(
            and_(
                Task.tenant_id == tenant_id,
                Task.project_id == milestone.project_id
            )
        )
    ).all()
    
    return list(tasks)


def get_milestone_completion_stats(session: Session, tenant_id: int, milestone_id: int) -> Dict[str, Any]:
    """Get completion statistics for a milestone."""
    milestone = get_milestone(session, tenant_id, milestone_id)
    if not milestone:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Milestone not found"
        )
    
    # Get all tasks in the project
    tasks = session.exec(
        select(Task).where(
            and_(
                Task.tenant_id == tenant_id,
                Task.project_id == milestone.project_id
            )
        )
    ).all()
    
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





