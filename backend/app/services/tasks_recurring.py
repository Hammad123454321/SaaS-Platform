"""
Recurring Tasks Service

Business logic for recurring task management operations.
All operations are tenant-isolated.
"""
import logging
from datetime import datetime, date, timedelta
from typing import Optional, List, Dict, Any
from dateutil.rrule import rrule, DAILY, WEEKLY, MONTHLY, YEARLY, MO, TU, WE, TH, FR, SA, SU
from dateutil.relativedelta import relativedelta

from sqlmodel import Session, select, and_
from fastapi import HTTPException, status

from app.models import Task
from app.models.tasks import RecurringTask

logger = logging.getLogger(__name__)


# ========== Recurring Task Operations ==========
def create_recurring_task(
    session: Session,
    tenant_id: int,
    task_id: int,
    recurrence_data: Dict[str, Any]
) -> RecurringTask:
    """Create a recurring task configuration."""
    # Verify task exists
    task = session.get(Task, task_id)
    if not task or task.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Task not found"
        )
    
    # Check if task already has recurring configuration
    existing = session.exec(
        select(RecurringTask).where(
            and_(
                RecurringTask.tenant_id == tenant_id,
                RecurringTask.task_id == task_id
            )
        )
    ).first()
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Task already has recurring configuration"
        )
    
    recurring = RecurringTask(
        tenant_id=tenant_id,
        task_id=task_id,
        recurrence_type=recurrence_data.get("recurrence_type", "daily"),
        recurrence_interval=recurrence_data.get("recurrence_interval", 1),
        days_of_week=recurrence_data.get("days_of_week"),
        day_of_month=recurrence_data.get("day_of_month"),
        week_of_month=recurrence_data.get("week_of_month"),
        weekday_of_month=recurrence_data.get("weekday_of_month"),
        month_of_year=recurrence_data.get("month_of_year"),
        custom_rule=recurrence_data.get("custom_rule"),
        end_date=recurrence_data.get("end_date"),
        max_occurrences=recurrence_data.get("max_occurrences"),
        is_active=recurrence_data.get("is_active", True),
    )
    
    session.add(recurring)
    session.commit()
    session.refresh(recurring)
    return recurring


def get_recurring_task(session: Session, tenant_id: int, task_id: int) -> Optional[RecurringTask]:
    """Get recurring task configuration for a task."""
    return session.exec(
        select(RecurringTask).where(
            and_(
                RecurringTask.tenant_id == tenant_id,
                RecurringTask.task_id == task_id
            )
        )
    ).first()


def list_recurring_tasks(
    session: Session,
    tenant_id: int,
    is_active: Optional[bool] = None
) -> List[RecurringTask]:
    """List all recurring tasks for a tenant."""
    query = select(RecurringTask).where(RecurringTask.tenant_id == tenant_id)
    
    if is_active is not None:
        query = query.where(RecurringTask.is_active == is_active)
    
    return list(session.exec(query.order_by(RecurringTask.created_at.desc())).all())


def update_recurring_task(
    session: Session,
    tenant_id: int,
    task_id: int,
    updates: Dict[str, Any]
) -> RecurringTask:
    """Update a recurring task configuration."""
    recurring = get_recurring_task(session, tenant_id, task_id)
    if not recurring:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Recurring task not found"
        )
    
    if "recurrence_type" in updates:
        recurring.recurrence_type = updates["recurrence_type"]
    if "recurrence_interval" in updates:
        recurring.recurrence_interval = updates["recurrence_interval"]
    if "days_of_week" in updates:
        recurring.days_of_week = updates["days_of_week"]
    if "day_of_month" in updates:
        recurring.day_of_month = updates["day_of_month"]
    if "week_of_month" in updates:
        recurring.week_of_month = updates["week_of_month"]
    if "weekday_of_month" in updates:
        recurring.weekday_of_month = updates["weekday_of_month"]
    if "month_of_year" in updates:
        recurring.month_of_year = updates["month_of_year"]
    if "custom_rule" in updates:
        recurring.custom_rule = updates["custom_rule"]
    if "end_date" in updates:
        recurring.end_date = updates["end_date"]
    if "max_occurrences" in updates:
        recurring.max_occurrences = updates["max_occurrences"]
    if "is_active" in updates:
        recurring.is_active = updates["is_active"]
    
    recurring.updated_at = datetime.utcnow()
    session.add(recurring)
    session.commit()
    session.refresh(recurring)
    return recurring


def delete_recurring_task(session: Session, tenant_id: int, task_id: int) -> None:
    """Delete a recurring task configuration."""
    recurring = get_recurring_task(session, tenant_id, task_id)
    if not recurring:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Recurring task not found"
        )
    
    session.delete(recurring)
    session.commit()


def generate_next_occurrence_date(recurring: RecurringTask, base_date: Optional[date] = None) -> Optional[date]:
    """Calculate the next occurrence date for a recurring task."""
    if not recurring.is_active:
        return None
    
    if base_date is None:
        base_date = date.today()
    
    # Check end conditions
    if recurring.end_date and base_date > recurring.end_date:
        return None
    
    if recurring.max_occurrences and recurring.occurrence_count >= recurring.max_occurrences:
        return None
    
    # Calculate next occurrence based on recurrence type
    if recurring.recurrence_type == "daily":
        return base_date + timedelta(days=recurring.recurrence_interval)
    
    elif recurring.recurrence_type == "weekly":
        if recurring.days_of_week:
            # Find next matching day of week
            days_map = {0: MO, 1: TU, 2: WE, 3: TH, 4: FR, 5: SA, 6: SU}
            weekdays = [days_map[d] for d in recurring.days_of_week if d in days_map]
            if weekdays:
                rule = rrule(WEEKLY, byweekday=weekdays, dtstart=base_date, count=1)
                next_date = rule.after(base_date)
                return next_date.date() if next_date else None
        return base_date + timedelta(weeks=recurring.recurrence_interval)
    
    elif recurring.recurrence_type == "monthly":
        if recurring.day_of_month:
            # Same day of month
            next_date = base_date + relativedelta(months=recurring.recurrence_interval)
            return next_date.replace(day=min(recurring.day_of_month, 28))  # Handle month-end
        elif recurring.week_of_month and recurring.weekday_of_month is not None:
            # Nth weekday of month
            next_date = base_date + relativedelta(months=recurring.recurrence_interval)
            # Calculate nth weekday logic here
            return next_date
        return base_date + relativedelta(months=recurring.recurrence_interval)
    
    elif recurring.recurrence_type == "yearly":
        if recurring.month_of_year and recurring.day_of_month:
            next_date = base_date + relativedelta(years=recurring.recurrence_interval)
            return next_date.replace(month=recurring.month_of_year, day=min(recurring.day_of_month, 28))
        return base_date + relativedelta(years=recurring.recurrence_interval)
    
    elif recurring.recurrence_type == "custom" and recurring.custom_rule:
        # Parse RRULE format (simplified)
        # In production, use a proper RRULE parser
        try:
            rule = rrule.rrulestr(recurring.custom_rule, dtstart=base_date)
            next_date = rule.after(base_date)
            return next_date.date() if next_date else None
        except Exception:
            logger.error(f"Failed to parse custom rule: {recurring.custom_rule}")
            return None
    
    return None


async def create_next_occurrence(
    session: Session,
    tenant_id: int,
    recurring: RecurringTask,
    user_id: int
) -> Optional[Task]:
    """Create the next occurrence of a recurring task."""
    from app.services.tasks import get_task, create_task
    
    # Get template task
    template_task = get_task(session, tenant_id, recurring.task_id)
    if not template_task:
        return None
    
    # Calculate next occurrence date
    base_date = recurring.last_created_at.date() if recurring.last_created_at else date.today()
    next_date = generate_next_occurrence_date(recurring, base_date)
    
    if not next_date:
        return None
    
    # Check if we should create it now
    if next_date > date.today():
        return None  # Not time yet
    
    # Create new task based on template
    task_data = {
        "title": template_task.title,
        "description": template_task.description,
        "notes": template_task.notes,
        "project_id": template_task.project_id,
        "status_id": template_task.status_id,
        "priority_id": template_task.priority_id,
        "task_list_id": template_task.task_list_id,
        "start_date": next_date,
        "due_date": next_date,  # Or calculate based on template
        "completion_percentage": 0,
    }
    
    new_task = create_task(session, tenant_id, user_id, task_data)
    
    # Copy assignees if any
    if template_task.assignees:
        assignee_ids = [a.id for a in template_task.assignees]
        from app.services.tasks import update_task
        update_task(session, tenant_id, new_task.id, {"assignee_ids": assignee_ids})
    
    # Update recurring task
    recurring.occurrence_count += 1
    recurring.last_created_at = datetime.utcnow()
    session.add(recurring)
    session.commit()
    
    return new_task


async def process_recurring_tasks(session: Session, tenant_id: Optional[int] = None) -> List[Task]:
    """Process all active recurring tasks and create next occurrences."""
    from app.models import User
    
    if tenant_id:
        recurring_tasks = list_recurring_tasks(session, tenant_id, is_active=True)
    else:
        # Process all tenants
        recurring_tasks = session.exec(
            select(RecurringTask).where(RecurringTask.is_active == True)  # noqa: E712
        ).all()
    
    created_tasks = []
    
    for recurring in recurring_tasks:
        # Get a user for the tenant
        user = session.exec(
            select(User).where(User.tenant_id == recurring.tenant_id).limit(1)
        ).first()
        
        if not user:
            continue
        
        try:
            new_task = await create_next_occurrence(session, recurring.tenant_id, recurring, user.id)
            if new_task:
                created_tasks.append(new_task)
        except Exception as e:
            logger.error(f"Failed to create occurrence for recurring task {recurring.id}: {e}")
    
    return created_tasks





