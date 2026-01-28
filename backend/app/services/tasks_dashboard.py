"""
Dashboard Metrics Service

Provides metrics for completion rates, overdue tasks, workload distribution, etc.
"""
import logging
from datetime import date, datetime, timedelta
from typing import Dict, Any, Optional
from decimal import Decimal

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import Task, Project, User, TaskStatus, TimeEntry, TaskPriority
from app.models.tasks import TaskStatusCategory

logger = logging.getLogger(__name__)


async def get_dashboard_metrics(
    tenant_id: str,
    user_id: Optional[str] = None,
    project_id: Optional[str] = None
) -> Dict[str, Any]:
    """Get comprehensive dashboard metrics."""
    # Base query for tasks
    conditions = [Task.tenant_id == tenant_id]
    
    if project_id:
        conditions.append(Task.project_id == project_id)
    
    if user_id:
        # Filter tasks assigned to user
        conditions.append({"assignee_ids": user_id})
    
    all_tasks = await Task.find(*conditions).to_list()
    
    # Calculate metrics
    total_tasks = len(all_tasks)
    
    # Status breakdown
    status_counts: Dict[str, int] = {}
    for task in all_tasks:
        if task.status_id:
            try:
                task_status = await TaskStatus.get(PydanticObjectId(task.status_id))
                if task_status:
                    status_name = task_status.name
                    status_counts[status_name] = status_counts.get(status_name, 0) + 1
            except Exception:
                continue
    
    # Completion rate
    completed_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.DONE
    ).to_list()
    completed_status_ids = [str(s.id) for s in completed_statuses]
    completed_count = sum(1 for t in all_tasks if t.status_id in completed_status_ids)
    completion_rate = (completed_count / total_tasks * 100) if total_tasks > 0 else 0
    
    # Overdue tasks
    today = date.today()
    overdue_tasks = [
        t for t in all_tasks
        if t.due_date and t.due_date < today and t.status_id not in completed_status_ids
    ]
    overdue_count = len(overdue_tasks)
    
    # Tasks due soon (next 7 days)
    next_week = today + timedelta(days=7)
    due_soon = [
        t for t in all_tasks
        if t.due_date and today <= t.due_date <= next_week and t.status_id not in completed_status_ids
    ]
    due_soon_count = len(due_soon)
    
    # Priority breakdown
    priority_counts = {"high": 0, "medium": 0, "low": 0, "none": 0}
    for task in all_tasks:
        if task.priority_id:
            try:
                priority = await TaskPriority.get(PydanticObjectId(task.priority_id))
                if priority:
                    if priority.level >= 3:
                        priority_counts["high"] += 1
                    elif priority.level >= 2:
                        priority_counts["medium"] += 1
                    else:
                        priority_counts["low"] += 1
                else:
                    priority_counts["none"] += 1
            except Exception:
                priority_counts["none"] += 1
        else:
            priority_counts["none"] += 1
    
    # Workload distribution (if user_id provided)
    workload_distribution: Dict[str, int] = {}
    if user_id:
        # Get tasks assigned to user grouped by project
        user_tasks = [t for t in all_tasks if user_id in (t.assignee_ids or [])]
        
        project_counts: Dict[str, int] = {}
        for task in user_tasks:
            project_id_val = task.project_id
            project_counts[project_id_val] = project_counts.get(project_id_val, 0) + 1
        
        # Get project names - safely handle invalid ObjectIds
        for proj_id, count in project_counts.items():
            if not proj_id:
                continue
            try:
                project = await Project.get(PydanticObjectId(proj_id))
                if project:
                    workload_distribution[project.name] = count
            except Exception:
                # Invalid ObjectId, skip this project
                continue
    
    # Time tracking summary (if user_id provided)
    time_summary: Dict[str, Any] = {}
    if user_id:
        thirty_days_ago = date.today() - timedelta(days=30)
        time_entries = await TimeEntry.find(
            TimeEntry.tenant_id == tenant_id,
            TimeEntry.user_id == user_id,
            TimeEntry.entry_date >= thirty_days_ago
        ).to_list()
        
        total_hours = sum(float(e.hours or 0) for e in time_entries)
        billable_hours = sum(float(e.hours or 0) for e in time_entries if e.is_billable)
        
        time_summary = {
            "total_hours_30d": round(total_hours, 2),
            "billable_hours_30d": round(billable_hours, 2)
        }
    
    return {
        "summary": {
            "total_tasks": total_tasks,
            "completed_tasks": completed_count,
            "in_progress_tasks": total_tasks - completed_count,
            "overdue_tasks": overdue_count,
            "due_soon_tasks": due_soon_count,
            "completion_rate": round(completion_rate, 2)
        },
        "status_breakdown": status_counts,
        "priority_breakdown": priority_counts,
        "workload_distribution": workload_distribution,
        "time_summary": time_summary,
        "overdue_tasks": [
            {
                "id": str(t.id),
                "title": t.title,
                "due_date": str(t.due_date),
                "project_id": t.project_id
            }
            for t in overdue_tasks[:10]  # Top 10 overdue
        ],
        "due_soon_tasks": [
            {
                "id": str(t.id),
                "title": t.title,
                "due_date": str(t.due_date),
                "project_id": t.project_id
            }
            for t in due_soon[:10]  # Top 10 due soon
        ]
    }


async def get_employee_progress_overview(
    tenant_id: str,
    project_id: Optional[str] = None
) -> Dict[str, Any]:
    """Get employee assignment and progress overview."""
    # Get all users in tenant
    users = await User.find(
        User.tenant_id == tenant_id,
        User.is_active == True
    ).to_list()
    
    employee_stats = []
    
    for user in users:
        # Get tasks assigned to user
        conditions = [
            Task.tenant_id == tenant_id,
            {"assignee_ids": str(user.id)}
        ]
        
        if project_id:
            conditions.append(Task.project_id == project_id)
        
        user_tasks = await Task.find(*conditions).to_list()
        
        # Calculate stats
        total_assigned = len(user_tasks)
        
        # Completed tasks
        completed_statuses = await TaskStatus.find(
            TaskStatus.tenant_id == tenant_id,
            TaskStatus.category == TaskStatusCategory.DONE
        ).to_list()
        completed_status_ids = [str(s.id) for s in completed_statuses]
        completed = sum(1 for t in user_tasks if t.status_id in completed_status_ids)
        
        # Overdue tasks
        today = date.today()
        overdue = sum(
            1 for t in user_tasks
            if t.due_date and t.due_date < today and t.status_id not in completed_status_ids
        )
        
        # Average completion percentage
        avg_completion = sum(t.completion_percentage for t in user_tasks) / total_assigned if total_assigned > 0 else 0
        
        # Time logged (last 30 days)
        thirty_days_ago = date.today() - timedelta(days=30)
        time_entries = await TimeEntry.find(
            TimeEntry.tenant_id == tenant_id,
            TimeEntry.user_id == str(user.id),
            TimeEntry.entry_date >= thirty_days_ago
        ).to_list()
        hours_logged = sum(float(e.hours or 0) for e in time_entries)
        
        employee_stats.append({
            "user_id": str(user.id),
            "email": user.email,
            "total_assigned": total_assigned,
            "completed": completed,
            "in_progress": total_assigned - completed,
            "overdue": overdue,
            "completion_rate": round((completed / total_assigned * 100) if total_assigned > 0 else 0, 2),
            "average_completion_percentage": round(avg_completion, 2),
            "hours_logged_30d": round(hours_logged, 2)
        })
    
    return {
        "employees": employee_stats,
        "total_employees": len(employee_stats)
    }
