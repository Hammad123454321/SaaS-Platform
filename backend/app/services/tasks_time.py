"""
Time Tracking Service

Handles time entries, time tracker (start/stop), and time reports/analytics.
"""
import logging
from datetime import datetime, date, timedelta, timezone
from typing import Optional, List, Dict, Any
from decimal import Decimal

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import Task, User, TimeEntry, TimeTracker, Project

logger = logging.getLogger(__name__)


# ========== Time Entry Operations ==========
async def create_time_entry(
    tenant_id: str,
    task_id: str,
    user_id: str,
    time_data: Dict[str, Any]
) -> TimeEntry:
    """Create a time entry for a task."""
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
    
    time_entry = TimeEntry(
        tenant_id=tenant_id,
        task_id=task_id,
        user_id=user_id,
        hours=Decimal(str(time_data.get("hours", 0))),
        entry_date=time_data.get("date", date.today()),
        description=time_data.get("description"),
        is_billable=time_data.get("is_billable", True)
    )
    await time_entry.insert()
    return time_entry


async def list_time_entries(
    tenant_id: str,
    task_id: Optional[str] = None,
    user_id: Optional[str] = None,
    project_id: Optional[str] = None,
    start_date: Optional[date] = None,
    end_date: Optional[date] = None
) -> List[TimeEntry]:
    """List time entries with filters."""
    conditions = [TimeEntry.tenant_id == tenant_id]
    
    if task_id:
        conditions.append(TimeEntry.task_id == task_id)
    
    if user_id:
        conditions.append(TimeEntry.user_id == user_id)
    
    if start_date:
        conditions.append(TimeEntry.entry_date >= start_date)
    
    if end_date:
        conditions.append(TimeEntry.entry_date <= end_date)
    
    entries = await TimeEntry.find(*conditions).sort(-TimeEntry.entry_date, -TimeEntry.created_at).to_list()
    
    # Filter by project if needed (join through task)
    if project_id:
        filtered_entries = []
        for entry in entries:
            task = await Task.get(entry.task_id)
            if task and task.project_id == project_id:
                filtered_entries.append(entry)
        return filtered_entries
    
    return entries


async def update_time_entry(
    tenant_id: str,
    time_entry_id: str,
    updates: Dict[str, Any]
) -> TimeEntry:
    """Update a time entry."""
    time_entry = await TimeEntry.find_one(
        TimeEntry.id == PydanticObjectId(time_entry_id),
        TimeEntry.tenant_id == tenant_id
    )
    if not time_entry:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Time entry not found"
        )
    
    for key, value in updates.items():
        if hasattr(time_entry, key):
            if key == "hours":
                setattr(time_entry, key, Decimal(str(value)))
            else:
                setattr(time_entry, key, value)
    
    time_entry.updated_at = datetime.utcnow()
    await time_entry.save()
    return time_entry


async def delete_time_entry(tenant_id: str, time_entry_id: str) -> None:
    """Delete a time entry."""
    time_entry = await TimeEntry.find_one(
        TimeEntry.id == PydanticObjectId(time_entry_id),
        TimeEntry.tenant_id == tenant_id
    )
    if not time_entry:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Time entry not found"
        )
    
    await time_entry.delete()


# ========== Time Tracker (Start/Stop) Operations ==========
async def start_time_tracker(
    tenant_id: str,
    user_id: str,
    task_id: Optional[str] = None,
    message: Optional[str] = None
) -> TimeTracker:
    """Start a time tracker session."""
    # Check if user has an active tracker
    active_tracker = await TimeTracker.find_one(
        TimeTracker.tenant_id == tenant_id,
        TimeTracker.user_id == user_id,
        TimeTracker.is_running == True
    )
    
    if active_tracker:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You already have an active time tracker. Stop it first."
        )
    
    # Verify task if provided
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
    
    tracker = TimeTracker(
        tenant_id=tenant_id,
        user_id=user_id,
        task_id=task_id,
        start_date_time=datetime.now(timezone.utc),
        message=message,
        is_running=True
    )
    await tracker.insert()
    return tracker


async def stop_time_tracker(
    tenant_id: str,
    tracker_id: str,
    user_id: Optional[str] = None
) -> TimeTracker:
    """Stop a time tracker and create time entry."""
    tracker = await TimeTracker.find_one(
        TimeTracker.id == PydanticObjectId(tracker_id),
        TimeTracker.tenant_id == tenant_id
    )
    if not tracker:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Time tracker not found"
        )
    
    if not tracker.is_running:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Time tracker is not running"
        )
    
    if user_id and tracker.user_id != user_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You can only stop your own time tracker"
        )
    
    # Calculate duration
    end_time = datetime.utcnow()
    duration = (end_time - tracker.start_date_time).total_seconds() / 3600  # Convert to hours
    
    tracker.end_date_time = end_time
    tracker.duration = Decimal(str(round(duration, 2)))
    tracker.is_running = False
    tracker.updated_at = datetime.utcnow()
    
    # Create time entry if task is associated
    if tracker.task_id:
        await create_time_entry(
            tenant_id,
            tracker.task_id,
            tracker.user_id,
            {
                "hours": tracker.duration,
                "date": end_time.date(),
                "description": tracker.message or f"Time tracked: {tracker.duration} hours",
                "is_billable": True
            }
        )
    
    await tracker.save()
    return tracker


async def get_active_tracker(tenant_id: str, user_id: str) -> Optional[TimeTracker]:
    """Get active time tracker for a user."""
    return await TimeTracker.find_one(
        TimeTracker.tenant_id == tenant_id,
        TimeTracker.user_id == user_id,
        TimeTracker.is_running == True
    )


async def list_time_trackers(
    tenant_id: str,
    user_id: Optional[str] = None,
    task_id: Optional[str] = None,
    is_running: Optional[bool] = None
) -> List[TimeTracker]:
    """List time trackers with filters."""
    conditions = [TimeTracker.tenant_id == tenant_id]
    
    if user_id:
        conditions.append(TimeTracker.user_id == user_id)
    
    if task_id:
        conditions.append(TimeTracker.task_id == task_id)
    
    if is_running is not None:
        conditions.append(TimeTracker.is_running == is_running)
    
    return await TimeTracker.find(*conditions).sort(-TimeTracker.start_date_time).to_list()


# ========== Time Reports & Analytics ==========
async def get_time_report(
    tenant_id: str,
    project_id: Optional[str] = None,
    user_id: Optional[str] = None,
    start_date: Optional[date] = None,
    end_date: Optional[date] = None
) -> Dict[str, Any]:
    """Generate time report with analytics."""
    conditions = [TimeEntry.tenant_id == tenant_id]
    
    if user_id:
        conditions.append(TimeEntry.user_id == user_id)
    
    if start_date:
        conditions.append(TimeEntry.entry_date >= start_date)
    
    if end_date:
        conditions.append(TimeEntry.entry_date <= end_date)
    
    entries = await TimeEntry.find(*conditions).to_list()
    
    # Filter by project if needed
    if project_id:
        filtered_entries = []
        for entry in entries:
            task = await Task.get(entry.task_id)
            if task and task.project_id == project_id:
                filtered_entries.append(entry)
        entries = filtered_entries
    
    # Aggregate data
    total_hours = Decimal("0")
    total_billable = Decimal("0")
    total_entries = 0
    by_user: Dict[str, Dict[str, Any]] = {}
    by_task: Dict[str, Dict[str, Any]] = {}
    
    for entry in entries:
        hours = entry.hours or Decimal("0")
        billable = hours if entry.is_billable else Decimal("0")
        
        total_hours += hours
        total_billable += billable
        total_entries += 1
        
        # By user
        user_key = str(entry.user_id)
        if user_key not in by_user:
            by_user[user_key] = {"total_hours": Decimal("0"), "billable_hours": Decimal("0"), "entry_count": 0}
        by_user[user_key]["total_hours"] += hours
        by_user[user_key]["billable_hours"] += billable
        by_user[user_key]["entry_count"] += 1
        
        # By task
        task_key = str(entry.task_id)
        if task_key not in by_task:
            by_task[task_key] = {"total_hours": Decimal("0"), "billable_hours": Decimal("0"), "entry_count": 0}
        by_task[task_key]["total_hours"] += hours
        by_task[task_key]["billable_hours"] += billable
        by_task[task_key]["entry_count"] += 1
    
    return {
        "summary": {
            "total_hours": float(total_hours),
            "billable_hours": float(total_billable),
            "non_billable_hours": float(total_hours - total_billable),
            "total_entries": total_entries
        },
        "by_user": {k: {**v, "total_hours": float(v["total_hours"]), "billable_hours": float(v["billable_hours"])} for k, v in by_user.items()},
        "by_task": {k: {**v, "total_hours": float(v["total_hours"]), "billable_hours": float(v["billable_hours"])} for k, v in by_task.items()}
    }


async def get_time_by_date_range(
    tenant_id: str,
    start_date: date,
    end_date: date,
    project_id: Optional[str] = None,
    user_id: Optional[str] = None
) -> List[Dict[str, Any]]:
    """Get time entries grouped by date."""
    conditions = [
        TimeEntry.tenant_id == tenant_id,
        TimeEntry.entry_date >= start_date,
        TimeEntry.entry_date <= end_date
    ]
    
    if user_id:
        conditions.append(TimeEntry.user_id == user_id)
    
    entries = await TimeEntry.find(*conditions).to_list()
    
    # Filter by project if needed
    if project_id:
        filtered_entries = []
        for entry in entries:
            task = await Task.get(entry.task_id)
            if task and task.project_id == project_id:
                filtered_entries.append(entry)
        entries = filtered_entries
    
    # Group by date
    by_date: Dict[str, Dict[str, Any]] = {}
    for entry in entries:
        date_key = str(entry.entry_date) if entry.entry_date else str(date.today())
        if date_key not in by_date:
            by_date[date_key] = {"total_hours": Decimal("0"), "entry_count": 0}
        by_date[date_key]["total_hours"] += entry.hours or Decimal("0")
        by_date[date_key]["entry_count"] += 1
    
    # Sort by date and format
    result = [
        {
            "date": date_key,
            "total_hours": float(data["total_hours"]),
            "entry_count": data["entry_count"]
        }
        for date_key, data in sorted(by_date.items())
    ]
    
    return result
