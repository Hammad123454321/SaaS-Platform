"""
Task Management Service

Business logic for task management operations.
All operations are tenant-isolated.
All functions are async and use Beanie for MongoDB.
"""
import logging
import re
from datetime import datetime, date
from typing import Optional, List, Dict, Any
from decimal import Decimal

from beanie import PydanticObjectId
from fastapi import HTTPException, status

from app.models import (
    User,
    Task,
    Project,
    Client,
    TaskStatus,
    TaskPriority,
    TaskList,
    TaskComment,
    TaskAttachment,
    TaskFavorite,
    TaskPin,
    Tag,
    Milestone,
    TimeEntry,
)
from app.models.tasks import TaskStatusCategory

logger = logging.getLogger(__name__)


# ========== Client Operations ==========
async def create_client(tenant_id: str, client_data: Dict[str, Any]) -> Client:
    """Create a new client."""
    # Check if email already exists for this tenant
    existing = await Client.find_one(
        Client.tenant_id == tenant_id,
        Client.email == client_data["email"]
    )
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Client with this email already exists"
        )
    
    client = Client(
        tenant_id=tenant_id,
        **client_data
    )
    await client.insert()
    return client


async def get_client(tenant_id: str, client_id: str) -> Optional[Client]:
    """Get a client by ID."""
    return await Client.find_one(
        Client.id == PydanticObjectId(client_id),
        Client.tenant_id == tenant_id
    )


async def list_clients(tenant_id: str, search: Optional[str] = None) -> List[Client]:
    """List all clients for a tenant."""
    query = Client.find(Client.tenant_id == tenant_id)
    
    if search:
        # Use regex for case-insensitive search
        search_regex = re.compile(f".*{re.escape(search)}.*", re.IGNORECASE)
        query = Client.find(
            Client.tenant_id == tenant_id,
            {
                "$or": [
                    {"first_name": {"$regex": search_regex}},
                    {"last_name": {"$regex": search_regex}},
                    {"email": {"$regex": search_regex}},
                    {"company": {"$regex": search_regex}}
                ]
            }
        )
    
    return await query.sort(-Client.created_at).to_list()


async def update_client(tenant_id: str, client_id: str, updates: Dict[str, Any]) -> Client:
    """Update a client."""
    client = await get_client(tenant_id, client_id)
    if not client:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Client not found")
    
    # Check email uniqueness if email is being updated
    if "email" in updates and updates["email"] != client.email:
        existing = await Client.find_one(
            Client.tenant_id == tenant_id,
            Client.email == updates["email"],
            Client.id != PydanticObjectId(client_id)
        )
        if existing:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Client with this email already exists"
            )
    
    for key, value in updates.items():
        if hasattr(client, key):
            setattr(client, key, value)
    
    client.updated_at = datetime.utcnow()
    await client.save()
    return client


async def delete_client(tenant_id: str, client_id: str) -> None:
    """Delete a client. Fails if client has projects."""
    client = await get_client(tenant_id, client_id)
    if not client:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Client not found")
    
    # Check if client has projects
    projects_count = await Project.find(
        Project.tenant_id == tenant_id,
        Project.client_id == client_id
    ).count()
    
    if projects_count > 0:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete client with existing projects"
        )
    
    await client.delete()


# ========== Project Operations ==========
async def create_project(tenant_id: str, user_id: str, project_data: Dict[str, Any]) -> Project:
    """Create a new project."""
    # Verify client exists and belongs to tenant
    client = await get_client(tenant_id, project_data["client_id"])
    if not client:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Client not found")
    
    project = Project(
        tenant_id=tenant_id,
        **project_data
    )
    await project.insert()
    return project


async def get_project(tenant_id: str, project_id: str) -> Optional[Project]:
    """Get a project by ID."""
    return await Project.find_one(
        Project.id == PydanticObjectId(project_id),
        Project.tenant_id == tenant_id
    )


async def list_projects(tenant_id: str, client_id: Optional[str] = None) -> List[Project]:
    """List projects for a tenant."""
    if client_id:
        projects = await Project.find(
            Project.tenant_id == tenant_id,
            Project.client_id == client_id
        ).sort(-Project.created_at).to_list()
    else:
        projects = await Project.find(
            Project.tenant_id == tenant_id
        ).sort(-Project.created_at).to_list()
    
    return projects


async def update_project(tenant_id: str, project_id: str, updates: Dict[str, Any]) -> Project:
    """Update a project."""
    project = await get_project(tenant_id, project_id)
    if not project:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found")
    
    # Verify client if being updated
    if "client_id" in updates:
        client = await get_client(tenant_id, updates["client_id"])
        if not client:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Client not found")
    
    for key, value in updates.items():
        if hasattr(project, key):
            setattr(project, key, value)
    
    project.updated_at = datetime.utcnow()
    await project.save()
    return project


async def delete_project(tenant_id: str, project_id: str) -> None:
    """Delete a project. Fails if project has tasks."""
    project = await get_project(tenant_id, project_id)
    if not project:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found")
    
    # Check if project has tasks
    tasks_count = await Task.find(
        Task.tenant_id == tenant_id,
        Task.project_id == project_id
    ).count()
    
    if tasks_count > 0:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete project with existing tasks"
        )
    
    await project.delete()


# ========== Task Operations ==========
async def create_task(tenant_id: str, user_id: str, task_data: Dict[str, Any]) -> Task:
    """Create a new task."""
    # Verify project exists
    project_id = task_data.get("project_id")
    if not project_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Project ID is required")
    
    project = await get_project(tenant_id, project_id)
    if not project:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found")
    
    # Verify status exists - if not provided, use the default "To Do" status
    status_id = task_data.get("status_id")
    if not status_id:
        # Get default status
        default_status = await TaskStatus.find_one(
            TaskStatus.tenant_id == tenant_id,
            TaskStatus.is_default == True,
            TaskStatus.category == TaskStatusCategory.TODO
        )
        if default_status:
            task_data["status_id"] = str(default_status.id)
        else:
            # Ensure defaults exist and retry
            await ensure_default_statuses(tenant_id)
            default_status = await TaskStatus.find_one(
                TaskStatus.tenant_id == tenant_id,
                TaskStatus.is_default == True,
                TaskStatus.category == TaskStatusCategory.TODO
            )
            if default_status:
                task_data["status_id"] = str(default_status.id)
            else:
                raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to create default status")
    
    status_obj = await TaskStatus.find_one(
        TaskStatus.id == PydanticObjectId(task_data["status_id"]),
        TaskStatus.tenant_id == tenant_id
    )
    if not status_obj:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Status not found")
    
    # Verify priority if provided
    if "priority_id" in task_data and task_data["priority_id"]:
        priority = await TaskPriority.find_one(
            TaskPriority.id == PydanticObjectId(task_data["priority_id"]),
            TaskPriority.tenant_id == tenant_id
        )
        if not priority:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Priority not found")
    
    # Verify task_list if provided
    if "task_list_id" in task_data and task_data["task_list_id"]:
        task_list = await TaskList.find_one(
            TaskList.id == PydanticObjectId(task_data["task_list_id"]),
            TaskList.tenant_id == tenant_id,
            TaskList.project_id == task_data["project_id"]
        )
        if not task_list:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task list not found")
    
    # Extract assignees if provided
    assignee_ids = task_data.pop("assignee_ids", []) or task_data.pop("user_id", [])
    
    # Validate assignees belong to tenant
    valid_assignee_ids = []
    if assignee_ids:
        for assignee_id in assignee_ids:
            user = await User.get(assignee_id)
            if user and user.tenant_id == tenant_id:
                valid_assignee_ids.append(str(assignee_id))
    
    # Create task
    task = Task(
        tenant_id=tenant_id,
        created_by=user_id,
        assignee_ids=valid_assignee_ids,
        **task_data
    )
    await task.insert()
    return task


async def get_task(tenant_id: str, task_id: str) -> Optional[Task]:
    """Get a task by ID."""
    return await Task.find_one(
        Task.id == PydanticObjectId(task_id),
        Task.tenant_id == tenant_id
    )


async def list_tasks(
    tenant_id: str,
    project_id: Optional[str] = None,
    status_id: Optional[str] = None,
    assignee_id: Optional[str] = None,
    search: Optional[str] = None,
    limit: int = 100,
    offset: int = 0
) -> List[Task]:
    """List tasks with filters."""
    conditions = [Task.tenant_id == tenant_id]
    
    if project_id:
        conditions.append(Task.project_id == project_id)
    
    if status_id:
        conditions.append(Task.status_id == status_id)
    
    if assignee_id:
        conditions.append({"assignee_ids": str(assignee_id)})
    
    if search:
        search_regex = re.compile(f".*{re.escape(search)}.*", re.IGNORECASE)
        conditions.append({
            "$or": [
                {"title": {"$regex": search_regex}},
                {"description": {"$regex": search_regex}}
            ]
        })
    
    tasks = await Task.find(*conditions).sort(-Task.created_at).skip(offset).limit(limit).to_list()
    return tasks


async def update_task(tenant_id: str, task_id: str, updates: Dict[str, Any]) -> Task:
    """Update a task."""
    task = await get_task(tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    # Handle assignees separately
    assignee_ids = updates.pop("assignee_ids", None) or updates.pop("user_id", None)
    
    # Update fields
    for key, value in updates.items():
        if hasattr(task, key):
            setattr(task, key, value)
    
    # Update assignees if provided
    if assignee_ids is not None:
        # Validate assignees belong to tenant
        valid_assignee_ids = []
        for assignee_id in assignee_ids:
            user = await User.get(assignee_id)
            if user and user.tenant_id == tenant_id:
                valid_assignee_ids.append(str(assignee_id))
        task.assignee_ids = valid_assignee_ids
    
    task.updated_at = datetime.utcnow()
    await task.save()
    return task


async def delete_task(tenant_id: str, task_id: str) -> None:
    """Delete a task."""
    task = await get_task(tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    await task.delete()


async def duplicate_task(tenant_id: str, task_id: str, user_id: str) -> Task:
    """Duplicate a task."""
    original = await get_task(tenant_id, task_id)
    if not original:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    # Create new task with same data
    new_task_data = {
        "title": f"{original.title} (Copy)",
        "description": original.description,
        "notes": original.notes,
        "project_id": original.project_id,
        "status_id": original.status_id,
        "priority_id": original.priority_id,
        "task_list_id": original.task_list_id,
        "start_date": original.start_date,
        "due_date": original.due_date,
        "completion_percentage": 0,
        "billing_type": original.billing_type,
        "client_can_discuss": original.client_can_discuss,
        "assignee_ids": original.assignee_ids or [],
    }
    
    new_task = await create_task(tenant_id, user_id, new_task_data)
    return new_task


# ========== Status Operations ==========
async def ensure_default_statuses(tenant_id: str) -> None:
    """Ensure default statuses exist for a tenant. Creates them if they don't exist."""
    default_statuses = [
        {"name": "To Do", "color": "#6b7280", "category": TaskStatusCategory.TODO, "display_order": 0, "is_default": True},
        {"name": "In Progress", "color": "#3b82f6", "category": TaskStatusCategory.IN_PROGRESS, "display_order": 1, "is_default": True},
        {"name": "Done", "color": "#10b981", "category": TaskStatusCategory.DONE, "display_order": 2, "is_default": True},
        {"name": "Cancelled", "color": "#ef4444", "category": TaskStatusCategory.CANCELLED, "display_order": 3, "is_default": True},
    ]
    
    existing_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.is_default == True
    ).to_list()
    
    existing_names = {s.name for s in existing_statuses}
    
    for status_data in default_statuses:
        if status_data["name"] not in existing_names:
            status_obj = TaskStatus(tenant_id=tenant_id, **status_data)
            await status_obj.insert()


async def list_statuses(tenant_id: str) -> List[TaskStatus]:
    """List all statuses for a tenant. Ensures default statuses exist."""
    await ensure_default_statuses(tenant_id)
    return await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id
    ).sort(+TaskStatus.display_order).to_list()


async def create_status(tenant_id: str, status_data: Dict[str, Any]) -> TaskStatus:
    """Create a new custom status. Users cannot create default statuses."""
    # Ensure is_default is False for user-created statuses
    status_data = {**status_data, "is_default": False}
    
    # Get max display_order for custom statuses
    all_statuses = await TaskStatus.find(TaskStatus.tenant_id == tenant_id).to_list()
    max_order = max((s.display_order for s in all_statuses), default=3)
    
    # Set display_order if not provided
    if "display_order" not in status_data or status_data["display_order"] is None:
        status_data["display_order"] = max_order + 1
    
    status_obj = TaskStatus(tenant_id=tenant_id, **status_data)
    await status_obj.insert()
    return status_obj


async def update_status(tenant_id: str, status_id: str, updates: Dict[str, Any]) -> TaskStatus:
    """Update a status."""
    status_obj = await TaskStatus.find_one(
        TaskStatus.id == PydanticObjectId(status_id),
        TaskStatus.tenant_id == tenant_id
    )
    
    if not status_obj:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Status not found")
    
    for key, value in updates.items():
        if hasattr(status_obj, key):
            setattr(status_obj, key, value)
    
    status_obj.updated_at = datetime.utcnow()
    await status_obj.save()
    return status_obj


async def delete_status(tenant_id: str, status_id: str) -> None:
    """Delete a status. Fails if status is used by tasks."""
    status_obj = await TaskStatus.find_one(
        TaskStatus.id == PydanticObjectId(status_id),
        TaskStatus.tenant_id == tenant_id
    )
    
    if not status_obj:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Status not found")
    
    # Check if status is used
    tasks_count = await Task.find(Task.status_id == status_id).count()
    
    if tasks_count > 0:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete status that is used by tasks"
        )
    
    await status_obj.delete()


# ========== Priority Operations ==========
async def list_priorities(tenant_id: str) -> List[TaskPriority]:
    """List all priorities for a tenant."""
    return await TaskPriority.find(
        TaskPriority.tenant_id == tenant_id
    ).sort(+TaskPriority.display_order).to_list()


async def create_priority(tenant_id: str, priority_data: Dict[str, Any]) -> TaskPriority:
    """Create a new priority."""
    priority = TaskPriority(tenant_id=tenant_id, **priority_data)
    await priority.insert()
    return priority


async def update_priority(tenant_id: str, priority_id: str, updates: Dict[str, Any]) -> TaskPriority:
    """Update a priority."""
    priority = await TaskPriority.find_one(
        TaskPriority.id == PydanticObjectId(priority_id),
        TaskPriority.tenant_id == tenant_id
    )
    
    if not priority:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Priority not found")
    
    for key, value in updates.items():
        if hasattr(priority, key):
            setattr(priority, key, value)
    
    priority.updated_at = datetime.utcnow()
    await priority.save()
    return priority


async def delete_priority(tenant_id: str, priority_id: str) -> None:
    """Delete a priority. Fails if priority is used by tasks."""
    priority = await TaskPriority.find_one(
        TaskPriority.id == PydanticObjectId(priority_id),
        TaskPriority.tenant_id == tenant_id
    )
    
    if not priority:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Priority not found")
    
    # Check if priority is used by any tasks
    tasks_count = await Task.find(
        Task.tenant_id == tenant_id,
        Task.priority_id == priority_id
    ).count()
    
    if tasks_count > 0:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete priority that is used by tasks"
        )
    
    await priority.delete()


# ========== Comment Operations ==========
async def add_comment(tenant_id: str, task_id: str, user_id: str, comment_text: str) -> TaskComment:
    """Add a comment to a task."""
    # Verify task exists
    task = await get_task(tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    comment = TaskComment(
        tenant_id=tenant_id,
        task_id=task_id,
        user_id=user_id,
        comment=comment_text
    )
    await comment.insert()
    return comment


async def list_comments(tenant_id: str, task_id: str) -> List[TaskComment]:
    """List comments for a task."""
    return await TaskComment.find(
        TaskComment.task_id == task_id,
        TaskComment.tenant_id == tenant_id
    ).sort(+TaskComment.created_at).to_list()


# ========== Favorite/Pin Operations ==========
async def toggle_favorite(tenant_id: str, task_id: str, user_id: str, is_favorite: bool) -> None:
    """Toggle favorite status for a task."""
    task = await get_task(tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    existing = await TaskFavorite.find_one(
        TaskFavorite.task_id == task_id,
        TaskFavorite.user_id == user_id,
        TaskFavorite.tenant_id == tenant_id
    )
    
    if is_favorite:
        if not existing:
            favorite = TaskFavorite(
                tenant_id=tenant_id,
                task_id=task_id,
                user_id=user_id
            )
            await favorite.insert()
    else:
        if existing:
            await existing.delete()


async def toggle_pin(tenant_id: str, task_id: str, user_id: str, is_pinned: bool) -> None:
    """Toggle pin status for a task."""
    task = await get_task(tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    existing = await TaskPin.find_one(
        TaskPin.task_id == task_id,
        TaskPin.user_id == user_id,
        TaskPin.tenant_id == tenant_id
    )
    
    if is_pinned:
        if not existing:
            pin = TaskPin(
                tenant_id=tenant_id,
                task_id=task_id,
                user_id=user_id
            )
            await pin.insert()
    else:
        if existing:
            await existing.delete()


async def is_task_favorite(tenant_id: str, task_id: str, user_id: str) -> bool:
    """Check if task is favorited by user."""
    favorite = await TaskFavorite.find_one(
        TaskFavorite.task_id == task_id,
        TaskFavorite.user_id == user_id,
        TaskFavorite.tenant_id == tenant_id
    )
    return favorite is not None


async def is_task_pinned(tenant_id: str, task_id: str, user_id: str) -> bool:
    """Check if task is pinned by user."""
    pin = await TaskPin.find_one(
        TaskPin.task_id == task_id,
        TaskPin.user_id == user_id,
        TaskPin.tenant_id == tenant_id
    )
    return pin is not None
