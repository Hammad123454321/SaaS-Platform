"""
Resource Allocation Service

Handles resource allocation for projects and availability tracking.
"""
import logging
from datetime import date, datetime
from typing import Optional, List, Dict, Any
from decimal import Decimal

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import Project, User, ResourceAllocation, TimeEntry, Task

logger = logging.getLogger(__name__)


async def allocate_resource(
    tenant_id: str,
    project_id: str,
    user_id: str,
    allocated_hours: Decimal,
    start_date: date,
    end_date: Optional[date] = None
) -> ResourceAllocation:
    """Allocate a resource to a project."""
    # Verify project exists
    project = await Project.find_one(
        Project.id == PydanticObjectId(project_id),
        Project.tenant_id == tenant_id
    )
    if not project:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Project not found"
        )
    
    # Verify user exists
    user = await User.find_one(
        User.id == PydanticObjectId(user_id),
        User.tenant_id == tenant_id
    )
    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found"
        )
    
    # Check for overlapping allocations (simplified check)
    existing = await ResourceAllocation.find_one(
        ResourceAllocation.tenant_id == tenant_id,
        ResourceAllocation.user_id == user_id,
        ResourceAllocation.is_active == True,
        ResourceAllocation.start_date <= start_date
    )
    
    if existing and (existing.end_date is None or existing.end_date >= start_date):
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
    await allocation.insert()
    return allocation


async def list_allocations(
    tenant_id: str,
    project_id: Optional[str] = None,
    user_id: Optional[str] = None,
    is_active: Optional[bool] = None
) -> List[ResourceAllocation]:
    """List resource allocations."""
    conditions = [ResourceAllocation.tenant_id == tenant_id]
    
    if project_id:
        conditions.append(ResourceAllocation.project_id == project_id)
    
    if user_id:
        conditions.append(ResourceAllocation.user_id == user_id)
    
    if is_active is not None:
        conditions.append(ResourceAllocation.is_active == is_active)
    
    return await ResourceAllocation.find(*conditions).sort(-ResourceAllocation.start_date).to_list()


async def update_allocation(
    tenant_id: str,
    allocation_id: str,
    updates: Dict[str, Any]
) -> ResourceAllocation:
    """Update a resource allocation."""
    allocation = await ResourceAllocation.find_one(
        ResourceAllocation.id == PydanticObjectId(allocation_id),
        ResourceAllocation.tenant_id == tenant_id
    )
    if not allocation:
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
    await allocation.save()
    return allocation


async def deallocate_resource(tenant_id: str, allocation_id: str) -> None:
    """Deactivate a resource allocation."""
    allocation = await ResourceAllocation.find_one(
        ResourceAllocation.id == PydanticObjectId(allocation_id),
        ResourceAllocation.tenant_id == tenant_id
    )
    if not allocation:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Allocation not found"
        )
    
    allocation.is_active = False
    allocation.updated_at = datetime.utcnow()
    await allocation.save()


async def get_resource_availability(
    tenant_id: str,
    user_id: str,
    start_date: date,
    end_date: date
) -> Dict[str, Any]:
    """Get resource availability and utilization."""
    # Get allocations in date range
    allocations = await ResourceAllocation.find(
        ResourceAllocation.tenant_id == tenant_id,
        ResourceAllocation.user_id == user_id,
        ResourceAllocation.is_active == True,
        ResourceAllocation.start_date <= end_date
    ).to_list()
    
    # Filter allocations that overlap with date range
    filtered_allocations = [
        a for a in allocations 
        if a.end_date is None or a.end_date >= start_date
    ]
    
    # Get actual time logged in date range
    time_entries = await TimeEntry.find(
        TimeEntry.tenant_id == tenant_id,
        TimeEntry.user_id == user_id,
        TimeEntry.entry_date >= start_date,
        TimeEntry.entry_date <= end_date
    ).to_list()
    
    total_allocated = sum(float(a.allocated_hours) for a in filtered_allocations)
    total_logged = sum(float(e.hours or 0) for e in time_entries)
    
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
                "id": str(a.id),
                "project_id": a.project_id,
                "allocated_hours": float(a.allocated_hours),
                "start_date": str(a.start_date),
                "end_date": str(a.end_date) if a.end_date else None
            }
            for a in filtered_allocations
        ]
    }
