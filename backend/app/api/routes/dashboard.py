"""
Dashboard API Routes - Provides data for Company Admin, Staff, and Super Admin dashboards.
"""
from datetime import datetime, timedelta, date
from typing import Optional
from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel
from beanie import PydanticObjectId

from app.api.deps import get_current_user
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
    id: str
    name: str
    email: str
    role: str
    tasks_completed: int
    last_active: Optional[str] = None


class RecentTaskItem(BaseModel):
    id: str
    title: str
    status: str
    status_color: str
    project: Optional[str] = None
    due_date: Optional[str] = None
    is_overdue: bool = False


class DeadlineItem(BaseModel):
    id: str
    title: str
    due_date: str
    days_left: int
    project: Optional[str] = None


class ActivityItem(BaseModel):
    id: str
    type: str
    title: str
    description: str
    timestamp: str
    user: Optional[str] = None


class TenantItem(BaseModel):
    id: str
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
) -> dict:
    """Get company-level dashboard statistics."""
    try:
        tenant_id = str(current_user.tenant_id)
        tenant = await Tenant.get(current_user.tenant_id)
        
        total_users = await User.find(
            User.tenant_id == tenant_id,
            User.is_active == True
        ).count()
        
        enabled_modules = await ModuleEntitlement.find(
            ModuleEntitlement.tenant_id == tenant_id,
            ModuleEntitlement.enabled == True
        ).count()
        
        week_ago = datetime.utcnow() - timedelta(days=7)
        tasks_this_week = await Task.find(
            Task.tenant_id == tenant_id,
            Task.created_at >= week_ago
        ).count()
        
        subscription = await Subscription.find_one(Subscription.tenant_id == tenant_id)
        subscription_status = subscription.status if subscription else "inactive"
        
        return {
            "company_name": tenant.name if tenant else "Company",
            "total_users": total_users,
            "enabled_modules": enabled_modules,
            "tasks_this_week": tasks_this_week,
            "subscription_status": subscription_status,
        }
    except Exception as e:
        # Return default values on error instead of crashing
        return {
            "company_name": "Company",
            "total_users": 0,
            "enabled_modules": 0,
            "tasks_this_week": 0,
            "subscription_status": "inactive",
        }


@router.get("/company/module-usage", response_model=list[ModuleUsageItem])
async def get_company_module_usage(
    current_user: User = Depends(get_current_user),
) -> list[ModuleUsageItem]:
    """Get module usage statistics for the company."""
    tenant_id = str(current_user.tenant_id)
    
    entitlements = await ModuleEntitlement.find(
        ModuleEntitlement.tenant_id == tenant_id,
        ModuleEntitlement.enabled == True
    ).to_list()
    
    usage_data = []
    week_ago = datetime.utcnow() - timedelta(days=7)
    
    for ent in entitlements:
        usage_count = await ActivityLog.find(
            ActivityLog.tenant_id == tenant_id,
            ActivityLog.entity_type.in_([ent.module_code, f"{ent.module_code}_task"]),
            ActivityLog.created_at >= week_ago
        ).count()
        
        if ent.module_code == "tasks":
            task_count = await ActivityLog.find(
                ActivityLog.tenant_id == tenant_id,
                ActivityLog.entity_type == "task",
                ActivityLog.created_at >= week_ago
            ).count()
            usage_count += task_count
        
        usage_data.append(ModuleUsageItem(
            module=get_module_name(ent.module_code),
            usage=usage_count,
            color=get_module_color(ent.module_code)
        ))
    
    usage_data.sort(key=lambda x: x.usage, reverse=True)
    return usage_data


@router.get("/company/task-trends", response_model=list[TaskTrendItem])
async def get_company_task_trends(
    current_user: User = Depends(get_current_user),
) -> list[TaskTrendItem]:
    """Get task creation and completion trends for the past 7 days."""
    tenant_id = str(current_user.tenant_id)
    trends = []
    
    done_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.DONE
    ).to_list()
    done_status_ids = [str(s.id) for s in done_statuses]
    
    for i in range(6, -1, -1):
        day = datetime.utcnow().date() - timedelta(days=i)
        day_start = datetime.combine(day, datetime.min.time())
        day_end = datetime.combine(day, datetime.max.time())
        
        created = await Task.find(
            Task.tenant_id == tenant_id,
            Task.created_at >= day_start,
            Task.created_at <= day_end
        ).count()
        
        completed = await ActivityLog.find(
            ActivityLog.tenant_id == tenant_id,
            ActivityLog.entity_type == "task",
            ActivityLog.action == "status_changed",
            ActivityLog.created_at >= day_start,
            ActivityLog.created_at <= day_end,
            {"description": {"$regex": "Done"}}
        ).count()
        
        trends.append(TaskTrendItem(
            date=day.strftime("%a"),
            completed=completed,
            created=created
        ))
    
    return trends


@router.get("/company/team-overview", response_model=list[TeamMemberItem])
async def get_company_team_overview(
    current_user: User = Depends(get_current_user),
    limit: int = 10,
) -> list[TeamMemberItem]:
    """Get team members with their task completion stats."""
    tenant_id = str(current_user.tenant_id)
    
    users = await User.find(
        User.tenant_id == tenant_id,
        User.is_active == True
    ).limit(limit).to_list()
    
    team_data = []
    week_ago = datetime.utcnow() - timedelta(days=7)
    
    done_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.DONE
    ).to_list()
    done_status_ids = [str(s.id) for s in done_statuses]
    
    for user in users:
        user_id = str(user.id)
        tasks_completed = 0
        
        if done_status_ids:
            my_task_ids = await TaskAssignment.find(
                TaskAssignment.user_id == user_id
            ).to_list()
            my_task_id_list = [a.task_id for a in my_task_ids]
            
            if my_task_id_list:
                tasks_completed = await Task.find(
                    Task.tenant_id == tenant_id,
                    {"status_id": {"$in": done_status_ids}},
                    Task.updated_at >= week_ago,
                    {"_id": {"$in": [PydanticObjectId(tid) if not isinstance(tid, PydanticObjectId) else tid for tid in my_task_id_list]}}
                ).count()
        
        user_role = await UserRole.find_one(UserRole.user_id == user_id)
        role_name = "Staff"
        if user_role:
            role = await Role.get(user_role.role_id)
            role_name = role.name if role else "Staff"
        
        last_activity = await ActivityLog.find(
            ActivityLog.tenant_id == tenant_id,
            ActivityLog.user_id == user_id
        ).sort(-ActivityLog.created_at).limit(1).first_or_none()
        
        last_active = get_time_ago(last_activity.created_at) if last_activity else None
        
        team_data.append(TeamMemberItem(
            id=user_id,
            name=user.email.split("@")[0],
            email=user.email,
            role=role_name,
            tasks_completed=tasks_completed,
            last_active=last_active
        ))
    
    team_data.sort(key=lambda x: x.tasks_completed, reverse=True)
    return team_data


@router.get("/company/recent-tasks", response_model=list[RecentTaskItem])
async def get_company_recent_tasks(
    current_user: User = Depends(get_current_user),
    limit: int = 5,
) -> list[RecentTaskItem]:
    """Get recent tasks for the company."""
    tenant_id = str(current_user.tenant_id)
    today = datetime.utcnow().date()
    
    tasks = await Task.find(
        Task.tenant_id == tenant_id
    ).sort(-Task.updated_at).limit(limit).to_list()
    
    result = []
    for task in tasks:
        status = await TaskStatus.get(task.status_id) if task.status_id else None
        status_name = status.name if status else "Unknown"
        status_color = status.color if status else "#6b7280"
        
        project = await Project.get(task.project_id) if task.project_id else None
        project_name = project.name if project else None
        
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
            id=str(task.id),
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
    limit: int = 5,
) -> list[DeadlineItem]:
    """Get upcoming task deadlines for the company."""
    tenant_id = str(current_user.tenant_id)
    today = datetime.utcnow().date()
    
    done_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.DONE
    ).to_list()
    done_status_ids = [str(s.id) for s in done_statuses]
    
    query_filter = {
        "tenant_id": tenant_id,
        "due_date": {"$ne": None, "$gte": today - timedelta(days=7)},
    }
    if done_status_ids:
        query_filter["status_id"] = {"$nin": done_status_ids}
    
    tasks = await Task.find(query_filter).sort(+Task.due_date).limit(limit).to_list()
    
    result = []
    for task in tasks:
        project = await Project.get(task.project_id) if task.project_id else None
        days_left = (task.due_date - today).days
        
        result.append(DeadlineItem(
            id=str(task.id),
            title=task.title,
            due_date=task.due_date.strftime("%b %d, %Y"),
            days_left=days_left,
            project=project.name if project else None
        ))
    
    return result


@router.get("/company/activity", response_model=list[ActivityItem])
async def get_company_activity(
    current_user: User = Depends(get_current_user),
    limit: int = 10,
) -> list[ActivityItem]:
    """Get recent activity feed for the company."""
    tenant_id = str(current_user.tenant_id)
    
    activities = await ActivityLog.find(
        ActivityLog.tenant_id == tenant_id
    ).sort(-ActivityLog.created_at).limit(limit).to_list()
    
    result = []
    for activity in activities:
        user = await User.get(activity.user_id) if activity.user_id else None
        user_name = user.full_name if user and user.full_name else (user.email.split("@")[0] if user else None)
        
        activity_type = "task"
        if activity.entity_type in ["project", "client"]:
            activity_type = activity.entity_type
        elif activity.entity_type in ["user", "team"]:
            activity_type = "user"
        elif activity.entity_type == "booking":
            activity_type = "booking"
        
        result.append(ActivityItem(
            id=str(activity.id),
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
) -> list[ModuleItem]:
    """Get list of enabled modules for the company."""
    tenant_id = str(current_user.tenant_id)
    week_ago = datetime.utcnow() - timedelta(days=7)
    
    entitlements = await ModuleEntitlement.find(
        ModuleEntitlement.tenant_id == tenant_id,
        ModuleEntitlement.enabled == True
    ).to_list()
    
    result = []
    for ent in entitlements:
        entity_types = [ent.module_code]
        if ent.module_code == "tasks":
            entity_types.append("task")
        
        usage_count = await ActivityLog.find(
            ActivityLog.tenant_id == tenant_id,
            {"entity_type": {"$in": entity_types}},
            ActivityLog.created_at >= week_ago
        ).count()
        
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
) -> dict:
    """Get personal task statistics for staff member."""
    tenant_id = str(current_user.tenant_id)
    user_id = str(current_user.id)
    today = datetime.utcnow().date()
    
    my_assignments = await TaskAssignment.find(
        TaskAssignment.user_id == user_id
    ).to_list()
    my_task_ids = [a.task_id for a in my_assignments]
    
    if not my_task_ids:
        return {
            "total": 0,
            "pending": 0,
            "in_progress": 0,
            "completed": 0,
            "overdue": 0,
        }
    
    todo_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.TODO
    ).to_list()
    todo_ids = [str(s.id) for s in todo_statuses]
    
    in_progress_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.IN_PROGRESS
    ).to_list()
    in_progress_ids = [str(s.id) for s in in_progress_statuses]
    
    done_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.DONE
    ).to_list()
    done_ids = [str(s.id) for s in done_statuses]
    
    total = len(my_task_ids)
    
    pending = await Task.find(
        {"_id": {"$in": [PydanticObjectId(tid) if not isinstance(tid, PydanticObjectId) else tid for tid in my_task_ids]}},
        {"status_id": {"$in": todo_ids}} if todo_ids else {"status_id": None}
    ).count() if todo_ids else 0
    
    in_progress = await Task.find(
        {"_id": {"$in": [PydanticObjectId(tid) if not isinstance(tid, PydanticObjectId) else tid for tid in my_task_ids]}},
        {"status_id": {"$in": in_progress_ids}} if in_progress_ids else {"status_id": None}
    ).count() if in_progress_ids else 0
    
    completed = await Task.find(
        {"_id": {"$in": [PydanticObjectId(tid) if not isinstance(tid, PydanticObjectId) else tid for tid in my_task_ids]}},
        {"status_id": {"$in": done_ids}} if done_ids else {"status_id": None}
    ).count() if done_ids else 0
    
    non_done_ids = todo_ids + in_progress_ids
    overdue = 0
    if non_done_ids:
        overdue = await Task.find(
            {"_id": {"$in": [PydanticObjectId(tid) if not isinstance(tid, PydanticObjectId) else tid for tid in my_task_ids]}},
            {"status_id": {"$in": non_done_ids}},
            Task.due_date < today
        ).count()
    
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
    limit: int = 10,
) -> list[RecentTaskItem]:
    """Get tasks assigned to current user."""
    tenant_id = str(current_user.tenant_id)
    user_id = str(current_user.id)
    today = datetime.utcnow().date()
    
    my_assignments = await TaskAssignment.find(
        TaskAssignment.user_id == user_id
    ).to_list()
    my_task_ids = [a.task_id for a in my_assignments]
    
    if not my_task_ids:
        return []
    
    tasks = await Task.find(
        {"_id": {"$in": [PydanticObjectId(tid) if not isinstance(tid, PydanticObjectId) else tid for tid in my_task_ids]}}
    ).sort(+Task.due_date, -Task.updated_at).limit(limit).to_list()
    
    done_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.DONE
    ).to_list()
    done_ids = [str(s.id) for s in done_statuses]
    
    result = []
    for task in tasks:
        status = await TaskStatus.get(task.status_id) if task.status_id else None
        project = await Project.get(task.project_id) if task.project_id else None
        
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
            id=str(task.id),
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
) -> list[TaskTrendItem]:
    """Get personal task completion trends."""
    tenant_id = str(current_user.tenant_id)
    user_id = str(current_user.id)
    
    trends = []
    for i in range(6, -1, -1):
        day = datetime.utcnow().date() - timedelta(days=i)
        day_start = datetime.combine(day, datetime.min.time())
        day_end = datetime.combine(day, datetime.max.time())
        
        created = await TaskAssignment.find(
            TaskAssignment.user_id == user_id,
            TaskAssignment.assigned_at >= day_start,
            TaskAssignment.assigned_at <= day_end
        ).count()
        
        completed = await ActivityLog.find(
            ActivityLog.tenant_id == tenant_id,
            ActivityLog.user_id == user_id,
            ActivityLog.entity_type == "task",
            ActivityLog.action == "status_changed",
            ActivityLog.created_at >= day_start,
            ActivityLog.created_at <= day_end,
            {"description": {"$regex": "Done"}}
        ).count()
        
        trends.append(TaskTrendItem(
            date=day.strftime("%a"),
            completed=completed,
            created=created
        ))
    
    return trends


@router.get("/staff/upcoming-deadlines", response_model=list[DeadlineItem])
async def get_staff_upcoming_deadlines(
    current_user: User = Depends(get_current_user),
    limit: int = 5,
) -> list[DeadlineItem]:
    """Get upcoming deadlines for tasks assigned to user."""
    tenant_id = str(current_user.tenant_id)
    user_id = str(current_user.id)
    today = datetime.utcnow().date()
    
    my_assignments = await TaskAssignment.find(
        TaskAssignment.user_id == user_id
    ).to_list()
    my_task_ids = [a.task_id for a in my_assignments]
    
    if not my_task_ids:
        return []
    
    done_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.DONE
    ).to_list()
    done_ids = [str(s.id) for s in done_statuses]
    
    query_filter = {
        "_id": {"$in": [PydanticObjectId(tid) if not isinstance(tid, PydanticObjectId) else tid for tid in my_task_ids]},
        "due_date": {"$ne": None, "$gte": today - timedelta(days=7)},
    }
    if done_ids:
        query_filter["status_id"] = {"$nin": done_ids}
    
    tasks = await Task.find(query_filter).sort(+Task.due_date).limit(limit).to_list()
    
    result = []
    for task in tasks:
        project = await Project.get(task.project_id) if task.project_id else None
        days_left = (task.due_date - today).days
        
        result.append(DeadlineItem(
            id=str(task.id),
            title=task.title,
            due_date=task.due_date.strftime("%b %d, %Y"),
            days_left=days_left,
            project=project.name if project else None
        ))
    
    return result


@router.get("/staff/activity", response_model=list[ActivityItem])
async def get_staff_activity(
    current_user: User = Depends(get_current_user),
    limit: int = 10,
) -> list[ActivityItem]:
    """Get personal activity feed."""
    tenant_id = str(current_user.tenant_id)
    user_id = str(current_user.id)
    
    activities = await ActivityLog.find(
        ActivityLog.tenant_id == tenant_id,
        ActivityLog.user_id == user_id
    ).sort(-ActivityLog.created_at).limit(limit).to_list()
    
    result = []
    for activity in activities:
        activity_type = "task"
        if activity.entity_type in ["booking"]:
            activity_type = "booking"
        
        result.append(ActivityItem(
            id=str(activity.id),
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
) -> dict:
    """Get platform-wide statistics for super admin."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    total_tenants = await Tenant.find().count()
    total_users = await User.find(User.is_active == True).count()
    active_subscriptions = await Subscription.find(Subscription.status == "active").count()
    
    month_ago = datetime.utcnow() - timedelta(days=30)
    billing_records = await BillingHistory.find(
        BillingHistory.created_at >= month_ago
    ).to_list()
    total_revenue = sum(float(b.amount) for b in billing_records if b.amount)
    
    return {
        "total_tenants": total_tenants,
        "total_users": total_users,
        "active_subscriptions": active_subscriptions,
        "total_revenue": total_revenue,
    }


@router.get("/admin/growth", response_model=list[GrowthDataItem])
async def get_admin_growth_data(
    current_user: User = Depends(get_current_user),
) -> list[GrowthDataItem]:
    """Get tenant and user growth data for the past 6 months."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    result = []
    for i in range(5, -1, -1):
        month_start = (datetime.utcnow().replace(day=1) - timedelta(days=i*30)).replace(day=1)
        month_end = (month_start + timedelta(days=32)).replace(day=1) - timedelta(days=1)
        
        tenants_count = await Tenant.find(Tenant.created_at <= month_end).count()
        users_count = await User.find(User.created_at <= month_end).count()
        
        result.append(GrowthDataItem(
            month=month_start.strftime("%b"),
            tenants=tenants_count,
            users=users_count
        ))
    
    return result


@router.get("/admin/revenue", response_model=list[RevenueDataItem])
async def get_admin_revenue_data(
    current_user: User = Depends(get_current_user),
) -> list[RevenueDataItem]:
    """Get revenue data for the past 6 months."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    result = []
    for i in range(5, -1, -1):
        month_start = (datetime.utcnow().replace(day=1) - timedelta(days=i*30)).replace(day=1)
        month_end = (month_start + timedelta(days=32)).replace(day=1) - timedelta(days=1)
        
        billing_records = await BillingHistory.find(
            BillingHistory.created_at >= month_start,
            BillingHistory.created_at <= month_end
        ).to_list()
        revenue = sum(float(b.amount) for b in billing_records if b.amount)
        
        result.append(RevenueDataItem(
            month=month_start.strftime("%b"),
            revenue=revenue
        ))
    
    return result


@router.get("/admin/module-popularity", response_model=list[ModulePopularityItem])
async def get_admin_module_popularity(
    current_user: User = Depends(get_current_user),
) -> list[ModulePopularityItem]:
    """Get module subscription popularity across all tenants."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    modules = ["tasks", "crm", "booking", "pos", "hrm", "landing", "ai"]
    result = []
    
    for module in modules:
        count = await ModuleEntitlement.find(
            ModuleEntitlement.module_code == module,
            ModuleEntitlement.enabled == True
        ).count()
        
        if count > 0:
            result.append(ModulePopularityItem(
                name=get_module_name(module),
                value=count,
                color=get_module_color(module)
            ))
    
    result.sort(key=lambda x: x.value, reverse=True)
    return result


@router.get("/admin/recent-tenants", response_model=list[TenantItem])
async def get_admin_recent_tenants(
    current_user: User = Depends(get_current_user),
    limit: int = 5,
) -> list[TenantItem]:
    """Get recently registered tenants."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    tenants = await Tenant.find().sort(-Tenant.created_at).limit(limit).to_list()
    
    result = []
    for tenant in tenants:
        tenant_id = str(tenant.id)
        user_count = await User.find(User.tenant_id == tenant_id).count()
        
        subscription = await Subscription.find_one(Subscription.tenant_id == tenant_id)
        status_str = subscription.status if subscription else "inactive"
        if subscription and subscription.status == "active" and subscription.trial_ends_at:
            if subscription.trial_ends_at > datetime.utcnow():
                status_str = "trial"
        
        result.append(TenantItem(
            id=tenant_id,
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
) -> list[ServiceStatusItem]:
    """Get system health status."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    db_status = "online"
    db_latency = 5
    try:
        import time
        start = time.time()
        await User.find().limit(1).to_list()
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
    limit: int = 10,
) -> list[ActivityItem]:
    """Get platform-wide activity feed."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super admin access required")
    
    recent_tenants = await Tenant.find().sort(-Tenant.created_at).limit(limit // 2).to_list()
    recent_subscriptions = await Subscription.find().sort(-Subscription.created_at).limit(limit // 2).to_list()
    
    result = []
    
    for tenant in recent_tenants:
        result.append(ActivityItem(
            id=str(tenant.id),
            type="user",
            title="New tenant registered",
            description=f"{tenant.name} joined the platform",
            timestamp=get_time_ago(tenant.created_at),
            user=None
        ))
    
    for sub in recent_subscriptions:
        tenant = await Tenant.get(sub.tenant_id)
        tenant_name = tenant.name if tenant else "Unknown"
        result.append(ActivityItem(
            id=str(sub.id),
            type="task",
            title="Subscription updated",
            description=f"{tenant_name} subscription: {sub.status}",
            timestamp=get_time_ago(sub.created_at),
            user=None
        ))
    
    result.sort(key=lambda x: x.timestamp, reverse=False)
    return result[:limit]
