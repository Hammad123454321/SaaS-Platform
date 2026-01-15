"""
Task Management API Routes

Dedicated routes for the Tasks module.
Replaces the Taskify integration with native implementation.
"""
import logging
from typing import Optional, List, Any
from fastapi import APIRouter, Depends, HTTPException, status, UploadFile, File
from sqlmodel import Session, select, and_, distinct

logger = logging.getLogger(__name__)

from app.api.authz import require_permission
from app.db import get_session
from app.models import User, ModuleCode, ModuleEntitlement, Task, Project
from app.models.role import PermissionCode
from app.models.tasks import task_assignments_table
from app.services.task_access_control import (
    can_user_create_task,
    can_user_update_task,
    can_user_change_task_status,
    can_user_delete_task,
    can_user_create_project,
    can_user_update_project,
    can_user_delete_project,
)
from app.services.tasks import (
    # Clients
    create_client,
    get_client,
    list_clients,
    update_client,
    delete_client,
    # Projects
    create_project,
    get_project,
    list_projects,
    update_project,
    delete_project,
    # Tasks
    create_task,
    get_task,
    list_tasks,
    update_task,
    delete_task,
    duplicate_task,
    # Statuses
    list_statuses,
    create_status,
    update_status,
    delete_status,
    # Priorities
    list_priorities,
    create_priority,
    update_priority,
    delete_priority,
    # Comments
    add_comment,
    list_comments,
    # Favorites/Pins
    toggle_favorite,
    toggle_pin,
    is_task_favorite,
    is_task_pinned,
)
from sqlmodel import select

router = APIRouter(prefix="/modules/tasks", tags=["tasks"])


def _require_tasks_entitlement(session: Session, tenant_id: int) -> None:
    """Check if Tasks module is enabled for tenant."""
    entitlement = session.exec(
        select(ModuleEntitlement).where(
            ModuleEntitlement.tenant_id == tenant_id,
            ModuleEntitlement.module_code == ModuleCode.TASKS,
            ModuleEntitlement.enabled == True,  # noqa: E712
        )
    ).first()
    
    if not entitlement:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Tasks module not enabled"
        )


def _is_staff_only(user: User) -> bool:
    """Check if user is staff (not admin). Staff can only access their assigned tasks."""
    if user.is_super_admin:
        return False
    role_names = [role.name for role in user.roles] if user.roles else []
    # If user has company_admin or admin role, they're not staff-only
    return not any(name in ["company_admin", "admin"] for name in role_names)


# ========== Dropdown Data Endpoints ==========
@router.get("/dropdown/clients")
async def get_clients_dropdown(
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get clients for dropdown."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    clients = list_clients(session, current_user.tenant_id)
    return {
        "data": [
            {
                "id": c.id,
                "name": f"{c.first_name} {c.last_name}",
                "email": c.email,
                "company": c.company,
            }
            for c in clients
        ]
    }


@router.get("/dropdown/projects")
async def get_projects_dropdown(
    client_id: Optional[int] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get projects for dropdown."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    projects = list_projects(session, current_user.tenant_id, client_id=client_id)
    return {
        "data": [
            {
                "id": p.id,
                "name": p.name,
                "client_id": p.client_id,
            }
            for p in projects
        ]
    }


@router.get("/dropdown/statuses")
async def get_statuses_dropdown(
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get statuses for dropdown."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    statuses = list_statuses(session, current_user.tenant_id)
    return {
        "data": [
            {
                "id": s.id,
                "name": s.name,
                "color": s.color,
            }
            for s in statuses
        ]
    }


@router.get("/dropdown/priorities")
async def get_priorities_dropdown(
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get priorities for dropdown."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    priorities = list_priorities(session, current_user.tenant_id)
    return {
        "data": [
            {
                "id": p.id,
                "name": p.name,
                "color": p.color,
            }
            for p in priorities
        ]
    }


@router.get("/dropdown/users")
async def get_users_dropdown(
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get users for dropdown (assignees)."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.models import User as UserModel
    users = session.exec(
        select(UserModel).where(UserModel.tenant_id == current_user.tenant_id)
    ).all()
    return {
        "data": [
            {
                "id": u.id,
                "first_name": u.email.split("@")[0].split(".")[0].capitalize(),
                "last_name": u.email.split("@")[0].split(".")[1].capitalize() if "." in u.email.split("@")[0] else "",
                "email": u.email,
            }
            for u in users
        ]
    }


# ========== Records Endpoint (Generic) ==========
@router.get("/my-tasks")
async def get_my_tasks(
    project_id: Optional[int] = None,
    status_id: Optional[int] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get tasks assigned to the current user (for staff members)."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    # Filter by current user as assignee
    tasks = list_tasks(
        session,
        current_user.tenant_id,
        project_id=project_id,
        status_id=status_id,
        assignee_id=current_user.id
    )
    
    return {
        "data": [
            {
                "id": t.id,
                "title": t.title,
                "description": t.description,
                "status_id": t.status_id,
                "status_name": t.status.name if t.status else None,
                "status_color": t.status.color if t.status else None,
                "priority_id": t.priority_id,
                "priority_name": t.priority.name if t.priority else None,
                "priority_color": t.priority.color if t.priority else None,
                "project_id": t.project_id,
                "project": {"id": t.project.id, "title": t.project.name} if t.project else None,
                "due_date": str(t.due_date) if t.due_date else None,
                "start_date": str(t.start_date) if t.start_date else None,
                "assignees": [
                    {"id": u.id, "first_name": u.email.split("@")[0].split(".")[0].capitalize(), "last_name": ""}
                    for u in t.assignees
                ] if t.assignees else [],
                "user_id": [u.id for u in t.assignees] if t.assignees else [],
                "created_at": str(t.created_at),
                "is_favorite": is_task_favorite(session, current_user.tenant_id, t.id, current_user.id),
                "is_pinned": is_task_pinned(session, current_user.tenant_id, t.id, current_user.id),
            }
            for t in tasks
        ]
    }


@router.get("/records")
async def list_records(
    resource: str,
    project_id: Optional[int] = None,
    status_id: Optional[int] = None,
    assignee_id: Optional[int] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """List records for a resource."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    # Check if user is staff-only (not admin)
    is_staff = _is_staff_only(current_user)
    
    if resource == "tasks":
        # Staff can only see tasks assigned to them
        if is_staff:
            assignee_id = current_user.id
        
        tasks = list_tasks(
            session,
            current_user.tenant_id,
            project_id=project_id,
            status_id=status_id,
            assignee_id=assignee_id
        )
        return {
            "data": [
                {
                    "id": t.id,
                    "title": t.title,
                    "description": t.description,
                    "status_id": t.status_id,
                    "status_name": t.status.name if t.status else None,
                    "status_color": t.status.color if t.status else None,
                    "priority_id": t.priority_id,
                    "priority_name": t.priority.name if t.priority else None,
                    "priority_color": t.priority.color if t.priority else None,
                    "project_id": t.project_id,
                    "project": {"id": t.project.id, "title": t.project.name} if t.project else None,
                    "due_date": str(t.due_date) if t.due_date else None,
                    "start_date": str(t.start_date) if t.start_date else None,
                    "assignees": [
                        {"id": u.id, "first_name": u.email.split("@")[0].split(".")[0].capitalize(), "last_name": ""}
                        for u in t.assignees
                    ] if t.assignees else [],
                    "user_id": [u.id for u in t.assignees] if t.assignees else [],
                    "created_at": str(t.created_at),
                    "is_favorite": is_task_favorite(session, current_user.tenant_id, t.id, current_user.id),
                    "is_pinned": is_task_pinned(session, current_user.tenant_id, t.id, current_user.id),
                }
                for t in tasks
            ]
        }
    elif resource == "projects":
        # Staff can only see projects that have tasks assigned to them
        if is_staff:
            # Get project IDs from tasks assigned to this user
            project_ids = session.exec(
                select(distinct(Task.project_id))
                .join(task_assignments_table)
                .where(
                    and_(
                        Task.tenant_id == current_user.tenant_id,
                        task_assignments_table.c.user_id == current_user.id
                    )
                )
            ).all()
            projects = [p for p in list_projects(session, current_user.tenant_id) if p.id in project_ids]
        else:
            projects = list_projects(session, current_user.tenant_id)
        
        return {
            "data": [
                {
                    "id": p.id,
                    "name": p.name,
                    "title": p.name,  # For compatibility
                    "description": p.description,
                    "client_id": p.client_id,
                    "client": {"id": p.client.id, "first_name": p.client.first_name, "last_name": p.client.last_name} if p.client else None,
                    "budget": float(p.budget) if p.budget else None,
                    "deadline": str(p.deadline) if p.deadline else None,
                }
                for p in projects
            ]
        }
    elif resource == "clients":
        # Staff can only see clients from projects they're assigned to
        if is_staff:
            # Get client IDs from projects that have tasks assigned to this user
            client_ids = session.exec(
                select(distinct(Project.client_id))
                .join(Task)
                .join(task_assignments_table)
                .where(
                    and_(
                        Task.tenant_id == current_user.tenant_id,
                        task_assignments_table.c.user_id == current_user.id
                    )
                )
            ).all()
            clients = [c for c in list_clients(session, current_user.tenant_id) if c.id in client_ids]
        else:
            clients = list_clients(session, current_user.tenant_id)
        
        return {
            "data": [
                {
                    "id": c.id,
                    "first_name": c.first_name,
                    "last_name": c.last_name,
                    "email": c.email,
                    "phone": c.phone,
                    "company": c.company,
                }
                for c in clients
            ]
        }
    elif resource == "statuses":
        statuses = list_statuses(session, current_user.tenant_id)
        return {
            "data": [
                {
                    "id": s.id,
                    "name": s.name,
                    "title": s.name,  # For compatibility
                    "color": s.color,
                }
                for s in statuses
            ]
        }
    elif resource == "priorities":
        priorities = list_priorities(session, current_user.tenant_id)
        return {
            "data": [
                {
                    "id": p.id,
                    "name": p.name,
                    "title": p.name,  # For compatibility
                    "color": p.color,
                }
                for p in priorities
            ]
        }
    else:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Unknown resource: {resource}")


@router.post("/records")
async def create_record(
    resource: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Create a record."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    # Check if user is staff-only (not admin)
    is_staff = _is_staff_only(current_user)
    
    # Staff can only create tasks, not clients/projects/statuses/priorities
    if is_staff and resource not in ["tasks"]:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Staff members can only create tasks"
        )
    
    if resource == "tasks":
        # Stage 3: Access control - check if user can create tasks
        if not can_user_create_task(current_user, session):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="You do not have permission to create tasks."
            )
        
        # Normalize payload
        assignee_ids = payload.get("assignee_ids") or payload.get("user_id", [])
        
        # Staff can only create tasks assigned to themselves
        role_names = [r.name.lower() for r in current_user.roles]
        if "staff" in role_names:
            assignee_ids = [current_user.id]
        
        task_data = {
            "title": payload.get("title", ""),
            "description": payload.get("description"),
            "notes": payload.get("notes"),
            "project_id": payload.get("project_id") or payload.get("project"),
            "status_id": payload.get("status_id"),
            "priority_id": payload.get("priority_id"),
            "task_list_id": payload.get("task_list_id"),
            "start_date": payload.get("start_date"),
            "due_date": payload.get("due_date"),
            "completion_percentage": payload.get("completion_percentage", 0),
            "assignee_ids": assignee_ids,
        }
        task = create_task(session, current_user.tenant_id, current_user.id, task_data)
        return {"data": {"id": task.id, "title": task.title}}
    
    elif resource == "projects":
        # Stage 3: Access control - check if user can create projects
        if not can_user_create_project(current_user, session):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="You do not have permission to create projects. Contact the owner to grant this permission."
            )
        
        project_data = {
            "name": payload.get("name") or payload.get("title", ""),
            "description": payload.get("description"),
            "client_id": payload.get("client_id"),
            "budget": payload.get("budget"),
            "deadline": payload.get("deadline") or payload.get("end_date"),
            "start_date": payload.get("start_date"),
        }
        project = create_project(session, current_user.tenant_id, current_user.id, project_data)
        return {"data": {"id": project.id, "name": project.name}}
    
    elif resource == "clients":
        client_data = {
            "first_name": payload.get("first_name", ""),
            "last_name": payload.get("last_name", ""),
            "email": payload.get("email", ""),
            "phone": payload.get("phone"),
            "company": payload.get("company"),
            "address": payload.get("address"),
            "notes": payload.get("notes"),
        }
        client = create_client(session, current_user.tenant_id, client_data)
        return {"data": {"id": client.id, "first_name": client.first_name, "last_name": client.last_name}}
    
    elif resource == "statuses":
        status_data = {
            "name": payload.get("name") or payload.get("title", ""),
            "color": payload.get("color", "#6b7280"),
            "category": payload.get("category", "todo"),
            "display_order": payload.get("display_order", 0),
        }
        status_obj = create_status(session, current_user.tenant_id, status_data)
        return {"data": {"id": status_obj.id, "name": status_obj.name}}
    
    elif resource == "priorities":
        priority_data = {
            "name": payload.get("name") or payload.get("title", ""),
            "color": payload.get("color", "#6b7280"),
            "level": payload.get("level", 0),
            "display_order": payload.get("display_order", 0),
        }
        priority = create_priority(session, current_user.tenant_id, priority_data)
        return {"data": {"id": priority.id, "name": priority.name}}
    
    else:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Unknown resource: {resource}")


@router.patch("/records/{record_id}")
async def update_record(
    record_id: str,
    resource: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Update a record."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    # Check if user is staff-only (not admin)
    is_staff = _is_staff_only(current_user)
    
    try:
        record_id_int = int(record_id)
    except ValueError:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Invalid record ID")
    
    if resource == "tasks":
        # Get task first
        task = get_task(session, current_user.tenant_id, record_id_int)
        if not task:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
        
        # Stage 3: Access control - check if user can update this task
        if not can_user_update_task(current_user, task, session):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="You do not have permission to update this task."
            )
        
        # Staff can only change status, not other fields
        role_names = [r.name.lower() for r in current_user.roles]
        is_staff = "staff" in role_names
        
        if is_staff:
            # Staff can only update status_id
            updates = {
                "status_id": payload.get("status_id"),
            }
            # Remove status_id if not provided
            if updates["status_id"] is None:
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail="Staff can only change task status."
                )
        else:
            # Owner/Manager can update all fields
            updates = {
                "title": payload.get("title"),
                "description": payload.get("description"),
                "notes": payload.get("notes"),
                "status_id": payload.get("status_id"),
                "priority_id": payload.get("priority_id"),
                "project_id": payload.get("project_id"),
                "task_list_id": payload.get("task_list_id"),
                "start_date": payload.get("start_date"),
                "due_date": payload.get("due_date"),
                "completion_percentage": payload.get("completion_percentage"),
            }
            
            # Only Owner/Manager can change assignees
            assignee_ids = payload.get("assignee_ids") or payload.get("user_id")
            if assignee_ids is not None:
                updates["assignee_ids"] = assignee_ids
        
        updates = {k: v for k, v in updates.items() if v is not None}
        task = update_task(session, current_user.tenant_id, record_id_int, updates)
        return {"data": {"id": task.id, "title": task.title}}
    
    elif resource == "projects":
        # Get project first
        project = session.get(Project, record_id_int)
        if not project or project.tenant_id != current_user.tenant_id:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found")
        
        # Stage 3: Access control - check if user can update this project
        if not can_user_update_project(current_user, project, session):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="You do not have permission to update this project."
            )
        
        updates = {
            "name": payload.get("name") or payload.get("title"),
            "description": payload.get("description"),
            "client_id": payload.get("client_id"),
            "budget": payload.get("budget"),
            "deadline": payload.get("deadline") or payload.get("end_date"),
            "start_date": payload.get("start_date"),
            "status": payload.get("status"),
        }
        updates = {k: v for k, v in updates.items() if v is not None}
        project = update_project(session, current_user.tenant_id, record_id_int, updates)
        return {"data": {"id": project.id, "name": project.name}}
    
    elif resource == "clients":
        # Staff cannot update clients
        if is_staff:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Staff members cannot update clients"
            )
        updates = {
            "first_name": payload.get("first_name"),
            "last_name": payload.get("last_name"),
            "email": payload.get("email"),
            "phone": payload.get("phone"),
            "company": payload.get("company"),
            "address": payload.get("address"),
            "notes": payload.get("notes"),
        }
        updates = {k: v for k, v in updates.items() if v is not None}
        client = update_client(session, current_user.tenant_id, record_id_int, updates)
        return {"data": {"id": client.id}}
    
    elif resource == "statuses":
        # Staff cannot update statuses
        if is_staff:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Staff members cannot update statuses"
            )
        updates = {
            "name": payload.get("name") or payload.get("title"),
            "color": payload.get("color"),
            "category": payload.get("category"),
            "display_order": payload.get("display_order"),
        }
        updates = {k: v for k, v in updates.items() if v is not None}
        status_obj = update_status(session, current_user.tenant_id, record_id_int, updates)
        return {"data": {"id": status_obj.id}}
    
    elif resource == "priorities":
        # Staff cannot update priorities
        if is_staff:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Staff members cannot update priorities"
            )
        updates = {
            "name": payload.get("name") or payload.get("title"),
            "color": payload.get("color"),
            "level": payload.get("level"),
            "display_order": payload.get("display_order"),
        }
        updates = {k: v for k, v in updates.items() if v is not None}
        priority = update_priority(session, current_user.tenant_id, record_id_int, updates)
        return {"data": {"id": priority.id}}
    
    else:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Unknown resource: {resource}")


@router.delete("/records/{record_id}")
async def delete_record(
    record_id: str,
    resource: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Delete a record."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    # Check if user is staff-only (not admin)
    is_staff = _is_staff_only(current_user)
    
    try:
        record_id_int = int(record_id)
    except ValueError:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Invalid record ID")
    
    if resource == "tasks":
        # Get task first
        task = get_task(session, current_user.tenant_id, record_id_int)
        if not task:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
        
        # Stage 3: Access control - check if user can delete this task
        if not can_user_delete_task(current_user, task, session):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="You do not have permission to delete this task. Required tasks cannot be deleted."
            )
        
        delete_task(session, current_user.tenant_id, record_id_int)
    elif resource == "projects":
        # Get project first
        project = session.get(Project, record_id_int)
        if not project or project.tenant_id != current_user.tenant_id:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found")
        
        # Stage 3: Access control - check if user can delete this project
        if not can_user_delete_project(current_user, project, session):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="You do not have permission to delete this project."
            )
        
        delete_project(session, current_user.tenant_id, record_id_int)
    elif resource == "clients":
        # Staff cannot delete clients
        if is_staff:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Staff members cannot delete clients"
            )
        delete_client(session, current_user.tenant_id, record_id_int)
    elif resource == "statuses":
        # Staff cannot delete statuses
        if is_staff:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Staff members cannot delete statuses"
            )
        delete_status(session, current_user.tenant_id, record_id_int)
    elif resource == "priorities":
        # Staff cannot delete priorities
        if is_staff:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Staff members cannot delete priorities"
            )
        delete_priority(session, current_user.tenant_id, record_id_int)
    else:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Unknown resource: {resource}")
    
    return {"data": {"id": record_id_int, "deleted": True}}


# ========== Task-Specific Endpoints ==========
@router.patch("/tasks/{task_id}/favorite")
async def update_task_favorite_endpoint(
    task_id: int,
    is_favorite: bool = False,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Toggle task favorite status."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    toggle_favorite(session, current_user.tenant_id, task_id, current_user.id, is_favorite)
    return {"data": {"id": task_id, "is_favorite": is_favorite}}


@router.patch("/tasks/{task_id}/pinned")
async def update_task_pinned_endpoint(
    task_id: int,
    is_pinned: bool = False,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Toggle task pinned status."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    toggle_pin(session, current_user.tenant_id, task_id, current_user.id, is_pinned)
    return {"data": {"id": task_id, "is_pinned": is_pinned}}


@router.post("/tasks/{task_id}/duplicate")
async def duplicate_task_endpoint(
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Duplicate a task."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    new_task = duplicate_task(session, current_user.tenant_id, task_id, current_user.id)
    return {"data": {"id": new_task.id, "title": new_task.title}}


@router.post("/tasks/bulk-delete")
async def bulk_delete_tasks_endpoint(
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Bulk delete tasks."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    task_ids = payload.get("task_ids", [])
    
    for task_id in task_ids:
        try:
            delete_task(session, current_user.tenant_id, task_id)
        except Exception as e:
            logger.error(f"Failed to delete task {task_id}: {e}")
    
    return {"data": {"deleted_count": len(task_ids)}}


# ========== Comments ==========
@router.post("/tasks/{task_id}/comments")
async def add_task_comment(
    task_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Add a comment to a task."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    comment_text = payload.get("comment", "")
    comment = add_comment(session, current_user.tenant_id, task_id, current_user.id, comment_text)
    return {"data": {"id": comment.id, "comment": comment.comment}}


@router.get("/tasks/{task_id}/comments/list")
async def get_task_comments(
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get comments for a task."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    comments = list_comments(session, current_user.tenant_id, task_id)
    return {
        "data": [
            {
                "id": c.id,
                "comment": c.comment,
                "user_id": c.user_id,
                "created_at": str(c.created_at),
            }
            for c in comments
        ]
    }


# ========== Milestones ==========
@router.get("/milestones")
async def list_milestones(
    project_id: Optional[int] = None,
    is_completed: Optional[bool] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """List milestones."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_milestones import list_milestones as list_milestones_service
    
    milestones = list_milestones_service(
        session,
        current_user.tenant_id,
        project_id=project_id,
        is_completed=is_completed
    )
    
    return {
        "data": [
            {
                "id": m.id,
                "title": m.title,
                "description": m.description,
                "due_date": str(m.due_date) if m.due_date else None,
                "is_completed": m.is_completed,
                "completed_at": str(m.completed_at) if m.completed_at else None,
                "project_id": m.project_id,
            }
            for m in milestones
        ]
    }


@router.post("/milestones")
async def create_milestone_endpoint(
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Create a milestone."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_milestones import create_milestone
    
    milestone = create_milestone(
        session,
        current_user.tenant_id,
        payload["project_id"],
        {
            "title": payload.get("title", ""),
            "description": payload.get("description"),
            "due_date": payload.get("due_date"),
            "is_completed": payload.get("is_completed", False),
        }
    )
    
    return {"data": {"id": milestone.id, "title": milestone.title}}


@router.patch("/milestones/{milestone_id}")
async def update_milestone_endpoint(
    milestone_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Update a milestone."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_milestones import update_milestone
    
    milestone = update_milestone(
        session,
        current_user.tenant_id,
        milestone_id,
        payload
    )
    
    return {"data": {"id": milestone.id, "title": milestone.title}}


@router.delete("/milestones/{milestone_id}")
async def delete_milestone_endpoint(
    milestone_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Delete a milestone."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_milestones import delete_milestone
    
    delete_milestone(session, current_user.tenant_id, milestone_id)
    return {"data": {"id": milestone_id, "deleted": True}}


@router.get("/milestones/{milestone_id}/stats")
async def get_milestone_stats(
    milestone_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get milestone completion statistics."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_milestones import get_milestone_completion_stats
    
    stats = get_milestone_completion_stats(session, current_user.tenant_id, milestone_id)
    return {"data": stats}


# ========== Task Lists ==========
@router.get("/task-lists")
async def list_task_lists(
    project_id: Optional[int] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """List task lists."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_lists import list_task_lists as list_task_lists_service
    
    task_lists = list_task_lists_service(
        session,
        current_user.tenant_id,
        project_id=project_id
    )
    
    return {
        "data": [
            {
                "id": tl.id,
                "name": tl.name,
                "description": tl.description,
                "display_order": tl.display_order,
                "project_id": tl.project_id,
            }
            for tl in task_lists
        ]
    }


@router.post("/task-lists")
async def create_task_list_endpoint(
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Create a task list."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_lists import create_task_list
    
    task_list = create_task_list(
        session,
        current_user.tenant_id,
        payload["project_id"],
        {
            "name": payload.get("name", ""),
            "description": payload.get("description"),
        }
    )
    
    return {"data": {"id": task_list.id, "name": task_list.name}}


@router.patch("/task-lists/{list_id}")
async def update_task_list_endpoint(
    list_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Update a task list."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_lists import update_task_list
    
    task_list = update_task_list(
        session,
        current_user.tenant_id,
        list_id,
        payload
    )
    
    return {"data": {"id": task_list.id, "name": task_list.name}}


@router.delete("/task-lists/{list_id}")
async def delete_task_list_endpoint(
    list_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Delete a task list."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_lists import delete_task_list
    
    delete_task_list(session, current_user.tenant_id, list_id)
    return {"data": {"id": list_id, "deleted": True}}


@router.get("/task-lists/{list_id}/stats")
async def get_task_list_stats(
    list_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get task list statistics."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_lists import get_task_list_stats
    
    stats = get_task_list_stats(session, current_user.tenant_id, list_id)
    return {"data": stats}


# ========== Tags ==========
@router.get("/tags")
async def list_tags(
    search: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """List tags."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_tags import list_tags as list_tags_service
    
    tags = list_tags_service(session, current_user.tenant_id, search=search)
    
    return {
        "data": [
            {
                "id": t.id,
                "name": t.name,
                "color": t.color,
            }
            for t in tags
        ]
    }


@router.post("/tags")
async def create_tag_endpoint(
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Create a tag."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_tags import create_tag
    
    tag = create_tag(
        session,
        current_user.tenant_id,
        {
            "name": payload.get("name", ""),
            "color": payload.get("color", "#6b7280"),
        }
    )
    
    return {"data": {"id": tag.id, "name": tag.name}}


@router.patch("/tags/{tag_id}")
async def update_tag_endpoint(
    tag_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Update a tag."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_tags import update_tag
    
    tag = update_tag(session, current_user.tenant_id, tag_id, payload)
    return {"data": {"id": tag.id, "name": tag.name}}


@router.delete("/tags/{tag_id}")
async def delete_tag_endpoint(
    tag_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Delete a tag."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_tags import delete_tag
    
    delete_tag(session, current_user.tenant_id, tag_id)
    return {"data": {"id": tag_id, "deleted": True}}


@router.post("/tasks/{task_id}/tags")
async def assign_tags_to_task_endpoint(
    task_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Assign tags to a task."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_tags import assign_tags_to_task
    
    tag_ids = payload.get("tag_ids", [])
    task = assign_tags_to_task(session, current_user.tenant_id, task_id, tag_ids)
    return {"data": {"id": task.id, "tag_ids": tag_ids}}


@router.get("/tasks/{task_id}/tags")
async def get_task_tags_endpoint(
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get tags for a task."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_tags import get_task_tags
    
    tags = get_task_tags(session, current_user.tenant_id, task_id)
    return {
        "data": [
            {
                "id": t.id,
                "name": t.name,
                "color": t.color,
            }
            for t in tags
        ]
    }


@router.post("/tasks/{task_id}/media")
async def upload_task_media(
    task_id: int,
    file: UploadFile = File(...),
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Upload media/file to a task."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    # Verify task exists
    from app.services.tasks import get_task
    task = get_task(session, current_user.tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    # Read file content
    file_content = await file.read()
    file_size = len(file_content)
    
    # Validate file size (10MB limit)
    max_size = 10 * 1024 * 1024  # 10MB
    if file_size > max_size:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"File size exceeds maximum allowed size of 10MB"
        )
    
    # Generate unique filename
    import os
    import uuid
    from pathlib import Path
    
    file_ext = Path(file.filename).suffix if file.filename else ""
    unique_filename = f"{uuid.uuid4()}{file_ext}"
    
    # Create uploads directory if it doesn't exist
    upload_dir = Path("uploads/tasks")
    upload_dir.mkdir(parents=True, exist_ok=True)
    
    # Save file
    file_path = upload_dir / unique_filename
    with open(file_path, "wb") as f:
        f.write(file_content)
    
    # Get MIME type
    import mimetypes
    mime_type, _ = mimetypes.guess_type(file.filename or "")
    mime_type = mime_type or "application/octet-stream"
    
    # Create attachment record
    from app.models import TaskAttachment
    attachment = TaskAttachment(
        tenant_id=current_user.tenant_id,
        task_id=task_id,
        user_id=current_user.id,
        filename=file.filename or "upload",
        original_filename=file.filename or "upload",
        file_path=str(file_path),
        file_size=file_size,
        mime_type=mime_type,
    )
    session.add(attachment)
    session.commit()
    session.refresh(attachment)
    
    return {"data": {"id": attachment.id, "filename": attachment.filename}}


@router.get("/tasks/{task_id}/media")
async def list_task_attachments(
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """List attachments for a task."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.models import TaskAttachment
    from sqlmodel import select, and_
    
    attachments = session.exec(
        select(TaskAttachment).where(
            and_(
                TaskAttachment.tenant_id == current_user.tenant_id,
                TaskAttachment.task_id == task_id
            )
        )
    ).all()
    
    return {
        "data": [
            {
                "id": a.id,
                "filename": a.original_filename,
                "file_size": a.file_size,
                "mime_type": a.mime_type,
                "created_at": str(a.created_at),
            }
            for a in attachments
        ]
    }


@router.get("/attachments/{attachment_id}/download")
async def download_attachment(
    attachment_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> Any:
    """Download an attachment."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.models import TaskAttachment
    from fastapi.responses import FileResponse
    from pathlib import Path
    
    attachment = session.get(TaskAttachment, attachment_id)
    if not attachment or attachment.tenant_id != current_user.tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Attachment not found")
    
    file_path = Path(attachment.file_path)
    if not file_path.exists():
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="File not found on disk")
    
    return FileResponse(
        path=str(file_path),
        filename=attachment.original_filename,
        media_type=attachment.mime_type
    )


@router.delete("/attachments/{attachment_id}")
async def delete_attachment(
    attachment_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Delete an attachment."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.models import TaskAttachment
    from pathlib import Path
    import os
    
    attachment = session.get(TaskAttachment, attachment_id)
    if not attachment or attachment.tenant_id != current_user.tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Attachment not found")
    
    # Delete file from disk
    file_path = Path(attachment.file_path)
    if file_path.exists():
        try:
            os.remove(file_path)
        except Exception as e:
            logger.error(f"Failed to delete file {file_path}: {e}")
    
    session.delete(attachment)
    session.commit()
    
    return {"data": {"id": attachment_id, "deleted": True}}


# ========== Kanban View ==========
@router.get("/kanban")
async def get_kanban_view(
    project_id: Optional[int] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get tasks organized by status for Kanban view."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    # Check if user is staff - limit to assigned tasks
    is_staff = not current_user.is_super_admin
    if not is_staff:
        role_names = [role.name for role in current_user.roles] if current_user.roles else []
        is_staff = not any(name in ["company_admin", "admin"] for name in role_names)
    
    # Get all statuses
    from app.services.tasks import list_statuses
    statuses = list_statuses(session, current_user.tenant_id)
    
    # Get tasks
    if is_staff:
        tasks = list_tasks(
            session,
            current_user.tenant_id,
            project_id=project_id,
            assignee_id=current_user.id
        )
    else:
        tasks = list_tasks(
            session,
            current_user.tenant_id,
            project_id=project_id
        )
    
    # Group by status
    kanban_data = {}
    for status in statuses:
        status_tasks = [
            {
                "id": t.id,
                "title": t.title,
                "description": t.description,
                "priority_id": t.priority_id,
                "priority_name": t.priority.name if t.priority else None,
                "priority_color": t.priority.color if t.priority else None,
                "due_date": str(t.due_date) if t.due_date else None,
                "completion_percentage": t.completion_percentage,
                "assignees": [
                    {"id": u.id, "email": u.email}
                    for u in t.assignees
                ] if t.assignees else [],
                "subtasks_count": len(t.subtasks) if t.subtasks else 0,
            }
            for t in tasks if t.status_id == status.id
        ]
        kanban_data[status.name] = {
            "status_id": status.id,
            "status_name": status.name,
            "status_color": status.color,
            "tasks": status_tasks,
            "count": len(status_tasks)
        }
    
    return {"data": kanban_data}


@router.patch("/kanban/{task_id}/move")
async def move_task_kanban(
    task_id: int,
    status_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Move a task to a different status (for drag-and-drop)."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    # Verify task exists
    task = get_task(session, current_user.tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    # Check staff permissions
    is_staff = not current_user.is_super_admin
    if not is_staff:
        role_names = [role.name for role in current_user.roles] if current_user.roles else []
        is_staff = not any(name in ["company_admin", "admin"] for name in role_names)
    
    if is_staff:
        is_assigned = any(assignee.id == current_user.id for assignee in (task.assignees or []))
        if not is_assigned:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="You can only move tasks assigned to you"
            )
    
    # Verify status exists
    from app.models import TaskStatus
    status = session.get(TaskStatus, status_id)
    if not status or status.tenant_id != current_user.tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Status not found")
    
    # Update task
    update_task(session, current_user.tenant_id, task_id, {"status_id": status_id})
    
    return {"data": {"id": task_id, "status_id": status_id}}


# ========== Subtasks ==========
@router.post("/tasks/{task_id}/subtasks")
async def create_subtask_endpoint(
    task_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Create a subtask."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_subtasks import create_subtask
    
    subtask_data = {
        "title": payload.get("title"),
        "description": payload.get("description"),
        "status_id": payload.get("status_id"),
        "priority_id": payload.get("priority_id"),
        "start_date": payload.get("start_date"),
        "due_date": payload.get("due_date"),
    }
    
    subtask = create_subtask(
        session,
        current_user.tenant_id,
        task_id,
        subtask_data,
        current_user.id
    )
    
    return {"data": {"id": subtask.id, "title": subtask.title}}


@router.get("/tasks/{task_id}/subtasks")
async def list_subtasks_endpoint(
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """List all subtasks for a task."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_subtasks import list_subtasks
    
    subtasks = list_subtasks(session, current_user.tenant_id, task_id)
    
    return {
        "data": [
            {
                "id": st.id,
                "title": st.title,
                "description": st.description,
                "status_id": st.status_id,
                "completion_percentage": st.completion_percentage,
            }
            for st in subtasks
        ]
    }


@router.patch("/subtasks/{subtask_id}")
async def update_subtask_endpoint(
    subtask_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Update a subtask."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_subtasks import update_subtask
    
    updates = {k: v for k, v in payload.items() if v is not None}
    subtask = update_subtask(session, current_user.tenant_id, subtask_id, updates)
    
    return {"data": {"id": subtask.id, "title": subtask.title}}


@router.delete("/subtasks/{subtask_id}")
async def delete_subtask_endpoint(
    subtask_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Delete a subtask."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_subtasks import delete_subtask
    
    delete_subtask(session, current_user.tenant_id, subtask_id)
    return {"data": {"id": subtask_id, "deleted": True}}


# ========== Project Duplication ==========
@router.post("/projects/{project_id}/duplicate")
async def duplicate_project_endpoint(
    project_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Duplicate a project with all tasks and subtasks."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_duplication import duplicate_project
    
    new_project = duplicate_project(
        session,
        current_user.tenant_id,
        project_id,
        payload.get("name"),
        current_user.id
    )
    
    return {"data": {"id": new_project.id, "name": new_project.name}}


@router.post("/tasks/{task_id}/duplicate")
async def duplicate_task_endpoint(
    task_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Duplicate a task (and optionally subtasks)."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_duplication import duplicate_task
    
    new_task = duplicate_task(
        session,
        current_user.tenant_id,
        task_id,
        payload.get("title"),
        payload.get("include_subtasks", True),
        current_user.id
    )
    
    return {"data": {"id": new_task.id, "title": new_task.title}}


# ========== Time Tracking ==========
@router.post("/time-entries")
async def create_time_entry_endpoint(
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Create a time entry."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_time import create_time_entry
    
    time_entry = create_time_entry(
        session,
        current_user.tenant_id,
        payload["task_id"],
        current_user.id,
        {
            "hours": payload.get("hours", 0),
            "date": payload.get("date"),
            "description": payload.get("description"),
            "is_billable": payload.get("is_billable", True)
        }
    )
    
    return {"data": {"id": time_entry.id, "hours": float(time_entry.hours)}}


@router.get("/time-entries")
async def list_time_entries_endpoint(
    task_id: Optional[int] = None,
    project_id: Optional[int] = None,
    start_date: Optional[str] = None,
    end_date: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """List time entries."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_time import list_time_entries
    from datetime import datetime as dt
    
    entries = list_time_entries(
        session,
        current_user.tenant_id,
        task_id=task_id,
        user_id=current_user.id if not current_user.is_super_admin else None,
        project_id=project_id,
        start_date=dt.fromisoformat(start_date).date() if start_date else None,
        end_date=dt.fromisoformat(end_date).date() if end_date else None
    )
    
    # Ensure timezone-aware datetime for proper parsing in frontend
    from datetime import timezone
    
    return {
        "data": [
            {
                "id": e.id,
                "task_id": e.task_id,
                "user_id": e.user_id,
                "hours": float(e.hours),
                "duration_minutes": int(float(e.hours) * 60),  # Convert hours to minutes
                "date": str(e.entry_date),
                "start_time": (e.created_at.replace(tzinfo=timezone.utc) if e.created_at.tzinfo is None else e.created_at).isoformat(),
                "end_time": (e.updated_at.replace(tzinfo=timezone.utc) if e.updated_at.tzinfo is None else e.updated_at).isoformat(),
                "description": e.description,
                "is_billable": e.is_billable,
                "created_at": (e.created_at.replace(tzinfo=timezone.utc) if e.created_at.tzinfo is None else e.created_at).isoformat()
            }
            for e in entries
        ]
    }


@router.post("/time-tracker/start")
async def start_time_tracker_endpoint(
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Start a time tracker."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_time import start_time_tracker
    
    tracker = start_time_tracker(
        session,
        current_user.tenant_id,
        current_user.id,
        payload.get("task_id"),
        payload.get("message")
    )
    
    # Ensure timezone-aware datetime for proper parsing in frontend
    from datetime import timezone
    start_dt = tracker.start_date_time
    if start_dt.tzinfo is None:
        start_dt = start_dt.replace(tzinfo=timezone.utc)
    created_dt = tracker.created_at
    if created_dt.tzinfo is None:
        created_dt = created_dt.replace(tzinfo=timezone.utc)
    
    return {
        "data": {
            "id": tracker.id,
            "task_id": tracker.task_id,
            "user_id": tracker.user_id,
            "start_time": start_dt.isoformat(),  # ISO format with timezone for proper parsing
            "start_date_time": start_dt.isoformat(),
            "message": tracker.message,
            "is_running": tracker.is_running,
            "created_at": created_dt.isoformat()
        }
    }


@router.post("/time-tracker/{tracker_id}/stop")
async def stop_time_tracker_endpoint(
    tracker_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Stop a time tracker."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_time import stop_time_tracker
    
    tracker = stop_time_tracker(
        session,
        current_user.tenant_id,
        tracker_id,
        current_user.id
    )
    
    return {"data": {"id": tracker.id, "duration": float(tracker.duration or 0)}}


@router.get("/time-tracker/active")
async def get_active_tracker_endpoint(
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get active time tracker for current user."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_time import get_active_tracker
    
    tracker = get_active_tracker(session, current_user.tenant_id, current_user.id)
    
    if not tracker:
        return {"data": None}
    
    # Ensure timezone-aware datetime for proper parsing in frontend
    from datetime import timezone
    start_dt = tracker.start_date_time
    if start_dt.tzinfo is None:
        start_dt = start_dt.replace(tzinfo=timezone.utc)
    created_dt = tracker.created_at
    if created_dt.tzinfo is None:
        created_dt = created_dt.replace(tzinfo=timezone.utc)
    
    return {
        "data": {
            "id": tracker.id,
            "task_id": tracker.task_id,
            "user_id": tracker.user_id,
            "start_time": start_dt.isoformat(),  # ISO format with timezone for proper parsing
            "start_date_time": start_dt.isoformat(),
            "message": tracker.message,
            "is_running": tracker.is_running,
            "created_at": created_dt.isoformat()
        }
    }


@router.get("/time-reports")
async def get_time_report_endpoint(
    project_id: Optional[int] = None,
    start_date: Optional[str] = None,
    end_date: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get time tracking reports and analytics."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_time import get_time_report
    from datetime import datetime as dt
    
    report = get_time_report(
        session,
        current_user.tenant_id,
        project_id=project_id,
        user_id=current_user.id if not current_user.is_super_admin else None,
        start_date=dt.fromisoformat(start_date).date() if start_date else None,
        end_date=dt.fromisoformat(end_date).date() if end_date else None
    )
    
    return {"data": report}


# ========== Communication Threads ==========
@router.post("/threads")
async def create_thread_endpoint(
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Create a thread/comment."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_threads import create_thread
    
    thread = create_thread(
        session,
        current_user.tenant_id,
        current_user.id,
        payload["comment"],
        payload.get("task_id"),
        payload.get("project_id"),
        payload.get("parent_id")
    )
    
    return {"data": {"id": thread.id, "comment": thread.comment}}


@router.get("/threads")
async def list_threads_endpoint(
    task_id: Optional[int] = None,
    project_id: Optional[int] = None,
    search: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """List threads/comments."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_threads import list_threads
    
    threads = list_threads(
        session,
        current_user.tenant_id,
        task_id=task_id,
        project_id=project_id,
        search=search
    )
    
    return {
        "data": [
            {
                "id": t.id,
                "comment": t.comment,
                "user_id": t.user_id,
                "parent_id": t.parent_id,
                "created_at": str(t.created_at),
                "replies_count": len(t.replies) if t.replies else 0
            }
            for t in threads
        ]
    }


# ========== Document Management ==========
@router.post("/documents")
async def upload_document_endpoint(
    file: UploadFile = File(...),
    task_id: Optional[int] = None,
    project_id: Optional[int] = None,
    folder_id: Optional[int] = None,
    category_id: Optional[int] = None,
    description: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Upload a document."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_documents import upload_document
    
    document = await upload_document(
        session,
        current_user.tenant_id,
        current_user.id,
        file,
        task_id=task_id,
        project_id=project_id,
        folder_id=folder_id,
        category_id=category_id,
        description=description
    )
    
    return {"data": {"id": document.id, "filename": document.original_filename}}


@router.get("/documents")
async def list_documents_endpoint(
    task_id: Optional[int] = None,
    project_id: Optional[int] = None,
    folder_id: Optional[int] = None,
    category_id: Optional[int] = None,
    search: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """List documents."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_documents import list_documents
    
    documents = list_documents(
        session,
        current_user.tenant_id,
        task_id=task_id,
        project_id=project_id,
        folder_id=folder_id,
        category_id=category_id,
        search=search
    )
    
    return {
        "data": [
            {
                "id": d.id,
                "filename": d.original_filename,
                "file_size": d.file_size,
                "mime_type": d.mime_type,
                "version": d.version,
                "created_at": str(d.created_at)
            }
            for d in documents
        ]
    }


# ========== Resource Allocation ==========
@router.post("/resources/allocate")
async def allocate_resource_endpoint(
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Allocate a resource to a project."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_resources import allocate_resource
    from decimal import Decimal
    from datetime import datetime as dt
    
    allocation = allocate_resource(
        session,
        current_user.tenant_id,
        payload["project_id"],
        payload["user_id"],
        Decimal(str(payload["allocated_hours"])),
        dt.fromisoformat(payload["start_date"]).date(),
        dt.fromisoformat(payload["end_date"]).date() if payload.get("end_date") else None
    )
    
    return {"data": {"id": allocation.id, "allocated_hours": float(allocation.allocated_hours)}}


@router.get("/resources/availability")
async def get_resource_availability_endpoint(
    user_id: int,
    start_date: str,
    end_date: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get resource availability."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_resources import get_resource_availability
    from datetime import datetime as dt
    
    availability = get_resource_availability(
        session,
        current_user.tenant_id,
        user_id,
        dt.fromisoformat(start_date).date(),
        dt.fromisoformat(end_date).date()
    )
    
    return {"data": availability}


# ========== Dashboard Metrics ==========
@router.get("/dashboard/metrics")
async def get_dashboard_metrics_endpoint(
    project_id: Optional[int] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get dashboard metrics."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_dashboard import get_dashboard_metrics
    
    metrics = get_dashboard_metrics(
        session,
        current_user.tenant_id,
        user_id=current_user.id if not current_user.is_super_admin else None,
        project_id=project_id
    )
    
    return {"data": metrics}


@router.get("/dashboard/employee-progress")
async def get_employee_progress_endpoint(
    project_id: Optional[int] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get employee assignment and progress overview."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    
    from app.services.tasks_dashboard import get_employee_progress_overview
    
    progress = get_employee_progress_overview(
        session,
        current_user.tenant_id,
        project_id=project_id
    )
    
    return {"data": progress}


# ========== Task Dependencies ==========
@router.post("/tasks/{task_id}/dependencies")
async def create_dependency_endpoint(
    task_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Create a task dependency."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_dependencies import create_dependency
    
    dependency = create_dependency(
        session,
        current_user.tenant_id,
        task_id,
        payload["depends_on_task_id"],
        payload.get("dependency_type", "blocks")
    )
    
    return {"data": {"id": dependency.id, "task_id": dependency.task_id, "depends_on_task_id": dependency.depends_on_task_id}}


@router.get("/tasks/{task_id}/dependencies")
async def get_task_dependencies_endpoint(
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get dependencies for a task."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_dependencies import get_task_dependencies
    
    blocking_tasks = get_task_dependencies(session, current_user.tenant_id, task_id)
    return {
        "data": [
            {
                "id": t.id,
                "title": t.title,
                "completion_percentage": t.completion_percentage,
            }
            for t in blocking_tasks
        ]
    }


@router.get("/tasks/{task_id}/dependencies/blocking")
async def check_dependency_blocking_endpoint(
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Check if a task is blocked by dependencies."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_dependencies import check_dependency_blocking
    
    blocking_info = check_dependency_blocking(session, current_user.tenant_id, task_id)
    return {"data": blocking_info}


@router.delete("/dependencies/{dependency_id}")
async def delete_dependency_endpoint(
    dependency_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Delete a dependency."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_dependencies import delete_dependency
    
    delete_dependency(session, current_user.tenant_id, dependency_id)
    return {"data": {"id": dependency_id, "deleted": True}}


# ========== Recurring Tasks ==========
@router.post("/tasks/{task_id}/recurring")
async def create_recurring_task_endpoint(
    task_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Create a recurring task configuration."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_recurring import create_recurring_task
    
    recurring = create_recurring_task(
        session,
        current_user.tenant_id,
        task_id,
        payload
    )
    
    return {"data": {"id": recurring.id, "task_id": recurring.task_id, "recurrence_type": recurring.recurrence_type}}


@router.get("/tasks/{task_id}/recurring")
async def get_recurring_task_endpoint(
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get recurring task configuration."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_recurring import get_recurring_task
    
    recurring = get_recurring_task(session, current_user.tenant_id, task_id)
    if not recurring:
        return {"data": None}
    
    return {
        "data": {
            "id": recurring.id,
            "task_id": recurring.task_id,
            "recurrence_type": recurring.recurrence_type,
            "recurrence_interval": recurring.recurrence_interval,
            "days_of_week": recurring.days_of_week,
            "end_date": str(recurring.end_date) if recurring.end_date else None,
            "max_occurrences": recurring.max_occurrences,
            "occurrence_count": recurring.occurrence_count,
            "is_active": recurring.is_active,
        }
    }


@router.patch("/tasks/{task_id}/recurring")
async def update_recurring_task_endpoint(
    task_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Update a recurring task configuration."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_recurring import update_recurring_task
    
    recurring = update_recurring_task(session, current_user.tenant_id, task_id, payload)
    return {"data": {"id": recurring.id, "task_id": recurring.task_id}}


@router.delete("/tasks/{task_id}/recurring")
async def delete_recurring_task_endpoint(
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Delete a recurring task configuration."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_recurring import delete_recurring_task
    
    delete_recurring_task(session, current_user.tenant_id, task_id)
    return {"data": {"task_id": task_id, "deleted": True}}


@router.get("/recurring-tasks")
async def list_recurring_tasks(
    is_active: Optional[bool] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """List all recurring tasks."""
    _require_tasks_entitlement(session, current_user.tenant_id)
    from app.services.tasks_recurring import list_recurring_tasks as list_recurring_tasks_service
    
    recurring_tasks = list_recurring_tasks_service(session, current_user.tenant_id, is_active=is_active)
    
    return {
        "data": [
            {
                "id": r.id,
                "task_id": r.task_id,
                "recurrence_type": r.recurrence_type,
                "is_active": r.is_active,
                "occurrence_count": r.occurrence_count,
            }
            for r in recurring_tasks
        ]
    }

