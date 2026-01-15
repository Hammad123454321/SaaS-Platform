"""
Task Management Service

Business logic for task management operations.
All operations are tenant-isolated.
"""
import logging
from datetime import datetime, date
from typing import Optional, List, Dict, Any
from decimal import Decimal

from sqlmodel import Session, select, and_, or_, func
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
from app.models.tasks import (
    task_assignments_table,
    task_tags_table,
    TaskStatusCategory,
)
from sqlalchemy.orm import selectinload

logger = logging.getLogger(__name__)


# ========== Client Operations ==========
def create_client(session: Session, tenant_id: int, client_data: Dict[str, Any]) -> Client:
    """Create a new client."""
    # Check if email already exists for this tenant
    existing = session.exec(
        select(Client).where(
            and_(
                Client.tenant_id == tenant_id,
                Client.email == client_data["email"]
            )
        )
    ).first()
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Client with this email already exists"
        )
    
    client = Client(
        tenant_id=tenant_id,
        **client_data
    )
    session.add(client)
    session.commit()
    session.refresh(client)
    return client


def get_client(session: Session, tenant_id: int, client_id: int) -> Optional[Client]:
    """Get a client by ID."""
    return session.exec(
        select(Client).where(
            and_(
                Client.id == client_id,
                Client.tenant_id == tenant_id
            )
        )
    ).first()


def list_clients(session: Session, tenant_id: int, search: Optional[str] = None) -> List[Client]:
    """List all clients for a tenant."""
    query = select(Client).where(Client.tenant_id == tenant_id)
    
    if search:
        query = query.where(
            or_(
                Client.first_name.ilike(f"%{search}%"),
                Client.last_name.ilike(f"%{search}%"),
                Client.email.ilike(f"%{search}%"),
                Client.company.ilike(f"%{search}%")
            )
        )
    
    return list(session.exec(query.order_by(Client.created_at.desc())).all())


def update_client(session: Session, tenant_id: int, client_id: int, updates: Dict[str, Any]) -> Client:
    """Update a client."""
    client = get_client(session, tenant_id, client_id)
    if not client:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Client not found")
    
    # Check email uniqueness if email is being updated
    if "email" in updates and updates["email"] != client.email:
        existing = session.exec(
            select(Client).where(
                and_(
                    Client.tenant_id == tenant_id,
                    Client.email == updates["email"],
                    Client.id != client_id
                )
            )
        ).first()
        if existing:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Client with this email already exists"
            )
    
    for key, value in updates.items():
        setattr(client, key, value)
    
    client.updated_at = datetime.utcnow()
    session.add(client)
    session.commit()
    session.refresh(client)
    return client


def delete_client(session: Session, tenant_id: int, client_id: int) -> None:
    """Delete a client. Fails if client has projects."""
    client = get_client(session, tenant_id, client_id)
    if not client:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Client not found")
    
    # Check if client has projects
    projects_count = session.exec(
        select(func.count(Project.id)).where(
            and_(
                Project.tenant_id == tenant_id,
                Project.client_id == client_id
            )
        )
    ).first()
    
    if projects_count and projects_count > 0:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete client with existing projects"
        )
    
    session.delete(client)
    session.commit()


# ========== Project Operations ==========
def create_project(session: Session, tenant_id: int, user_id: int, project_data: Dict[str, Any]) -> Project:
    """Create a new project."""
    # Verify client exists and belongs to tenant
    client = get_client(session, tenant_id, project_data["client_id"])
    if not client:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Client not found")
    
    project = Project(
        tenant_id=tenant_id,
        **project_data
    )
    session.add(project)
    session.commit()
    session.refresh(project)
    return project


def get_project(session: Session, tenant_id: int, project_id: int) -> Optional[Project]:
    """Get a project by ID."""
    return session.exec(
        select(Project).where(
            and_(
                Project.id == project_id,
                Project.tenant_id == tenant_id
            )
        )
    ).first()


def list_projects(session: Session, tenant_id: int, client_id: Optional[int] = None) -> List[Project]:
    """List projects for a tenant."""
    query = select(Project).where(Project.tenant_id == tenant_id)
    
    if client_id:
        query = query.where(Project.client_id == client_id)
    
    return list(
        session.exec(
            query.options(selectinload(Project.client))
            .order_by(Project.created_at.desc())
        ).all()
    )


def update_project(session: Session, tenant_id: int, project_id: int, updates: Dict[str, Any]) -> Project:
    """Update a project."""
    project = get_project(session, tenant_id, project_id)
    if not project:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found")
    
    # Verify client if being updated
    if "client_id" in updates:
        client = get_client(session, tenant_id, updates["client_id"])
        if not client:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Client not found")
    
    for key, value in updates.items():
        setattr(project, key, value)
    
    project.updated_at = datetime.utcnow()
    session.add(project)
    session.commit()
    session.refresh(project)
    return project


def delete_project(session: Session, tenant_id: int, project_id: int) -> None:
    """Delete a project. Fails if project has tasks."""
    project = get_project(session, tenant_id, project_id)
    if not project:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found")
    
    # Check if project has tasks
    tasks_count = session.exec(
        select(func.count(Task.id)).where(
            and_(
                Task.tenant_id == tenant_id,
                Task.project_id == project_id
            )
        )
    ).first()
    
    if tasks_count and tasks_count > 0:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete project with existing tasks"
        )
    
    session.delete(project)
    session.commit()


# ========== Task Operations ==========
def create_task(session: Session, tenant_id: int, user_id: int, task_data: Dict[str, Any]) -> Task:
    """Create a new task."""
    # Verify project exists
    project = get_project(session, tenant_id, task_data["project_id"])
    if not project:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found")
    
    # Verify status exists
    status_obj = session.exec(
        select(TaskStatus).where(
            and_(
                TaskStatus.id == task_data["status_id"],
                TaskStatus.tenant_id == tenant_id
            )
        )
    ).first()
    if not status_obj:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Status not found")
    
    # Verify priority if provided
    if "priority_id" in task_data and task_data["priority_id"]:
        priority = session.exec(
            select(TaskPriority).where(
                and_(
                    TaskPriority.id == task_data["priority_id"],
                    TaskPriority.tenant_id == tenant_id
                )
            )
        ).first()
        if not priority:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Priority not found")
    
    # Verify task_list if provided
    if "task_list_id" in task_data and task_data["task_list_id"]:
        task_list = session.exec(
            select(TaskList).where(
                and_(
                    TaskList.id == task_data["task_list_id"],
                    TaskList.tenant_id == tenant_id,
                    TaskList.project_id == task_data["project_id"]
                )
            )
        ).first()
        if not task_list:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task list not found")
    
    # Extract assignees if provided
    assignee_ids = task_data.pop("assignee_ids", []) or task_data.pop("user_id", [])
    
    # Create task
    task = Task(
        tenant_id=tenant_id,
        created_by=user_id,
        **task_data
    )
    session.add(task)
    session.flush()  # Get task ID
    
    # Assign users
    if assignee_ids:
        for assignee_id in assignee_ids:
            # Verify user belongs to tenant
            user = session.get(User, assignee_id)
            if user and user.tenant_id == tenant_id:
                session.execute(
                    task_assignments_table.insert().values(
                        task_id=task.id,
                        user_id=assignee_id,
                        is_primary=False
                    )
                )
    
    session.commit()
    session.refresh(task)
    return task


def get_task(session: Session, tenant_id: int, task_id: int) -> Optional[Task]:
    """Get a task by ID with relationships."""
    return session.exec(
        select(Task)
        .where(
            and_(
                Task.id == task_id,
                Task.tenant_id == tenant_id
            )
        )
        .options(
            selectinload(Task.project),
            selectinload(Task.status),
            selectinload(Task.priority),
            selectinload(Task.assignees),
            selectinload(Task.comments),
            selectinload(Task.attachments)
        )
    ).first()


def list_tasks(
    session: Session,
    tenant_id: int,
    project_id: Optional[int] = None,
    status_id: Optional[int] = None,
    assignee_id: Optional[int] = None,
    search: Optional[str] = None,
    limit: int = 100,
    offset: int = 0
) -> List[Task]:
    """List tasks with filters."""
    query = select(Task).where(Task.tenant_id == tenant_id)
    
    if project_id:
        query = query.where(Task.project_id == project_id)
    
    if status_id:
        query = query.where(Task.status_id == status_id)
    
    if assignee_id:
        query = query.join(task_assignments_table).where(
            task_assignments_table.c.user_id == assignee_id
        )
    
    if search:
        query = query.where(
            or_(
                Task.title.ilike(f"%{search}%"),
                Task.description.ilike(f"%{search}%")
            )
        )
    
    return list(
        session.exec(
            query
            .options(
                selectinload(Task.project),
                selectinload(Task.status),
                selectinload(Task.priority),
                selectinload(Task.assignees)
            )
            .order_by(Task.created_at.desc())
            .limit(limit)
            .offset(offset)
        ).all()
    )


def update_task(session: Session, tenant_id: int, task_id: int, updates: Dict[str, Any]) -> Task:
    """Update a task."""
    task = get_task(session, tenant_id, task_id)
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
        # Remove existing assignments
        session.execute(
            task_assignments_table.delete().where(
                task_assignments_table.c.task_id == task_id
            )
        )
        
        # Add new assignments
        for assignee_id in assignee_ids:
            user = session.get(User, assignee_id)
            if user and user.tenant_id == tenant_id:
                session.execute(
                    task_assignments_table.insert().values(
                        task_id=task_id,
                        user_id=assignee_id,
                        is_primary=False
                    )
                )
    
    task.updated_at = datetime.utcnow()
    session.add(task)
    session.commit()
    session.refresh(task)
    return task


def delete_task(session: Session, tenant_id: int, task_id: int) -> None:
    """Delete a task."""
    task = get_task(session, tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    # Delete related records (cascade should handle most, but be explicit)
    session.execute(
        task_assignments_table.delete().where(task_assignments_table.c.task_id == task_id)
    )
    
    session.delete(task)
    session.commit()


def duplicate_task(session: Session, tenant_id: int, task_id: int, user_id: int) -> Task:
    """Duplicate a task."""
    original = get_task(session, tenant_id, task_id)
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
    }
    
    new_task = create_task(session, tenant_id, user_id, new_task_data)
    
    # Copy assignees
    if original.assignees:
        assignee_ids = [user.id for user in original.assignees]
        update_task(session, tenant_id, new_task.id, {"assignee_ids": assignee_ids})
    
    return new_task


# ========== Status Operations ==========
def ensure_default_statuses(session: Session, tenant_id: int) -> None:
    """Ensure default statuses exist for a tenant. Creates them if they don't exist."""
    default_statuses = [
        {"name": "To Do", "color": "#6b7280", "category": TaskStatusCategory.TODO, "display_order": 0, "is_default": True},
        {"name": "In Progress", "color": "#3b82f6", "category": TaskStatusCategory.IN_PROGRESS, "display_order": 1, "is_default": True},
        {"name": "Done", "color": "#10b981", "category": TaskStatusCategory.DONE, "display_order": 2, "is_default": True},
        {"name": "Cancelled", "color": "#ef4444", "category": TaskStatusCategory.CANCELLED, "display_order": 3, "is_default": True},
    ]
    
    existing_statuses = session.exec(
        select(TaskStatus).where(
            and_(
                TaskStatus.tenant_id == tenant_id,
                TaskStatus.is_default == True
            )
        )
    ).all()
    
    existing_names = {s.name for s in existing_statuses}
    
    for status_data in default_statuses:
        if status_data["name"] not in existing_names:
            status_obj = TaskStatus(tenant_id=tenant_id, **status_data)
            session.add(status_obj)
    
    session.commit()


def list_statuses(session: Session, tenant_id: int) -> List[TaskStatus]:
    """List all statuses for a tenant. Ensures default statuses exist."""
    ensure_default_statuses(session, tenant_id)
    return list(
        session.exec(
            select(TaskStatus)
            .where(TaskStatus.tenant_id == tenant_id)
            .order_by(TaskStatus.display_order, TaskStatus.id)
        ).all()
    )


def create_status(session: Session, tenant_id: int, status_data: Dict[str, Any]) -> TaskStatus:
    """Create a new custom status. Users cannot create default statuses."""
    # Ensure is_default is False for user-created statuses
    status_data = {**status_data, "is_default": False}
    
    # Get max display_order for custom statuses
    max_order = session.exec(
        select(func.max(TaskStatus.display_order))
        .where(TaskStatus.tenant_id == tenant_id)
    ).first() or 3  # Default statuses go up to 3
    
    # Set display_order if not provided
    if "display_order" not in status_data or status_data["display_order"] is None:
        status_data["display_order"] = max_order + 1
    
    status_obj = TaskStatus(tenant_id=tenant_id, **status_data)
    session.add(status_obj)
    session.commit()
    session.refresh(status_obj)
    return status_obj


def update_status(session: Session, tenant_id: int, status_id: int, updates: Dict[str, Any]) -> TaskStatus:
    """Update a status."""
    status_obj = session.exec(
        select(TaskStatus).where(
            and_(
                TaskStatus.id == status_id,
                TaskStatus.tenant_id == tenant_id
            )
        )
    ).first()
    
    if not status_obj:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Status not found")
    
    for key, value in updates.items():
        setattr(status_obj, key, value)
    
    status_obj.updated_at = datetime.utcnow()
    session.add(status_obj)
    session.commit()
    session.refresh(status_obj)
    return status_obj


def delete_status(session: Session, tenant_id: int, status_id: int) -> None:
    """Delete a status. Fails if status is used by tasks."""
    status_obj = session.exec(
        select(TaskStatus).where(
            and_(
                TaskStatus.id == status_id,
                TaskStatus.tenant_id == tenant_id
            )
        )
    ).first()
    
    if not status_obj:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Status not found")
    
    # Check if status is used
    tasks_count = session.exec(
        select(func.count(Task.id)).where(Task.status_id == status_id)
    ).first()
    
    if tasks_count and tasks_count > 0:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete status that is used by tasks"
        )
    
    session.delete(status_obj)
    session.commit()


# ========== Priority Operations ==========
def list_priorities(session: Session, tenant_id: int) -> List[TaskPriority]:
    """List all priorities for a tenant."""
    return list(
        session.exec(
            select(TaskPriority)
            .where(TaskPriority.tenant_id == tenant_id)
            .order_by(TaskPriority.display_order, TaskPriority.id)
        ).all()
    )


def create_priority(session: Session, tenant_id: int, priority_data: Dict[str, Any]) -> TaskPriority:
    """Create a new priority."""
    priority = TaskPriority(tenant_id=tenant_id, **priority_data)
    session.add(priority)
    session.commit()
    session.refresh(priority)
    return priority


def update_priority(session: Session, tenant_id: int, priority_id: int, updates: Dict[str, Any]) -> TaskPriority:
    """Update a priority."""
    priority = session.exec(
        select(TaskPriority).where(
            and_(
                TaskPriority.id == priority_id,
                TaskPriority.tenant_id == tenant_id
            )
        )
    ).first()
    
    if not priority:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Priority not found")
    
    for key, value in updates.items():
        setattr(priority, key, value)
    
    priority.updated_at = datetime.utcnow()
    session.add(priority)
    session.commit()
    session.refresh(priority)
    return priority


def delete_priority(session: Session, tenant_id: int, priority_id: int) -> None:
    """Delete a priority."""
    priority = session.exec(
        select(TaskPriority).where(
            and_(
                TaskPriority.id == priority_id,
                TaskPriority.tenant_id == tenant_id
            )
        )
    ).first()
    
    if not priority:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Priority not found")
    
    session.delete(priority)
    session.commit()


# ========== Comment Operations ==========
def add_comment(session: Session, tenant_id: int, task_id: int, user_id: int, comment_text: str) -> TaskComment:
    """Add a comment to a task."""
    # Verify task exists
    task = get_task(session, tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    comment = TaskComment(
        tenant_id=tenant_id,
        task_id=task_id,
        user_id=user_id,
        comment=comment_text
    )
    session.add(comment)
    session.commit()
    session.refresh(comment)
    return comment


def list_comments(session: Session, tenant_id: int, task_id: int) -> List[TaskComment]:
    """List comments for a task."""
    return list(
        session.exec(
            select(TaskComment)
            .where(
                and_(
                    TaskComment.task_id == task_id,
                    TaskComment.tenant_id == tenant_id
                )
            )
            .order_by(TaskComment.created_at.asc())
            .options(selectinload(TaskComment.user))
        ).all()
    )


# ========== Favorite/Pin Operations ==========
def toggle_favorite(session: Session, tenant_id: int, task_id: int, user_id: int, is_favorite: bool) -> None:
    """Toggle favorite status for a task."""
    task = get_task(session, tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    existing = session.exec(
        select(TaskFavorite).where(
            and_(
                TaskFavorite.task_id == task_id,
                TaskFavorite.user_id == user_id,
                TaskFavorite.tenant_id == tenant_id
            )
        )
    ).first()
    
    if is_favorite:
        if not existing:
            favorite = TaskFavorite(
                tenant_id=tenant_id,
                task_id=task_id,
                user_id=user_id
            )
            session.add(favorite)
            session.commit()
    else:
        if existing:
            session.delete(existing)
            session.commit()


def toggle_pin(session: Session, tenant_id: int, task_id: int, user_id: int, is_pinned: bool) -> None:
    """Toggle pin status for a task."""
    task = get_task(session, tenant_id, task_id)
    if not task:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Task not found")
    
    existing = session.exec(
        select(TaskPin).where(
            and_(
                TaskPin.task_id == task_id,
                TaskPin.user_id == user_id,
                TaskPin.tenant_id == tenant_id
            )
        )
    ).first()
    
    if is_pinned:
        if not existing:
            pin = TaskPin(
                tenant_id=tenant_id,
                task_id=task_id,
                user_id=user_id
            )
            session.add(pin)
            session.commit()
    else:
        if existing:
            session.delete(existing)
            session.commit()


def is_task_favorite(session: Session, tenant_id: int, task_id: int, user_id: int) -> bool:
    """Check if task is favorited by user."""
    favorite = session.exec(
        select(TaskFavorite).where(
            and_(
                TaskFavorite.task_id == task_id,
                TaskFavorite.user_id == user_id,
                TaskFavorite.tenant_id == tenant_id
            )
        )
    ).first()
    return favorite is not None


def is_task_pinned(session: Session, tenant_id: int, task_id: int, user_id: int) -> bool:
    """Check if task is pinned by user."""
    pin = session.exec(
        select(TaskPin).where(
            and_(
                TaskPin.task_id == task_id,
                TaskPin.user_id == user_id,
                TaskPin.tenant_id == tenant_id
            )
        )
    ).first()
    return pin is not None

