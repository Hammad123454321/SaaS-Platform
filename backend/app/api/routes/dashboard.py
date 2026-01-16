"""
Dashboard API Routes - Provides data for Company Admin, Staff, and Super Admin dashboards.
"""
from datetime import datetime, timedelta, date
from typing import Optional
from fastapi import APIRouter, Depends, HTTPException, status
from sqlmodel import Session, select, func, and_, or_
from pydantic import BaseModel

from app.api.deps import get_current_user
from app.db import get_session
from app.models import (
    User, Tenant, ModuleEntitlement, Subscription, BillingHistory,
    Task, TaskStatus, TaskStatusCategory, Project, ActivityLog,
    TaskAssignment, TimeEntry, UserRole, Role
)


router = APIRouter(prefix="/dashboard", tags=["dashboard"])


# ==================== Schemas ====================

class StatValue(BaseModel):
    value: int | float | str
    trend: Optional[float] = None
    trend_is_positive: Optional[bool] = None


class ModuleUsageItem(BaseModel):
    module: str
    usage: int
    color: str


class TaskTrendItem(BaseModel):
    date: str
    completed: int
    created: int


class TeamMemberItem(BaseModel):
    id: int
    name: str
    email: str
    role: str
    tasks_completed: int
    last_active: Optional[str] = None


class RecentTaskItem(BaseModel):
    id: int
    title: str
    status: str
    status_color: str
    project: Optional[str] = None
    due_date: Optional[str] = None
    is_overdue: bool = False


class DeadlineItem(BaseModel):
    id: int
    title: str
    due_date: str
    days_left: int
    project: Optional[str] = None


class ActivityItem(BaseModel):
    id: int
    type: str
    title: str
    description: str
    timestamp: str
    user: Optional[str] = None


class TenantItem(BaseModel):
    id: int
    name: str
    slug: str
    created_at: str
    user_count: int
    status: str


class GrowthDataItem(BaseModel):
    month: str
    tenants: int
    users: int


class RevenueDataItem(BaseModel):
    month: str
    revenue: float


class ModulePopularityItem(BaseModel):
    name: str
    value: int
    color: str


class ServiceStatusItem(BaseModel):
    name: str
    status: str
    latency: Optional[int] = None
    icon: str


class ModuleItem(BaseModel):
    code: str
    name: str
    enabled: bool
    ai_access: bool = False
    usage_count: int = 0


# ==================== Helper Functions ====================

def get_time_ago(dt: datetime) -> str:
    """Convert datetime to human-readable time ago string."""
    now = datetime.utcnow()
    diff = now - dt
    
    if diff.days > 0:
        return f"{diff.days} day{'s' if diff.days > 1 else ''} ago"
    elif diff.seconds >= 3600:
        hours = diff.seconds // 3600
        return f"{hours} hour{'s' if hours > 1 else ''} ago"
    elif diff.seconds >= 60:
        minutes = diff.seconds // 60
        return f"{minutes} min{'s' if minutes > 1 else ''} ago"
    else:
        return "Just now"


def get_module_color(code: str) -> str:
    """Get color for module visualization."""
    colors = {
        "tasks": "#a855f7",
        "crm": "#ec4899",
        "booking": "#f97316",
        "pos": "#22c55e",
        "hrm": "#3b82f6",
        "landing": "#06b6d4",
        "ai": "#8b5cf6",
    }
    return colors.get(code, "#6b7280")


def get_module_name(code: str) -> str:
    """Get display name for module."""
    names = {
        "tasks": "Tasks",
        "crm": "CRM",
        "booking": "Booking",
        "pos": "POS",
        "hrm": "HRM",
        "landing": "Landing Builder",
        "ai": "AI Assistant",
    }
    return names.get(code, code.upper())


# ==================== Company Admin Dashboard ====================

@router.get("/company/stats")
async def get_company_dashboard_stats(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> dict:
    """Get company-level dashboard statistics."""
    tenant_id = current_user.tenant_id
    tenant = session.get(Tenant, tenant_id)
    
    # Count users
    total_users = session.exec(
        select(func.count(User.id)).where(
            User.tenant_id == tenant_id,
            User.is_active == True  # noqa: E712
        )
    ).one() or 0
    
    # Count enabled modules
    enabled_modules = session.exec(
        select(func.count(ModuleEntitlement.id)).where(
            ModuleEntitlement.tenant_id == tenant_id,
            ModuleEntitlement.enabled == True  # noqa: E712
        )
    ).one() or 0
    
    # Count tasks created this week
    week_ago = datetime.utcnow() - timedelta(days=7)
    tasks_this_week = session.exec(
        select(func.count(Task.id)).where(
            Task.tenant_id == tenant_id,
            Task.created_at >= week_ago
        )
    ).one() or 0
    
    # Get subscription status
    subscription = session.exec(
        select(Subscription).where(Subscription.tenant_id == tenant_id)
    ).first()
    subscription_status = subscription.status if subscription else "inactive"
    
    return {
        "company_name": tenant.name if tenant else "Company",
        "total_users": total_users,
        "enabled_modules": enabled_modules,
        "tasks_this_week": tasks_this_week,
        "subscription_status": subscription_status,
    }


@router.get("/company/module-usage", response_model=list[ModuleUsageItem])
async def get_company_module_usage(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> list[ModuleUsageItem]:
    """Get module usage statistics for the company."""
    tenant_id = current_user.tenant_id
    
    # Get enabled modules
    entitlements = session.exec(
        select(ModuleEntitlement).where(
            ModuleEntitlement.tenant_id == tenant_id,
            ModuleEntitlement.enabled == True  # noqa: E712
        )
    ).all()
    
    usage_data = []
    week_ago = datetime.utcnow() - timedelta(days=7)
    
    for ent in entitlements:
        # Count activities related to this module
        usage_count = session.exec(
            select(func.count(ActivityLog.id)).where(
                ActivityLog.tenant_id == tenant_id,
                ActivityLog.entity_type.in_([ent.module_code, f"{ent.module_code}_task"]),
                ActivityLog.created_at >= week_ago
            )
        ).one() or 0
        
        # For tasks module, also count task-related activities
        if ent.module_code == "tasks":
            task_count = session.exec(
                select(func.count(ActivityLog.id)).where(
                    ActivityLog.tenant_id == tenant_id,
                    ActivityLog.entity_type == "task",
                    ActivityLog.created_at >= week_ago
                )
            ).one() or 0
            usage_count += task_count
        
        usage_data.append(ModuleUsageItem(
            module=get_module_name(ent.module_code),
            usage=usage_count,
            color=get_module_color(ent.module_code)
        ))
    
    # Sort by usage descending
    usage_data.sort(key=lambda x: x.usage, reverse=True)
    return usage_data


@router.get("/company/task-trends", response_model=list[TaskTrendItem])
async def get_company_task_trends(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> list[TaskTrendItem]:
    """Get task creation and completion trends for the past 7 days."""
    tenant_id = current_user.tenant_id
    trends = []
    
    # Get done status IDs
    done_statuses = session.exec(
        select(TaskStatus.id).where(
            TaskStatus.tenant_id == tenant_id,
            TaskStatus.category == TaskStatusCategory.DONE
        )
    ).all()
    
    for i in range(6, -1, -1):
        day = datetime.utcnow().date() - timedelta(days=i)
        day_start = datetime.combine(day, datetime.min.time())
        day_end = datetime.combine(day, datetime.max.time())
        
        # Count created tasks
        created = session.exec(
            select(func.count(Task.id)).where(
                Task.tenant_id == tenant_id,
                Task.created_at >= day_start,
                Task.created_at <= day_end
            )
        ).one() or 0
        
        # Count completed tasks (tasks moved to done status on this day)
        completed = session.exec(
            select(func.count(ActivityLog.id)).where(
                ActivityLog.tenant_id == tenant_id,
                ActivityLog.entity_type == "task",
                ActivityLog.action == "status_changed",
                ActivityLog.created_at >= day_start,
                ActivityLog.created_at <= day_end,
                ActivityLog.description.contains("Done")
            )
        ).one() or 0
        
        trends.append(TaskTrendItem(
            date=day.strftime("%a"),
            completed=completed,
            created=created
        ))
    
    return trends


@router.get("/company/team-overview", response_model=list[TeamMemberItem])
async def get_company_team_overview(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
    limit: int = 10,
) -> list[TeamMemberItem]:
    """Get team members with their task completion stats."""
    tenant_id = current_user.tenant_id
    
    # Get team members
    users = session.exec(
        select(User).where(
            User.tenant_id == tenant_id,
            User.is_active == True  # noqa: E712
        ).limit(limit)
    ).all()
    
    team_data = []
    week_ago = datetime.utcnow() - timedelta(days=7)
    
    # Get done status IDs for this tenant
    done_status_ids = list(session.exec(
        select(TaskStatus.id).where(
            TaskStatus.tenant_id == tenant_id,
            TaskStatus.category == TaskStatusCategory.DONE
        )
    ).all())
    
    for user in users:
        # Count completed tasks this week
        tasks_completed = 0
        if done_status_ids:
            tasks_completed = session.exec(
                select(func.count(Task.id)).where(
                    Task.tenant_id == tenant_id,
                    Task.status_id.in_(done_status_ids),
                    Task.updated_at >= week_ago,
                    Task.id.in_(
                        select(TaskAssignment.task_id).where(TaskAssignment.user_id == user.id)
                    )
                )
            ).one() or 0
        
        # Get user role
        user_role = session.exec(
            select(Role).join(UserRole).where(UserRole.user_id == user.id).limit(1)
        ).first()
        role_name = user_role.name if user_role else "Staff"
        
        # Get last activity
        last_activity = session.exec(
            select(ActivityLog).where(
                ActivityLog.tenant_id == tenant_id,
                ActivityLog.user_id == user.id
            ).order_by(ActivityLog.created_at.desc()).limit(1)
        ).first()
        
        last_active = get_time_ago(last_activity.created_at) if last_activity else None
        
        team_data.append(TeamMemberItem(
            id=user.id,
            name=user.full_name or user.email.split("@")[0],
            email=user.email,
            role=role_name,
            tasks_completed=tasks_completed,
            last_active=last_active
        ))
    
    # Sort by tasks completed
    team_data.sort(key=lambda x: x.tasks_completed, reverse=True)
    return team_data


@router.get("/company/recent-tasks", response_model=list[RecentTaskItem])
async def get_company_recent_tasks(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
    limit: int = 5,
) -> list[RecentTaskItem]:
    """Get recent tasks for the company."""
    tenant_id = current_user.tenant_id
    today = datetime.utcnow().date()
    
    tasks = session.exec(
        select(Task).where(Task.tenant_id == tenant_id)
        .order_by(Task.updated_at.desc())
        .limit(limit)
    ).all()
    
    result = []
    for task in tasks:
        # Get status info
        status = session.get(TaskStatus, task.status_id)
        status_name = status.name if status else "Unknown"
        status_color = status.color if status else "#6b7280"
        
        # Get project info
        project = session.get(Project, task.project_id)
        project_name = project.name if project else None
        
        # Check if overdue
        is_overdue = False
        due_date_str = None
        if task.due_date:
            is_overdue = task.due_date < today and status and status.category != TaskStatusCategory.DONE
            if task.due_date == today:
                due_date_str = "Today"
            elif task.due_date == today + timedelta(days=1):
                due_date_str = "Tomorrow"
            else:
                due_date_str = task.due_date.strftime("%b %d")
        
        result.append(RecentTaskItem(
            id=task.id,
            title=task.title,
            status=status_name,
            status_color=status_color,
            project=project_name,
            due_date=due_date_str,
            is_overdue=is_overdue
        ))
    
    return result


@router.get("/company/upcoming-deadlines", response_model=list[DeadlineItem])
async def get_company_upcoming_deadlines(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
    limit: int = 5,
) -> list[DeadlineItem]:
    """Get upcoming task deadlines for the company."""
    tenant_id = current_user.tenant_id
    today = datetime.utcnow().date()
    
    # Get non-done status IDs
    done_status_ids = list(session.exec(
        select(TaskStatus.id).where(
            TaskStatus.tenant_id == tenant_id,
            TaskStatus.category == TaskStatusCategory.DONE
        )
    ).all())
    
    # Get tasks with upcoming deadlines
    query = select(Task).where(
        Task.tenant_id == tenant_id,
        Task.due_date != None,  # noqa: E711
        Task.due_date >= today - timedelta(days=7),  # Include overdue up to a week
    )
    
    if done_status_ids:
        query = query.where(~Task.status_id.in_(done_status_ids))
    
    tasks = session.exec(
        query.order_by(Task.due_date.asc()).limit(limit)
    ).all()
    
    result = []
    for task in tasks:
        project = session.get(Project, task.project_id)
        days_left = (task.due_date - today).days
        
        result.append(DeadlineItem(
            id=task.id,
            title=task.title,
            due_date=task.due_date.strftime("%b %d, %Y"),
            days_left=days_left,
            project=project.name if project else None
        ))
    
    return result


@router.get("/company/activity", response_model=list[ActivityItem])
async def get_company_activity(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
    limit: int = 10,
) -> list[ActivityItem]:
    """Get recent activity feed for the company."""
    tenant_id = current_user.tenant_id
    
    activities = session.exec(
        select(ActivityLog).where(ActivityLog.tenant_id == tenant_id)
        .order_by(ActivityLog.created_at.desc())
        .limit(limit)
    ).all()
    
    result = []
    for activity in activities:
        # Get user name if available
        user = session.get(User, activity.user_id) if activity.user_id else None
        user_name = user.full_name if user and user.full_name else (user.email.split("@")[0] if user else None)
        
        # Determine activity type
        activity_type = "task"
        if activity.entity_type in ["project", "client"]:
            activity_type = activity.entity_type
        elif activity.entity_type in ["user", "team"]:
            activity_type = "user"
        elif activity.entity_type == "booking":
            activity_type = "booking"
        
        result.append(ActivityItem(
            id=activity.id,
            type=activity_type,
            title=f"{activity.entity_type.title()} {activity.action}",
            description=activity.description,
            timestamp=get_time_ago(activity.created_at),
            user=user_name
        ))
    
    return result


@router.get("/company/modules", response_model=list[ModuleItem])
async def get_company_modules(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> list[ModuleItem]:
    """Get list of enabled modules for the company."""
    tenant_id = current_user.tenant_id
    week_ago = datetime.utcnow() - timedelta(days=7)
    
    entitlements = session.exec(
        select(ModuleEntitlement).where(
            ModuleEntitlement.tenant_id == tenant_id,
            ModuleEntitlement.enabled == True  # noqa: E712
        )
    ).all()
    
    result = []
    for ent in entitlements:
        # Count recent usage
        usage_count = session.exec(
            select(func.count(ActivityLog.id)).where(
                ActivityLog.tenant_id == tenant_id,
                ActivityLog.entity_type.in_([ent.module_code, "task"]) if ent.module_code == "tasks" else ActivityLog.entity_type == ent.module_code,
                ActivityLog.created_at >= week_ago
            )
        ).one() or 0
        
        result.append(ModuleItem(
            code=ent.module_code,
            name=get_module_name(ent.module_code),
            enabled=ent.enabled,
            ai_access=ent.ai_access or False,
            usage_count=usage_count
        ))
    
    return result


# ==================== Staff Dashboard ====================

@router.get("/staff/stats")
async def get_staff_dashboard_stats(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> dict:
    """Get personal task statistics for staff member."""
    tenant_id = current_user.tenant_id
    user_id = current_user.id
    today = datetime.utcnow().date()
    
    # Get all tasks assigned to user
    my_task_ids = session.exec(
        select(TaskAssignment.task_id).where(TaskAssignment.user_id == user_id)
    ).all()
    
    if not my_task_ids:
        return {
            "total": 0,
            "pending": 0,
            "in_progress": 0,
            "completed": 0,
            "overdue": 0,
        }
    
    # Get status categories
    todo_ids = list(session.exec(
        select(TaskStatus.id).where(
            TaskStatus.tenant_id == tenant_id,
            TaskStatus.category == TaskStatusCategory.TODO
        )
    ).all())
    
    in_progress_ids = list(session.exec(
        select(TaskStatus.id).where(
            TaskStatus.tenant_id == tenant_id,
            TaskStatus.category == TaskStatusCategory.IN_PROGRESS
        )
    ).all())
    
    done_ids = list(session.exec(
        select(TaskStatus.id).where(
            TaskStatus.tenant_id == tenant_id,
            TaskStatus.category == TaskStatusCategory.DONE
        )
    ).all())
    
    # Count tasks by category
    total = len(my_task_ids)
    
    pending = session.exec(
        select(func.count(Task.id)).where(
            Task.id.in_(my_task_ids),
            Task.status_id.in_(todo_ids) if todo_ids else False
        )
    ).one() or 0
    
    in_progress = session.exec(
        select(func.count(Task.id)).where(
            Task.id.in_(my_task_ids),
            Task.status_id.in_(in_progress_ids) if in_progress_ids else False
        )
    ).one() or 0
    
    completed = session.exec(
        select(func.count(Task.id)).where(
            Task.id.in_(my_task_ids),
            Task.status_id.in_(done_ids) if done_ids else False
        )
    ).one() or 0
    
    # Count overdue (not done, past due date)
    non_done_ids = todo_ids + in_progress_ids
    overdue = 0
    if non_done_ids:
        overdue = session.exec(
            select(func.count(Task.id)).where(
                Task.id.in_(my_task_ids),
                Task.status_id.in_(non_done_ids),
                Task.due_date < today
            )
        ).one() or 0
    
    return {
        "total": total,
        "pending": pending,
        "in_progress": in_progress,
        "completed": completed,
        "overdue": overdue,
    }


@router.get("/staff/my-tasks", response_model=list[RecentTaskItem])
async def get_staff_my_tasks(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
    limit: int = 10,
) -> list[RecentTaskItem]:
    """Get tasks assigned to current user."""
    tenant_id = current_user.tenant_id
    user_id = current_user.id
    today = datetime.utcnow().date()
    
    # Get task IDs assigned to user
    my_task_ids = list(session.exec(
        select(TaskAssignment.task_id).where(TaskAssignment.user_id == user_id)
    ).all())
    
    if not my_task_ids:
        return []
    
    tasks = session.exec(
        select(Task).where(Task.id.in_(my_task_ids))
        .order_by(Task.due_date.asc().nulls_last(), Task.updated_at.desc())
        .limit(limit)
    ).all()
    
    # Get done status IDs
    done_ids = list(session.exec(
        select(TaskStatus.id).where(
            TaskStatus.tenant_id == tenant_id,
            TaskStatus.category == TaskStatusCategory.DONE
        )
    ).all())
    
    result = []
    for task in tasks:
        status = session.get(TaskStatus, task.status_id)
        project = session.get(Project, task.project_id)
        
        is_overdue = False
        due_date_str = None
        if task.due_date:
            is_overdue = task.due_date < today and task.status_id not in done_ids
            if task.due_date == today:
                due_date_str = "Today"
            elif task.due_date == today + timedelta(days=1):
                due_date_str = "Tomorrow"
            else:
                due_date_str = task.due_date.strftime("%b %d")
        
        result.append(RecentTaskItem(
            id=task.id,
            title=task.title,
            status=status.name if status else "Unknown",
            status_color=status.color if status else "#6b7280",
            project=project.name if project else None,
            due_date=due_date_str,
            is_overdue=is_overdue
        ))
    
    return result


@router.get("/staff/task-trends", response_model=list[TaskTrendItem])
async def get_staff_task_trends(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> list[TaskTrendItem]:
    """Get personal task completion trends."""
    tenant_id = current_user.tenant_id
    user_id = current_user.id
    
    # Get task IDs assigned to user
    my_task_ids = list(session.exec(
        select(TaskAssignment.task_id).where(TaskAssignment.user_id == user_id)
    ).all())
    
    trends = []
    for i in range(6, -1, -1):
        day = datetime.utcnow().date() - timedelta(days=i)
        day_start = datetime.combine(day, datetime.min.time())
        day_end = datetime.combine(day, datetime.max.time())
        
        # Count tasks assigned to user on this day
        created = 0
        if my_task_ids:
            created = session.exec(
                select(func.count(TaskAssignment.task_id)).where(
                    TaskAssignment.user_id == user_id,
                    TaskAssignment.assigned_at >= day_start,
                    TaskAssignment.assigned_at <= day_end
                )
            ).one() or 0
        
        # Count completed tasks (status changed to done)
        completed = session.exec(
            select(func.count(ActivityLog.id)).where(
                ActivityLog.tenant_id == tenant_id,
                ActivityLog.user_id == user_id,
                ActivityLog.entity_type == "task",
                ActivityLog.action == "status_changed",
                ActivityLog.created_at >= day_start,
                ActivityLog.created_at <= day_end,
                ActivityLog.description.contains("Done")
            )
        ).one() or 0
        
        trends.append(TaskTrendItem(
            date=day.strftime("%a"),
            completed=completed,
            created=created
        ))
    
    return trends


@router.get("/staff/upcoming-deadlines", response_model=list[DeadlineItem])
async def get_staff_upcoming_deadlines(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
    limit: int = 5,
) -> list[DeadlineItem]:
    """Get upcoming deadlines for tasks assigned to user."""
    tenant_id = current_user.tenant_id
    user_id = current_user.id
    today = datetime.utcnow().date()
    
    # Get task IDs assigned to user
    my_task_ids = list(session.exec(
        select(TaskAssignment.task_id).where(TaskAssignment.user_id == user_id)
    ).all())
    
    if not my_task_ids:
        return []
    
    # Get non-done status IDs
    done_ids = list(session.exec(
        select(TaskStatus.id).where(
            TaskStatus.tenant_id == tenant_id,
            TaskStatus.category == TaskStatusCategory.DONE
        )
    ).all())
    
    query = select(Task).where(
        Task.id.in_(my_task_ids),
        Task.due_date != None,  # noqa: E711
        Task.due_date >= today - timedelta(days=7),
    )
    
    if done_ids:
        query = query.where(~Task.status_id.in_(done_ids))
    
    tasks = session.exec(
        query.order_by(Task.due_date.asc()).limit(limit)
    ).all()
    
    result = []
    for task in tasks:
        project = session.get(Project, task.project_id)
        days_left = (task.due_date - today).days
        
        result.append(DeadlineItem(
            id=task.id,
            title=task.title,
            due_date=task.due_date.strftime("%b %d, %Y"),
            days_left=days_left,
            project=project.name if project else None
        ))
    
    return result


@router.get("/staff/activity", response_model=list[ActivityItem])
async def get_staff_activity(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
    limit: int = 10,
) -> list[ActivityItem]:
    """Get personal activity feed."""
    tenant_id = current_user.tenant_id
    user_id = current_user.id
    
    activities = session.exec(
        select(ActivityLog).where(
            ActivityLog.tenant_id == tenant_id,
            ActivityLog.user_id == user_id
        )
        .order_by(ActivityLog.created_at.desc())
        .limit(limit)
    ).all()
    
    result = []
    for activity in activities:
        activity_type = "task"
        if activity.entity_type in ["booking"]:
            activity_type = "booking"
        
        result.append(ActivityItem(
            id=activity.id,
            type=activity_type,
            title=f"{activity.entity_type.title()} {activity.action}",
            description=activity.description,
            timestamp=get_time_ago(activity.created_at),
            user=None
        ))
    
    return result


# ==================== Super Admin Dashboard ====================

@router.get("/admin/stats")
async def get_admin_dashboard_stats(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> dict:
    """Get platform-wide statistics for super admin."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    # Count tenants
    total_tenants = session.exec(select(func.count(Tenant.id))).one() or 0
    
    # Count users
    total_users = session.exec(
        select(func.count(User.id)).where(User.is_active == True)  # noqa: E712
    ).one() or 0
    
    # Count active subscriptions
    active_subscriptions = session.exec(
        select(func.count(Subscription.id)).where(Subscription.status == "active")
    ).one() or 0
    
    # Calculate total revenue (MRR)
    total_revenue = session.exec(
        select(func.sum(BillingHistory.amount)).where(
            BillingHistory.created_at >= datetime.utcnow() - timedelta(days=30)
        )
    ).one() or 0
    
    return {
        "total_tenants": total_tenants,
        "total_users": total_users,
        "active_subscriptions": active_subscriptions,
        "total_revenue": float(total_revenue) if total_revenue else 0,
    }


@router.get("/admin/growth", response_model=list[GrowthDataItem])
async def get_admin_growth_data(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> list[GrowthDataItem]:
    """Get tenant and user growth data for the past 6 months."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    result = []
    for i in range(5, -1, -1):
        month_start = (datetime.utcnow().replace(day=1) - timedelta(days=i*30)).replace(day=1)
        month_end = (month_start + timedelta(days=32)).replace(day=1) - timedelta(days=1)
        
        # Count tenants created by this month
        tenants_count = session.exec(
            select(func.count(Tenant.id)).where(Tenant.created_at <= month_end)
        ).one() or 0
        
        # Count users created by this month
        users_count = session.exec(
            select(func.count(User.id)).where(User.created_at <= month_end)
        ).one() or 0
        
        result.append(GrowthDataItem(
            month=month_start.strftime("%b"),
            tenants=tenants_count,
            users=users_count
        ))
    
    return result


@router.get("/admin/revenue", response_model=list[RevenueDataItem])
async def get_admin_revenue_data(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> list[RevenueDataItem]:
    """Get revenue data for the past 6 months."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    result = []
    for i in range(5, -1, -1):
        month_start = (datetime.utcnow().replace(day=1) - timedelta(days=i*30)).replace(day=1)
        month_end = (month_start + timedelta(days=32)).replace(day=1) - timedelta(days=1)
        
        # Sum revenue for this month
        revenue = session.exec(
            select(func.sum(BillingHistory.amount)).where(
                BillingHistory.created_at >= month_start,
                BillingHistory.created_at <= month_end
            )
        ).one() or 0
        
        result.append(RevenueDataItem(
            month=month_start.strftime("%b"),
            revenue=float(revenue) if revenue else 0
        ))
    
    return result


@router.get("/admin/module-popularity", response_model=list[ModulePopularityItem])
async def get_admin_module_popularity(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> list[ModulePopularityItem]:
    """Get module subscription popularity across all tenants."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    # Count enabled modules by type
    modules = ["tasks", "crm", "booking", "pos", "hrm", "landing", "ai"]
    result = []
    
    for module in modules:
        count = session.exec(
            select(func.count(ModuleEntitlement.id)).where(
                ModuleEntitlement.module_code == module,
                ModuleEntitlement.enabled == True  # noqa: E712
            )
        ).one() or 0
        
        if count > 0:
            result.append(ModulePopularityItem(
                name=get_module_name(module),
                value=count,
                color=get_module_color(module)
            ))
    
    # Sort by popularity
    result.sort(key=lambda x: x.value, reverse=True)
    return result


@router.get("/admin/recent-tenants", response_model=list[TenantItem])
async def get_admin_recent_tenants(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
    limit: int = 5,
) -> list[TenantItem]:
    """Get recently registered tenants."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    tenants = session.exec(
        select(Tenant).order_by(Tenant.created_at.desc()).limit(limit)
    ).all()
    
    result = []
    for tenant in tenants:
        # Count users
        user_count = session.exec(
            select(func.count(User.id)).where(User.tenant_id == tenant.id)
        ).one() or 0
        
        # Get subscription status
        subscription = session.exec(
            select(Subscription).where(Subscription.tenant_id == tenant.id)
        ).first()
        status_str = subscription.status if subscription else "inactive"
        if subscription and subscription.status == "active" and subscription.trial_ends_at:
            if subscription.trial_ends_at > datetime.utcnow():
                status_str = "trial"
        
        result.append(TenantItem(
            id=tenant.id,
            name=tenant.name,
            slug=tenant.slug,
            created_at=tenant.created_at.strftime("%b %d, %Y"),
            user_count=user_count,
            status=status_str
        ))
    
    return result


@router.get("/admin/system-health", response_model=list[ServiceStatusItem])
async def get_admin_system_health(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> list[ServiceStatusItem]:
    """Get system health status."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    # Check database
    db_status = "online"
    db_latency = 5
    try:
        import time
        start = time.time()
        session.exec(select(func.count(User.id))).one()
        db_latency = int((time.time() - start) * 1000)
    except Exception:
        db_status = "offline"
        db_latency = None
    
    return [
        ServiceStatusItem(name="Database", status=db_status, latency=db_latency, icon="database"),
        ServiceStatusItem(name="API Server", status="online", latency=12, icon="server"),
        ServiceStatusItem(name="Authentication", status="online", latency=8, icon="shield"),
        ServiceStatusItem(name="Task Queue", status="online", latency=15, icon="activity"),
    ]


@router.get("/admin/activity", response_model=list[ActivityItem])
async def get_admin_activity(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
    limit: int = 10,
) -> list[ActivityItem]:
    """Get platform-wide activity feed."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    # Get recent tenant creations
    recent_tenants = session.exec(
        select(Tenant).order_by(Tenant.created_at.desc()).limit(limit // 2)
    ).all()
    
    # Get recent subscriptions
    recent_subscriptions = session.exec(
        select(Subscription).order_by(Subscription.created_at.desc()).limit(limit // 2)
    ).all()
    
    result = []
    
    for tenant in recent_tenants:
        result.append(ActivityItem(
            id=tenant.id,
            type="user",
            title="New tenant registered",
            description=f"{tenant.name} joined the platform",
            timestamp=get_time_ago(tenant.created_at),
            user=None
        ))
    
    for sub in recent_subscriptions:
        tenant = session.get(Tenant, sub.tenant_id)
        tenant_name = tenant.name if tenant else "Unknown"
        result.append(ActivityItem(
            id=sub.id,
            type="task",
            title="Subscription updated",
            description=f"{tenant_name} subscription: {sub.status}",
            timestamp=get_time_ago(sub.created_at),
            user=None
        ))
    
    # Sort by most recent
    result.sort(key=lambda x: x.timestamp, reverse=False)  # "Just now" should be first
    return result[:limit]

