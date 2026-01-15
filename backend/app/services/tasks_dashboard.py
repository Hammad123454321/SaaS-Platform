"""
Dashboard Metrics Service

Provides metrics for completion rates, overdue tasks, workload distribution, etc.
"""
import logging
from datetime import date, datetime, timedelta
from typing import Dict, Any, Optional
from decimal import Decimal

from sqlmodel import Session, select, and_, or_, func
from fastapi import HTTPException, status

from app.models import Task, Project, User, TaskStatus, TaskStatusCategory, TimeEntry, TaskPriority
from app.models.tasks import task_assignments_table

logger = logging.getLogger(__name__)


def get_dashboard_metrics(
    session: Session,
    tenant_id: int,
    user_id: Optional[int] = None,
    project_id: Optional[int] = None
) -> Dict[str, Any]:
    """Get comprehensive dashboard metrics."""
    # Base query for tasks
    task_query = select(Task).where(Task.tenant_id == tenant_id)
    
    if project_id:
        task_query = task_query.where(Task.project_id == project_id)
    
    if user_id:
        # Filter tasks assigned to user
        task_query = task_query.join(task_assignments_table).where(
            task_assignments_table.c.user_id == user_id
        )
    
    all_tasks = list(session.exec(task_query).all())
    
    # Calculate metrics
    total_tasks = len(all_tasks)
    
    # Status breakdown
    status_counts = {}
    for task in all_tasks:
        status = session.get(TaskStatus, task.status_id)
        if status:
            status_name = status.name
            status_counts[status_name] = status_counts.get(status_name, 0) + 1
    
    # Completion rate
    completed_statuses = session.exec(
        select(TaskStatus).where(
            and_(
                TaskStatus.tenant_id == tenant_id,
                TaskStatus.category == TaskStatusCategory.DONE
            )
        )
    ).all()
    completed_status_ids = [s.id for s in completed_statuses]
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
            priority = session.get(TaskPriority, task.priority_id)
            if priority:
                if priority.level >= 3:
                    priority_counts["high"] += 1
                elif priority.level >= 2:
                    priority_counts["medium"] += 1
                else:
                    priority_counts["low"] += 1
        else:
            priority_counts["none"] += 1
    
    # Workload distribution (if user_id provided)
    workload_distribution = {}
    if user_id:
        # Get tasks assigned to user grouped by project
        user_tasks = [
            t for t in all_tasks
            if any(a.id == user_id for a in (t.assignees or []))
        ]
        
        project_counts = {}
        for task in user_tasks:
            project_id_val = task.project_id
            project_counts[project_id_val] = project_counts.get(project_id_val, 0) + 1
        
        # Get project names
        for proj_id, count in project_counts.items():
            project = session.get(Project, proj_id)
            if project:
                workload_distribution[project.name] = count
    
    # Time tracking summary (if user_id provided)
    time_summary = {}
    if user_id:
        time_query = select(
            func.sum(TimeEntry.hours).label("total_hours"),
            func.sum(func.case((TimeEntry.is_billable == True, TimeEntry.hours), else_=0)).label("billable_hours")
        ).where(
            and_(
                TimeEntry.tenant_id == tenant_id,
                TimeEntry.user_id == user_id,
                TimeEntry.entry_date >= date.today() - timedelta(days=30)  # Last 30 days
            )
        )
        
        result = session.exec(time_query).first()
        if result:
            time_summary = {
                "total_hours_30d": float(result[0] or 0),
                "billable_hours_30d": float(result[1] or 0)
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
                "id": t.id,
                "title": t.title,
                "due_date": str(t.due_date),
                "project_id": t.project_id
            }
            for t in overdue_tasks[:10]  # Top 10 overdue
        ],
        "due_soon_tasks": [
            {
                "id": t.id,
                "title": t.title,
                "due_date": str(t.due_date),
                "project_id": t.project_id
            }
            for t in due_soon[:10]  # Top 10 due soon
        ]
    }


def get_employee_progress_overview(
    session: Session,
    tenant_id: int,
    project_id: Optional[int] = None
) -> Dict[str, Any]:
    """Get employee assignment and progress overview."""
    # Get all users in tenant
    users = list(
        session.exec(
            select(User).where(
                and_(
                    User.tenant_id == tenant_id,
                    User.is_active == True
                )
            )
        ).all()
    )
    
    employee_stats = []
    
    for user in users:
        # Get tasks assigned to user
        task_query = select(Task).where(
            and_(
                Task.tenant_id == tenant_id
            )
        ).join(task_assignments_table).where(
            task_assignments_table.c.user_id == user.id
        )
        
        if project_id:
            task_query = task_query.where(Task.project_id == project_id)
        
        user_tasks = list(session.exec(task_query).all())
        
        # Calculate stats
        total_assigned = len(user_tasks)
        
        # Completed tasks
        completed_statuses = session.exec(
            select(TaskStatus).where(
                and_(
                    TaskStatus.tenant_id == tenant_id,
                    TaskStatus.category == TaskStatusCategory.DONE
                )
            )
        ).all()
        completed_status_ids = [s.id for s in completed_statuses]
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
        time_query = select(func.sum(TimeEntry.hours)).where(
            and_(
                TimeEntry.tenant_id == tenant_id,
                TimeEntry.user_id == user.id,
                TimeEntry.date >= date.today() - timedelta(days=30)
            )
        )
        time_result = session.exec(time_query).first()
        hours_logged = float(time_result or 0)
        
        employee_stats.append({
            "user_id": user.id,
            "email": user.email,
            "total_assigned": total_assigned,
            "completed": completed,
            "in_progress": total_assigned - completed,
            "overdue": overdue,
            "completion_rate": round((completed / total_assigned * 100) if total_assigned > 0 else 0, 2),
            "average_completion_percentage": round(avg_completion, 2),
            "hours_logged_30d": hours_logged
        })
    
    return {
        "employees": employee_stats,
        "total_employees": len(employee_stats)
    }

