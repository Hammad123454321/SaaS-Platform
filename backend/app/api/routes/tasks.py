"""
Task Management API Routes

Dedicated routes for the Tasks module.
All operations use async Beanie for MongoDB.
"""
import logging
from typing import Optional, List, Any
from fastapi import APIRouter, Depends, HTTPException, status, UploadFile, File

from beanie import PydanticObjectId

logger = logging.getLogger(__name__)

from app.api.authz import require_permission
from app.models import User, ModuleCode, ModuleEntitlement, Task, Project, TaskStatus, TaskPriority, Client, UserRole
from app.models.role import PermissionCode, Role
from app.models.tasks import TaskAssignment
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

router = APIRouter(prefix="/modules/tasks", tags=["tasks"])


async def _require_tasks_entitlement(tenant_id: str) -> None:
    """Check if Tasks module is enabled for tenant."""
    entitlement = await ModuleEntitlement.find_one(
            ModuleEntitlement.tenant_id == tenant_id,
            ModuleEntitlement.module_code == ModuleCode.TASKS,
        ModuleEntitlement.enabled == True,
        )
    
    if not entitlement:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Tasks module not enabled"
        )


async def _is_staff_only(user: User) -> bool:
    """Check if user is staff (not admin). Staff can only access their assigned tasks."""
    if user.is_super_admin:
        return False
    user_roles = await UserRole.find(UserRole.user_id == str(user.id)).to_list()
    role_names = []
    for ur in user_roles:
        role = await Role.get(ur.role_id)
        if role:
            role_names.append(role.name)
    # If user has company_admin or admin role, they're not staff-only
    return not any(name in ["company_admin", "admin"] for name in role_names)


async def _get_task_response(t: Task, tenant_id: str, user_id: str) -> dict:
    """Helper to build task response dict."""
    # Get status and priority names/colors
    task_status = await TaskStatus.get(t.status_id) if t.status_id else None
    priority = await TaskPriority.get(t.priority_id) if t.priority_id else None
    project = await Project.get(t.project_id) if t.project_id else None
    
    # Get assignees
    assignees = []
    if t.assignee_ids:
        for aid in t.assignee_ids:
            user = await User.get(aid)
            if user:
                assignees.append({
                    "id": str(user.id),
                    "first_name": user.email.split("@")[0].split(".")[0].capitalize(),
                    "last_name": ""
                })
    
    return {
        "id": str(t.id),
        "title": t.title,
        "description": t.description,
        "status_id": t.status_id,
        "status_name": task_status.name if task_status else None,
        "status_color": task_status.color if task_status else None,
        "priority_id": t.priority_id,
        "priority_name": priority.name if priority else None,
        "priority_color": priority.color if priority else None,
        "project_id": t.project_id,
        "project": {"id": str(project.id), "title": project.name} if project else None,
        "due_date": str(t.due_date) if t.due_date else None,
        "start_date": str(t.start_date) if t.start_date else None,
        "assignees": assignees,
        "user_id": t.assignee_ids or [],
        "parent_id": t.parent_id,
        "created_at": str(t.created_at),
        "completion_percentage": t.completion_percentage,
        "is_favorite": await is_task_favorite(tenant_id, str(t.id), user_id),
        "is_pinned": await is_task_pinned(tenant_id, str(t.id), user_id),
    }


# ========== Dropdown Data Endpoints ==========
@router.get("/dropdown/clients")
async def get_clients_dropdown(
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get clients for dropdown."""
    await _require_tasks_entitlement(current_user.tenant_id)
    clients = await list_clients(current_user.tenant_id)
    return {
        "data": [
            {
                "id": str(c.id),
                "name": f"{c.first_name} {c.last_name}",
                "email": c.email,
                "company": c.company,
            }
            for c in clients
        ]
    }


@router.get("/dropdown/projects")
async def get_projects_dropdown(
    client_id: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get projects for dropdown."""
    await _require_tasks_entitlement(current_user.tenant_id)
    projects = await list_projects(current_user.tenant_id, client_id=client_id)
    return {
        "data": [
            {
                "id": str(p.id),
                "name": p.name,
                "client_id": p.client_id,
            }
            for p in projects
        ]
    }


@router.get("/dropdown/statuses")
async def get_statuses_dropdown(
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get statuses for dropdown."""
    await _require_tasks_entitlement(current_user.tenant_id)
    statuses = await list_statuses(current_user.tenant_id)
    return {
        "data": [
            {
                "id": str(s.id),
                "name": s.name,
                "color": s.color,
            }
            for s in statuses
        ]
    }


@router.get("/dropdown/priorities")
async def get_priorities_dropdown(
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get priorities for dropdown."""
    await _require_tasks_entitlement(current_user.tenant_id)
    priorities = await list_priorities(current_user.tenant_id)
    return {
        "data": [
            {
                "id": str(p.id),
                "name": p.name,
                "color": p.color,
            }
            for p in priorities
        ]
    }


@router.get("/dropdown/users")
async def get_users_dropdown(
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get users for dropdown (assignees)."""
    await _require_tasks_entitlement(current_user.tenant_id)
    users = await User.find(User.tenant_id == current_user.tenant_id).to_list()
    return {
        "data": [
            {
                "id": str(u.id),
                "first_name": u.email.split("@")[0].split(".")[0].capitalize(),
                "last_name": u.email.split("@")[0].split(".")[1].capitalize() if "." in u.email.split("@")[0] else "",
                "email": u.email,
            }
            for u in users
        ]
    }


# ========== My Tasks Endpoint ==========
@router.get("/my-tasks")
async def get_my_tasks(
    project_id: Optional[str] = None,
    status_id: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get tasks assigned to the current user (for staff members)."""
    await _require_tasks_entitlement(current_user.tenant_id)
    
    # Filter by current user as assignee
    tasks = await list_tasks(
        current_user.tenant_id,
        project_id=project_id,
        status_id=status_id,
        assignee_id=str(current_user.id)
    )
    
    result = []
    for t in tasks:
        result.append(await _get_task_response(t, current_user.tenant_id, str(current_user.id)))
    
    return {"data": result}


# ========== Records Endpoint (Generic) ==========
@router.get("/records")
async def list_records(
    resource: str,
    project_id: Optional[str] = None,
    status_id: Optional[str] = None,
    assignee_id: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """List records for a resource."""
    await _require_tasks_entitlement(current_user.tenant_id)
    
    # Check if user is staff-only (not admin)
    is_staff = await _is_staff_only(current_user)
    
    if resource == "tasks":
        # Staff can only see tasks assigned to them
        if is_staff:
            assignee_id = str(current_user.id)
        
        tasks = await list_tasks(
            current_user.tenant_id,
            project_id=project_id,
            status_id=status_id,
            assignee_id=assignee_id
        )
        
        result = []
        for t in tasks:
            result.append(await _get_task_response(t, current_user.tenant_id, str(current_user.id)))
        
        return {"data": result}
    
    elif resource == "projects":
        # Staff can only see projects that have tasks assigned to them
        if is_staff:
            # Get project IDs from tasks assigned to this user
            assignments = await TaskAssignment.find(
                TaskAssignment.user_id == str(current_user.id)
            ).to_list()
            task_ids = [a.task_id for a in assignments]

            if task_ids:
                tasks = await Task.find(
                    {"_id": {"$in": [PydanticObjectId(tid) for tid in task_ids]}}
                ).to_list()
                project_ids = list(set(t.project_id for t in tasks))
                all_projects = await list_projects(current_user.tenant_id)
                projects = [p for p in all_projects if str(p.id) in project_ids]
            else:
                projects = []
        else:
            projects = await list_projects(current_user.tenant_id)

        result = []
        for p in projects:
            client = await Client.get(p.client_id) if p.client_id else None
            result.append(
                {
                    "id": str(p.id),
                    "name": p.name,
                    "title": p.name,
                    "description": p.description,
                    "client_id": p.client_id,
                    "client": {
                        "id": str(client.id),
                        "first_name": client.first_name,
                        "last_name": client.last_name,
                    }
                    if client
                    else None,
                    "budget": float(p.budget) if p.budget else None,
                    "deadline": str(p.deadline) if p.deadline else None,
                }
            )

        return {"data": result}
    
    elif resource == "clients":
        # Staff can only see clients from projects they're assigned to
        if is_staff:
            assignments = await TaskAssignment.find(
                TaskAssignment.user_id == str(current_user.id)
            ).to_list()
            task_ids = [a.task_id for a in assignments]

            if task_ids:
                tasks = await Task.find(
                    {"_id": {"$in": [PydanticObjectId(tid) for tid in task_ids]}}
                ).to_list()
                project_ids = list(set(t.project_id for t in tasks))
                projects = await Project.find(
                    {"_id": {"$in": [PydanticObjectId(pid) for pid in project_ids]}}
                ).to_list()
                client_ids = list(set(p.client_id for p in projects if p.client_id))
                all_clients = await list_clients(current_user.tenant_id)
                clients = [c for c in all_clients if str(c.id) in client_ids]
            else:
                clients = []
        else:
            clients = await list_clients(current_user.tenant_id)

        return {
            "data": [
                {
                    "id": str(c.id),
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
        statuses = await list_statuses(current_user.tenant_id)
        return {
            "data": [
                {
                    "id": str(s.id),
                    "name": s.name,
                    "title": s.name,
                    "color": s.color,
                }
                for s in statuses
            ]
        }
    
    elif resource == "priorities":
        priorities = await list_priorities(current_user.tenant_id)
        return {
            "data": [
                {
                    "id": str(p.id),
                    "name": p.name,
                    "title": p.name,
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
) -> dict:
    """Create a record."""
    await _require_tasks_entitlement(current_user.tenant_id)
    
    is_staff = await _is_staff_only(current_user)
    
    # Staff can only create tasks, not clients/projects/statuses/priorities
    if is_staff and resource not in ["tasks"]:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Staff members can only create tasks"
        )
    
    if resource == "tasks":
        # Access control
        if not await can_user_create_task(current_user):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="You do not have permission to create tasks."
            )
        
        assignee_ids = payload.get("assignee_ids") or payload.get("user_id", [])
        
        # Staff can only create tasks assigned to themselves
        user_roles = await UserRole.find(UserRole.user_id == str(current_user.id)).to_list()
        role_names = []
        for ur in user_roles:
            role = await Role.get(ur.role_id)
            if role:
                role_names.append(role.name.lower())
        if "staff" in role_names:
            assignee_ids = [str(current_user.id)]
        
        task_data = {
            "title": payload.get("title", ""),
            "description": payload.get("description"),
            "notes": payload.get("notes"),
            "project_id": str(payload.get("project_id") or payload.get("project", "")),
            "status_id": str(payload.get("status_id", "")) if payload.get("status_id") else None,
            "priority_id": str(payload.get("priority_id", "")) if payload.get("priority_id") else None,
            "task_list_id": str(payload.get("task_list_id", "")) if payload.get("task_list_id") else None,
            "start_date": payload.get("start_date"),
            "due_date": payload.get("due_date"),
            "completion_percentage": payload.get("completion_percentage", 0),
            "assignee_ids": [str(aid) for aid in assignee_ids] if assignee_ids else [],
        }
        task = await create_task(current_user.tenant_id, str(current_user.id), task_data)
        return {"data": {"id": str(task.id), "title": task.title}}
    
    elif resource == "projects":
        if not await can_user_create_project(current_user):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="You do not have permission to create projects."
            )
        
        project_data = {
            "name": payload.get("name") or payload.get("title", ""),
            "description": payload.get("description"),
            "client_id": str(payload.get("client_id", "")) if payload.get("client_id") else None,
            "budget": payload.get("budget"),
            "deadline": payload.get("deadline") or payload.get("end_date"),
            "start_date": payload.get("start_date"),
        }
        project = await create_project(current_user.tenant_id, str(current_user.id), project_data)
        return {"data": {"id": str(project.id), "name": project.name}}
    
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
        client = await create_client(current_user.tenant_id, client_data)
        return {"data": {"id": str(client.id), "first_name": client.first_name, "last_name": client.last_name}}
    
    elif resource == "statuses":
        status_data = {
            "name": payload.get("name") or payload.get("title", ""),
            "color": payload.get("color", "#6b7280"),
            "category": payload.get("category", "todo"),
            "display_order": payload.get("display_order", 0),
        }
        status_obj = await create_status(current_user.tenant_id, status_data)
        return {"data": {"id": str(status_obj.id), "name": status_obj.name}}
    
    elif resource == "priorities":
        priority_data = {
            "name": payload.get("name") or payload.get("title", ""),
            "color": payload.get("color", "#6b7280"),
            "level": payload.get("level", 0),
            "display_order": payload.get("display_order", 0),
        }
        priority = await create_priority(current_user.tenant_id, priority_data)
        return {"data": {"id": str(priority.id), "name": priority.name}}
    
    else:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Unknown resource: {resource}")


@router.put("/records/{record_id}")
async def update_record(
    record_id: str,
    resource: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Update a record."""
    await _require_tasks_entitlement(current_user.tenant_id)
    
    is_staff = await _is_staff_only(current_user)
    
    if resource == "tasks":
        task = await get_task(current_user.tenant_id, record_id)
        if not task:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
        
        if not await can_user_update_task(current_user, task):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="You do not have permission to update this task."
            )
        
        # Staff can only change status
        if is_staff:
            allowed_fields = ["status_id", "completion_percentage"]
            updates = {k: v for k, v in payload.items() if k in allowed_fields}
        else:
            updates = {}
            if "title" in payload:
                updates["title"] = payload["title"]
            if "description" in payload:
                updates["description"] = payload["description"]
            if "notes" in payload:
                updates["notes"] = payload["notes"]
            if "status_id" in payload:
                updates["status_id"] = str(payload["status_id"]) if payload["status_id"] else None
            if "priority_id" in payload:
                updates["priority_id"] = str(payload["priority_id"]) if payload["priority_id"] else None
            if "start_date" in payload:
                updates["start_date"] = payload["start_date"]
            if "due_date" in payload:
                updates["due_date"] = payload["due_date"]
            if "completion_percentage" in payload:
                updates["completion_percentage"] = payload["completion_percentage"]
            if "assignee_ids" in payload or "user_id" in payload:
                aids = payload.get("assignee_ids") or payload.get("user_id", [])
                updates["assignee_ids"] = [str(aid) for aid in aids] if aids else []
        
        task = await update_task(current_user.tenant_id, record_id, updates)
        return {"data": {"id": str(task.id), "title": task.title}}
    
    elif resource == "projects":
        if is_staff:
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Staff cannot update projects")
        
        project = await get_project(current_user.tenant_id, record_id)
        if not project:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found")
        
        if not await can_user_update_project(current_user, project):
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="You cannot update this project.")
        
        updates = {}
        if "name" in payload or "title" in payload:
            updates["name"] = payload.get("name") or payload.get("title")
        if "description" in payload:
            updates["description"] = payload["description"]
        if "budget" in payload:
            updates["budget"] = payload["budget"]
        if "deadline" in payload or "end_date" in payload:
            updates["deadline"] = payload.get("deadline") or payload.get("end_date")
        if "start_date" in payload:
            updates["start_date"] = payload["start_date"]
        if "status" in payload:
            updates["status"] = payload["status"]
        
        project = await update_project(current_user.tenant_id, record_id, updates)
        return {"data": {"id": str(project.id), "name": project.name}}
    
    elif resource == "clients":
        if is_staff:
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Staff cannot update clients")
        
        updates = {}
        for field in ["first_name", "last_name", "email", "phone", "company", "address", "notes"]:
            if field in payload:
                updates[field] = payload[field]
        
        client = await update_client(current_user.tenant_id, record_id, updates)
        return {"data": {"id": str(client.id), "first_name": client.first_name}}
    
    elif resource == "statuses":
        if is_staff:
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Staff cannot update statuses")
        
        updates = {}
        for field in ["name", "color", "category", "display_order"]:
            if field in payload:
                updates[field] = payload[field]
        if "title" in payload:
            updates["name"] = payload["title"]
        
        status_obj = await update_status(current_user.tenant_id, record_id, updates)
        return {"data": {"id": str(status_obj.id), "name": status_obj.name}}
    
    elif resource == "priorities":
        if is_staff:
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Staff cannot update priorities")
        
        updates = {}
        for field in ["name", "color", "level", "display_order"]:
            if field in payload:
                updates[field] = payload[field]
        if "title" in payload:
            updates["name"] = payload["title"]
        
        priority = await update_priority(current_user.tenant_id, record_id, updates)
        return {"data": {"id": str(priority.id), "name": priority.name}}
    
    else:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Unknown resource: {resource}")


@router.delete("/records/{record_id}")
async def delete_record(
    record_id: str,
    resource: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Delete a record."""
    await _require_tasks_entitlement(current_user.tenant_id)
    
    is_staff = await _is_staff_only(current_user)
    
    if is_staff:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Staff cannot delete records")
    
    if resource == "tasks":
        task = await get_task(current_user.tenant_id, record_id)
        if not task:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
        
        if not await can_user_delete_task(current_user, task):
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="You cannot delete this task.")
        
        await delete_task(current_user.tenant_id, record_id)
        return {"message": "Task deleted"}
    
    elif resource == "projects":
        project = await get_project(current_user.tenant_id, record_id)
        if not project:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found")
        
        if not await can_user_delete_project(current_user, project):
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="You cannot delete this project.")
        
        await delete_project(current_user.tenant_id, record_id)
        return {"message": "Project deleted"}
    
    elif resource == "clients":
        await delete_client(current_user.tenant_id, record_id)
        return {"message": "Client deleted"}
    
    elif resource == "statuses":
        await delete_status(current_user.tenant_id, record_id)
        return {"message": "Status deleted"}
    
    elif resource == "priorities":
        await delete_priority(current_user.tenant_id, record_id)
        return {"message": "Priority deleted"}
    
    else:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Unknown resource: {resource}")
    

# ========== Task-specific Endpoints ==========
@router.post("/tasks/{task_id}/favorite")
async def toggle_task_favorite(
    task_id: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Toggle favorite status for a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    is_fav = payload.get("is_favorite", True)
    await toggle_favorite(current_user.tenant_id, task_id, str(current_user.id), is_fav)
    return {"message": "Favorite toggled", "is_favorite": is_fav}


@router.post("/tasks/{task_id}/pin")
async def toggle_task_pin(
    task_id: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Toggle pin status for a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    is_pin = payload.get("is_pinned", True)
    await toggle_pin(current_user.tenant_id, task_id, str(current_user.id), is_pin)
    return {"message": "Pin toggled", "is_pinned": is_pin}


@router.post("/tasks/{task_id}/comments")
async def add_task_comment(
    task_id: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Add a comment to a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    comment_text = payload.get("comment", "")
    comment = await add_comment(current_user.tenant_id, task_id, str(current_user.id), comment_text)
    return {"data": {"id": str(comment.id), "comment": comment.comment}}


@router.get("/tasks/{task_id}/comments")
async def get_task_comments(
    task_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get comments for a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    comments = await list_comments(current_user.tenant_id, task_id)
    
    result = []
    for c in comments:
        user = await User.get(c.user_id) if c.user_id else None
        result.append({
            "id": str(c.id),
                "comment": c.comment,
                "user_id": c.user_id,
            "user_name": user.email if user else None,
                "created_at": str(c.created_at),
        })
    
    return {"data": result}


@router.get("/tasks/{task_id}")
async def get_task_details(
    task_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get task details."""
    await _require_tasks_entitlement(current_user.tenant_id)
    task = await get_task(current_user.tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    return {"data": await _get_task_response(task, current_user.tenant_id, str(current_user.id))}


@router.post("/tasks/{task_id}/duplicate")
async def duplicate_task_endpoint(
    task_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Duplicate a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    
    if not await can_user_create_task(current_user):
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="You cannot create tasks.")
    
    new_task = await duplicate_task(current_user.tenant_id, task_id, str(current_user.id))
    return {"data": {"id": str(new_task.id), "title": new_task.title}}


# ========== Subtasks ==========
@router.get("/tasks/{task_id}/subtasks")
async def get_task_subtasks(
    task_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get subtasks for a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_subtasks import list_subtasks
    
    subtasks = await list_subtasks(current_user.tenant_id, task_id)
    
    result = []
    for t in subtasks:
        result.append(await _get_task_response(t, current_user.tenant_id, str(current_user.id)))
    
    return {"data": result}


@router.post("/tasks/{task_id}/subtasks")
async def create_task_subtask(
    task_id: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Create a subtask."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_subtasks import create_subtask
    
    if not await can_user_create_task(current_user):
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="You cannot create tasks.")
    
    subtask_data = {
        "title": payload.get("title", ""),
        "description": payload.get("description"),
        "status_id": str(payload.get("status_id", "")) if payload.get("status_id") else None,
        "priority_id": str(payload.get("priority_id", "")) if payload.get("priority_id") else None,
    }
    
    subtask = await create_subtask(current_user.tenant_id, task_id, subtask_data, str(current_user.id))
    return {"data": {"id": str(subtask.id), "title": subtask.title}}


# ========== Time Entries ==========
@router.get("/tasks/{task_id}/time-entries")
async def get_task_time_entries(
    task_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get time entries for a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_time import list_time_entries
    
    entries = await list_time_entries(current_user.tenant_id, task_id=task_id)
    
    result = []
    for e in entries:
        user = await User.get(e.user_id) if e.user_id else None
        result.append({
            "id": str(e.id),
            "hours": float(e.hours) if e.hours else 0,
            "date": str(e.entry_date) if e.entry_date else None,
            "description": e.description,
            "is_billable": e.is_billable,
            "user_id": e.user_id,
            "user_name": user.email if user else None,
            "created_at": str(e.created_at),
        })
    
    return {"data": result}


@router.post("/tasks/{task_id}/time-entries")
async def add_time_entry(
    task_id: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Add a time entry to a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_time import create_time_entry
    
    time_data = {
            "hours": payload.get("hours", 0),
            "date": payload.get("date"),
            "description": payload.get("description"),
        "is_billable": payload.get("is_billable", True),
    }
    
    entry = await create_time_entry(current_user.tenant_id, task_id, str(current_user.id), time_data)
    return {"data": {"id": str(entry.id), "hours": float(entry.hours) if entry.hours else 0}}


# ========== Dependencies ==========
@router.get("/tasks/{task_id}/dependencies")
async def get_task_dependencies(
    task_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get dependencies for a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_dependencies import get_task_dependencies as get_deps, get_tasks_depending_on
    
    blocking = await get_deps(current_user.tenant_id, task_id)
    blocked_by = await get_tasks_depending_on(current_user.tenant_id, task_id)
    
    return {
        "data": {
            "blocking": [{"id": str(t.id), "title": t.title} for t in blocking],
            "blocked_by": [{"id": str(t.id), "title": t.title} for t in blocked_by],
        }
    }


@router.post("/tasks/{task_id}/dependencies")
async def add_task_dependency(
    task_id: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Add a dependency to a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_dependencies import create_dependency
    
    depends_on_task_id = payload.get("depends_on_task_id") or payload.get("blocking_task_id")
    dependency_type = payload.get("dependency_type", "blocks")
    
    dep = await create_dependency(current_user.tenant_id, task_id, depends_on_task_id, dependency_type)
    return {"data": {"id": str(dep.id)}}


@router.delete("/tasks/{task_id}/dependencies/{dependency_id}")
async def remove_task_dependency(
    task_id: str,
    dependency_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Remove a dependency."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_dependencies import delete_dependency
    
    await delete_dependency(current_user.tenant_id, dependency_id)
    return {"message": "Dependency removed"}


# ========== Tags ==========
@router.get("/tasks/{task_id}/tags")
async def get_task_tags_endpoint(
    task_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get tags for a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_tags import get_task_tags
    
    tags = await get_task_tags(current_user.tenant_id, task_id)
    return {"data": [{"id": str(t.id), "name": t.name, "color": t.color} for t in tags]}


@router.post("/tasks/{task_id}/tags")
async def add_task_tag(
    task_id: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Assign tags to a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_tags import assign_tags_to_task
    
    tag_ids = payload.get("tag_ids", [])
    await assign_tags_to_task(current_user.tenant_id, task_id, [str(tid) for tid in tag_ids])
    return {"message": "Tags assigned"}


# ========== Tags Management ==========
@router.get("/tags")
async def list_tags_endpoint(
    search: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """List all tags."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_tags import list_tags
    
    tags = await list_tags(current_user.tenant_id, search=search)
    return {"data": [{"id": str(t.id), "name": t.name, "color": t.color} for t in tags]}


@router.post("/tags")
async def create_tag_endpoint(
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Create a tag."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_tags import create_tag
    
    tag_data = {
        "name": payload.get("name", ""),
        "color": payload.get("color", "#6b7280"),
    }
    tag = await create_tag(current_user.tenant_id, tag_data)
    return {"data": {"id": str(tag.id), "name": tag.name, "color": tag.color}}


# ========== Milestones ==========
@router.get("/projects/{project_id}/milestones")
async def get_project_milestones(
    project_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get milestones for a project."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_milestones import list_milestones
    
    milestones = await list_milestones(current_user.tenant_id, project_id=project_id)
    return {
        "data": [
            {
                "id": str(m.id),
                "title": m.title,
                "description": m.description,
                "due_date": str(m.due_date) if m.due_date else None,
                "is_completed": m.is_completed,
            }
            for m in milestones
        ]
    }


@router.post("/projects/{project_id}/milestones")
async def create_project_milestone(
    project_id: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Create a milestone."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_milestones import create_milestone
    
    milestone_data = {
        "title": payload.get("title", ""),
        "description": payload.get("description"),
        "due_date": payload.get("due_date"),
        "is_completed": payload.get("is_completed", False),
    }
    milestone = await create_milestone(current_user.tenant_id, project_id, milestone_data)
    return {"data": {"id": str(milestone.id), "title": milestone.title}}


# ========== Task Lists ==========
@router.get("/projects/{project_id}/lists")
async def get_project_lists(
    project_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get task lists for a project."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_lists import list_task_lists
    
    lists = await list_task_lists(current_user.tenant_id, project_id=project_id)
    return {
        "data": [
            {
                "id": str(tl.id),
                "name": tl.name,
                "description": tl.description,
                "display_order": tl.display_order,
            }
            for tl in lists
        ]
    }


@router.post("/projects/{project_id}/lists")
async def create_project_list(
    project_id: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Create a task list."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_lists import create_task_list
    
    list_data = {
        "name": payload.get("name", ""),
        "description": payload.get("description"),
    }
    task_list = await create_task_list(current_user.tenant_id, project_id, list_data)
    return {"data": {"id": str(task_list.id), "name": task_list.name}}


# ========== Dashboard ==========
@router.get("/dashboard")
async def get_tasks_dashboard(
    project_id: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get dashboard metrics."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_dashboard import get_dashboard_metrics
    
    is_staff = await _is_staff_only(current_user)
    user_id = str(current_user.id) if is_staff else None
    
    metrics = await get_dashboard_metrics(current_user.tenant_id, user_id=user_id, project_id=project_id)
    return {"data": metrics}


# ========== Kanban View ==========
@router.get("/kanban")
async def get_kanban_view(
    project_id: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get kanban board view."""
    await _require_tasks_entitlement(current_user.tenant_id)
    
    is_staff = await _is_staff_only(current_user)
    assignee_id = str(current_user.id) if is_staff else None
    
    # Get statuses
    statuses = await list_statuses(current_user.tenant_id)
    
    # Get tasks
    tasks = await list_tasks(current_user.tenant_id, project_id=project_id, assignee_id=assignee_id)
    
    # Group by status
    columns = {}
    for s in statuses:
        columns[str(s.id)] = {
            "id": str(s.id),
            "name": s.name,
            "color": s.color,
            "tasks": []
        }
    
    for t in tasks:
        if t.status_id and t.status_id in columns:
            columns[t.status_id]["tasks"].append(
                await _get_task_response(t, current_user.tenant_id, str(current_user.id))
            )
    
    return {"data": {"columns": list(columns.values())}}


# ========== Activity Log ==========
@router.get("/tasks/{task_id}/activity")
async def get_task_activity_log(
    task_id: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get activity log for a task."""
    await _require_tasks_entitlement(current_user.tenant_id)
    from app.services.tasks_activity import get_entity_activity
    
    activities = await get_entity_activity(current_user.tenant_id, "task", task_id)
    
    result = []
    for a in activities:
        user = await User.get(a.user_id) if a.user_id else None
        result.append({
            "id": str(a.id),
            "action": a.action,
            "description": a.description,
            "user_id": a.user_id,
            "user_name": user.email if user else None,
            "created_at": str(a.created_at),
        })
    
    return {"data": result}
