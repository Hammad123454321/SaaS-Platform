"""
Resource Allocation Service

Handles resource allocation for projects and availability tracking.
"""
import logging
from datetime import date, datetime
from typing import Optional, List, Dict, Any
from decimal import Decimal

from sqlmodel import Session, select, and_, or_, func
from fastapi import HTTPException, status

from app.models import Project, User, ResourceAllocation, TimeEntry, Task

logger = logging.getLogger(__name__)


def allocate_resource(
    session: Session,
    tenant_id: int,
    project_id: int,
    user_id: int,
    allocated_hours: Decimal,
    start_date: date,
    end_date: Optional[date] = None
) -> ResourceAllocation:
    """Allocate a resource to a project."""
    # Verify project exists
    project = session.get(Project, project_id)
    if not project or project.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Project not found"
        )
    
    # Verify user exists
    user = session.get(User, user_id)
    if not user or user.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found"
        )
    
    # Check for overlapping allocations
    existing = session.exec(
        select(ResourceAllocation).where(
            and_(
                ResourceAllocation.tenant_id == tenant_id,
                ResourceAllocation.user_id == user_id,
                ResourceAllocation.is_active == True,
                or_(
                    and_(
                        ResourceAllocation.start_date <= start_date,
                        or_(
                            ResourceAllocation.end_date.is_(None),
                            ResourceAllocation.end_date >= start_date
                        )
                    ),
                    and_(
                        end_date is not None,
                        ResourceAllocation.start_date <= end_date,
                        or_(
                            ResourceAllocation.end_date.is_(None),
                            ResourceAllocation.end_date >= end_date
                        )
                    )
                )
            )
        )
    ).first()
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="User already has an active allocation in this time period"
        )
    
    allocation = ResourceAllocation(
        tenant_id=tenant_id,
        project_id=project_id,
        user_id=user_id,
        allocated_hours=allocated_hours,
        start_date=start_date,
        end_date=end_date,
        is_active=True
    )
    session.add(allocation)
    session.commit()
    session.refresh(allocation)
    return allocation


def list_allocations(
    session: Session,
    tenant_id: int,
    project_id: Optional[int] = None,
    user_id: Optional[int] = None,
    is_active: Optional[bool] = None
) -> List[ResourceAllocation]:
    """List resource allocations."""
    query = select(ResourceAllocation).where(ResourceAllocation.tenant_id == tenant_id)
    
    if project_id:
        query = query.where(ResourceAllocation.project_id == project_id)
    
    if user_id:
        query = query.where(ResourceAllocation.user_id == user_id)
    
    if is_active is not None:
        query = query.where(ResourceAllocation.is_active == is_active)
    
    return list(session.exec(query.order_by(ResourceAllocation.start_date.desc())).all())


def update_allocation(
    session: Session,
    tenant_id: int,
    allocation_id: int,
    updates: Dict[str, Any]
) -> ResourceAllocation:
    """Update a resource allocation."""
    allocation = session.get(ResourceAllocation, allocation_id)
    if not allocation or allocation.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Allocation not found"
        )
    
    for key, value in updates.items():
        if hasattr(allocation, key):
            if key == "allocated_hours":
                setattr(allocation, key, Decimal(str(value)))
            else:
                setattr(allocation, key, value)
    
    allocation.updated_at = datetime.utcnow()
    session.add(allocation)
    session.commit()
    session.refresh(allocation)
    return allocation


def deallocate_resource(session: Session, tenant_id: int, allocation_id: int) -> None:
    """Deactivate a resource allocation."""
    allocation = session.get(ResourceAllocation, allocation_id)
    if not allocation or allocation.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Allocation not found"
        )
    
    allocation.is_active = False
    allocation.updated_at = datetime.utcnow()
    session.add(allocation)
    session.commit()


def get_resource_availability(
    session: Session,
    tenant_id: int,
    user_id: int,
    start_date: date,
    end_date: date
) -> Dict[str, Any]:
    """Get resource availability and utilization."""
    # Get allocations in date range
    allocations = session.exec(
        select(ResourceAllocation).where(
            and_(
                ResourceAllocation.tenant_id == tenant_id,
                ResourceAllocation.user_id == user_id,
                ResourceAllocation.is_active == True,
                ResourceAllocation.start_date <= end_date,
                or_(
                    ResourceAllocation.end_date.is_(None),
                    ResourceAllocation.end_date >= start_date
                )
            )
        )
    ).all()
    
    # Get actual time logged in date range
    time_entries = session.exec(
        select(
            TimeEntry.task_id,
            func.sum(TimeEntry.hours).label("total_hours")
        ).where(
            and_(
                TimeEntry.tenant_id == tenant_id,
                TimeEntry.user_id == user_id,
                TimeEntry.entry_date >= start_date,
                TimeEntry.entry_date <= end_date
            )
        ).group_by(TimeEntry.task_id)
    ).all()
    
    total_allocated = sum(float(a.allocated_hours) for a in allocations)
    total_logged = sum(float(row[1] or 0) for row in time_entries)
    
    # Calculate availability
    working_days = (end_date - start_date).days + 1
    standard_hours_per_day = 8
    total_available_hours = working_days * standard_hours_per_day
    
    utilization_percentage = (total_logged / total_allocated * 100) if total_allocated > 0 else 0
    availability_percentage = ((total_available_hours - total_logged) / total_available_hours * 100) if total_available_hours > 0 else 0
    
    return {
        "user_id": user_id,
        "start_date": str(start_date),
        "end_date": str(end_date),
        "total_allocated_hours": total_allocated,
        "total_logged_hours": total_logged,
        "total_available_hours": total_available_hours,
        "utilization_percentage": round(utilization_percentage, 2),
        "availability_percentage": round(availability_percentage, 2),
        "allocations": [
            {
                "id": a.id,
                "project_id": a.project_id,
                "allocated_hours": float(a.allocated_hours),
                "start_date": str(a.start_date),
                "end_date": str(a.end_date) if a.end_date else None
            }
            for a in allocations
        ]
    }

