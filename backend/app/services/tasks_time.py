"""
Time Tracking Service

Handles time entries, time tracker (start/stop), and time reports/analytics.
"""
import logging
from datetime import datetime, date, timedelta, timezone
from typing import Optional, List, Dict, Any
from decimal import Decimal

from sqlmodel import Session, select, and_, or_, func
from fastapi import HTTPException, status

from app.models import Task, User, TimeEntry, TimeTracker, Project

logger = logging.getLogger(__name__)


# ========== Time Entry Operations ==========
def create_time_entry(
    session: Session,
    tenant_id: int,
    task_id: int,
    user_id: int,
    time_data: Dict[str, Any]
) -> TimeEntry:
    """Create a time entry for a task."""
    # Verify task exists
    task = session.get(Task, task_id)
    if not task or task.tenant_id != tenant_id:
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
    session.add(time_entry)
    session.commit()
    session.refresh(time_entry)
    return time_entry


def list_time_entries(
    session: Session,
    tenant_id: int,
    task_id: Optional[int] = None,
    user_id: Optional[int] = None,
    project_id: Optional[int] = None,
    start_date: Optional[date] = None,
    end_date: Optional[date] = None
) -> List[TimeEntry]:
    """List time entries with filters."""
    query = select(TimeEntry).where(TimeEntry.tenant_id == tenant_id)
    
    if task_id:
        query = query.where(TimeEntry.task_id == task_id)
    
    if user_id:
        query = query.where(TimeEntry.user_id == user_id)
    
    if project_id:
        # Join with tasks to filter by project
        query = query.join(Task).where(Task.project_id == project_id)
    
    if start_date:
        query = query.where(TimeEntry.entry_date >= start_date)
    
    if end_date:
        query = query.where(TimeEntry.entry_date <= end_date)
    
    return list(session.exec(query.order_by(TimeEntry.entry_date.desc(), TimeEntry.created_at.desc())).all())


def update_time_entry(
    session: Session,
    tenant_id: int,
    time_entry_id: int,
    updates: Dict[str, Any]
) -> TimeEntry:
    """Update a time entry."""
    time_entry = session.get(TimeEntry, time_entry_id)
    if not time_entry or time_entry.tenant_id != tenant_id:
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
    session.add(time_entry)
    session.commit()
    session.refresh(time_entry)
    return time_entry


def delete_time_entry(session: Session, tenant_id: int, time_entry_id: int) -> None:
    """Delete a time entry."""
    time_entry = session.get(TimeEntry, time_entry_id)
    if not time_entry or time_entry.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Time entry not found"
        )
    
    session.delete(time_entry)
    session.commit()


# ========== Time Tracker (Start/Stop) Operations ==========
def start_time_tracker(
    session: Session,
    tenant_id: int,
    user_id: int,
    task_id: Optional[int] = None,
    message: Optional[str] = None
) -> TimeTracker:
    """Start a time tracker session."""
    # Check if user has an active tracker
    active_tracker = session.exec(
        select(TimeTracker).where(
            and_(
                TimeTracker.tenant_id == tenant_id,
                TimeTracker.user_id == user_id,
                TimeTracker.is_running == True
            )
        )
    ).first()
    
    if active_tracker:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You already have an active time tracker. Stop it first."
        )
    
    # Verify task if provided
    if task_id:
        task = session.get(Task, task_id)
        if not task or task.tenant_id != tenant_id:
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
    session.add(tracker)
    session.commit()
    session.refresh(tracker)
    return tracker


def stop_time_tracker(
    session: Session,
    tenant_id: int,
    tracker_id: int,
    user_id: Optional[int] = None
) -> TimeTracker:
    """Stop a time tracker and create time entry."""
    tracker = session.get(TimeTracker, tracker_id)
    if not tracker or tracker.tenant_id != tenant_id:
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
        create_time_entry(
            session,
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
    
    session.add(tracker)
    session.commit()
    session.refresh(tracker)
    return tracker


def get_active_tracker(session: Session, tenant_id: int, user_id: int) -> Optional[TimeTracker]:
    """Get active time tracker for a user."""
    return session.exec(
        select(TimeTracker).where(
            and_(
                TimeTracker.tenant_id == tenant_id,
                TimeTracker.user_id == user_id,
                TimeTracker.is_running == True
            )
        )
    ).first()


def list_time_trackers(
    session: Session,
    tenant_id: int,
    user_id: Optional[int] = None,
    task_id: Optional[int] = None,
    is_running: Optional[bool] = None
) -> List[TimeTracker]:
    """List time trackers with filters."""
    query = select(TimeTracker).where(TimeTracker.tenant_id == tenant_id)
    
    if user_id:
        query = query.where(TimeTracker.user_id == user_id)
    
    if task_id:
        query = query.where(TimeTracker.task_id == task_id)
    
    if is_running is not None:
        query = query.where(TimeTracker.is_running == is_running)
    
    return list(session.exec(query.order_by(TimeTracker.start_date_time.desc())).all())


# ========== Time Reports & Analytics ==========
def get_time_report(
    session: Session,
    tenant_id: int,
    project_id: Optional[int] = None,
    user_id: Optional[int] = None,
    start_date: Optional[date] = None,
    end_date: Optional[date] = None
) -> Dict[str, Any]:
    """Generate time report with analytics."""
    query = select(
        TimeEntry.user_id,
        TimeEntry.task_id,
        func.sum(TimeEntry.hours).label("total_hours"),
        func.sum(func.case((TimeEntry.is_billable == True, TimeEntry.hours), else_=0)).label("billable_hours"),
        func.count(TimeEntry.id).label("entry_count")
    ).where(TimeEntry.tenant_id == tenant_id)
    
    if project_id:
        query = query.join(Task).where(Task.project_id == project_id)
    
    if user_id:
        query = query.where(TimeEntry.user_id == user_id)
    
    if start_date:
        query = query.where(TimeEntry.entry_date >= start_date)
    
    if end_date:
        query = query.where(TimeEntry.entry_date <= end_date)
    
    query = query.group_by(TimeEntry.user_id, TimeEntry.task_id)
    
    results = session.exec(query).all()
    
    # Aggregate data
    total_hours = Decimal("0")
    total_billable = Decimal("0")
    total_entries = 0
    by_user: Dict[int, Dict[str, Any]] = {}
    by_task: Dict[int, Dict[str, Any]] = {}
    
    for row in results:
        user_id_val = row[0]
        task_id_val = row[1]
        hours = Decimal(str(row[2] or 0))
        billable = Decimal(str(row[3] or 0))
        count = row[4] or 0
        
        total_hours += hours
        total_billable += billable
        total_entries += count
        
        # By user
        if user_id_val not in by_user:
            by_user[user_id_val] = {"total_hours": Decimal("0"), "billable_hours": Decimal("0"), "entry_count": 0}
        by_user[user_id_val]["total_hours"] += hours
        by_user[user_id_val]["billable_hours"] += billable
        by_user[user_id_val]["entry_count"] += count
        
        # By task
        if task_id_val not in by_task:
            by_task[task_id_val] = {"total_hours": Decimal("0"), "billable_hours": Decimal("0"), "entry_count": 0}
        by_task[task_id_val]["total_hours"] += hours
        by_task[task_id_val]["billable_hours"] += billable
        by_task[task_id_val]["entry_count"] += count
    
    return {
        "summary": {
            "total_hours": float(total_hours),
            "billable_hours": float(total_billable),
            "non_billable_hours": float(total_hours - total_billable),
            "total_entries": total_entries
        },
        "by_user": {str(k): {**v, "total_hours": float(v["total_hours"]), "billable_hours": float(v["billable_hours"])} for k, v in by_user.items()},
        "by_task": {str(k): {**v, "total_hours": float(v["total_hours"]), "billable_hours": float(v["billable_hours"])} for k, v in by_task.items()}
    }


def get_time_by_date_range(
    session: Session,
    tenant_id: int,
    start_date: date,
    end_date: date,
    project_id: Optional[int] = None,
    user_id: Optional[int] = None
) -> List[Dict[str, Any]]:
    """Get time entries grouped by date."""
    query = select(
        TimeEntry.date,
        func.sum(TimeEntry.hours).label("total_hours"),
        func.count(TimeEntry.id).label("entry_count")
    ).where(
        and_(
            TimeEntry.tenant_id == tenant_id,
            TimeEntry.date >= start_date,
            TimeEntry.date <= end_date
        )
    )
    
    if project_id:
        query = query.join(Task).where(Task.project_id == project_id)
    
    if user_id:
        query = query.where(TimeEntry.user_id == user_id)
    
    query = query.group_by(TimeEntry.date).order_by(TimeEntry.date)
    
    results = session.exec(query).all()
    
    return [
        {
            "date": str(row[0]),
            "total_hours": float(row[1] or 0),
            "entry_count": row[2] or 0
        }
        for row in results
    ]

