"""Task Management Access Control Service - Stage 3

Role-based access control for task management operations.
"""
from typing import Optional
from sqlmodel import Session, select

from app.models.user import User
from app.models.role import Role
from app.models.permissions import UserPermission
from app.models.tasks import Task, Project
from app.services.owner_service import is_user_owner


def can_user_access_task_management(user: User, session: Session) -> bool:
    """Check if user can access task management module at all."""
    # Accountant has read-only access, others have full access
    role_names = [r.name.lower() for r in user.roles]
    
    # Accountant, Staff, Manager, Owner, company_admin all can access
    # Only users with no roles cannot access
    return len(role_names) > 0


def can_user_create_task(user: User, session: Session) -> bool:
    """Check if user can create tasks."""
    role_names = [r.name.lower() for r in user.roles]
    
    # Owner, Manager, Staff can create tasks
    # Accountant cannot create (read-only)
    if "accountant" in role_names:
        return False
    
    return "owner" in role_names or "manager" in role_names or "staff" in role_names or "company_admin" in role_names


def can_user_update_task(user: User, task: Task, session: Session) -> bool:
    """Check if user can update a task."""
    role_names = [r.name.lower() for r in user.roles]
    
    # Owner and Manager have full access
    if is_user_owner(session, user.id, user.tenant_id) or "manager" in role_names or "company_admin" in role_names:
        return True
    
    # Staff can only change status
    if "staff" in role_names:
        return True  # We'll check specific fields in the update endpoint
    
    # Accountant is read-only
    return False


def can_user_change_task_status(user: User, session: Session) -> bool:
    """Check if user can change task status."""
    role_names = [r.name.lower() for r in user.roles]
    
    # Owner, Manager, Staff can change status
    # Accountant cannot
    if "accountant" in role_names:
        return False
    
    return "owner" in role_names or "manager" in role_names or "staff" in role_names or "company_admin" in role_names


def can_user_delete_task(user: User, task: Task, session: Session) -> bool:
    """Check if user can delete a task."""
    # Required tasks cannot be deleted by anyone
    if task.is_required:
        return False
    
    role_names = [r.name.lower() for r in user.roles]
    
    # Only Owner and Manager can delete
    if is_user_owner(session, user.id, user.tenant_id) or "manager" in role_names or "company_admin" in role_names:
        return True
    
    return False


def can_user_create_project(user: User, session: Session) -> bool:
    """Check if user can create projects."""
    role_names = [r.name.lower() for r in user.roles]
    
    # Owner and Manager can always create projects
    if is_user_owner(session, user.id, user.tenant_id) or "manager" in role_names or "company_admin" in role_names:
        return True
    
    # Staff can create projects if granted permission
    if "staff" in role_names:
        permission = session.exec(
            select(UserPermission).where(
                UserPermission.user_id == user.id,
                UserPermission.permission_code == "create_projects"
            )
        ).first()
        return permission is not None
    
    return False


def can_user_update_project(user: User, project: Project, session: Session) -> bool:
    """Check if user can update a project."""
    role_names = [r.name.lower() for r in user.roles]
    
    # Owner and Manager have full access
    if is_user_owner(session, user.id, user.tenant_id) or "manager" in role_names or "company_admin" in role_names:
        return True
    
    # Staff can update if they created it or have permission
    if "staff" in role_names:
        if project.created_by == user.id:
            return True
        permission = session.exec(
            select(UserPermission).where(
                UserPermission.user_id == user.id,
                UserPermission.permission_code == "create_projects"
            )
        ).first()
        return permission is not None
    
    return False


def can_user_delete_project(user: User, project: Project, session: Session) -> bool:
    """Check if user can delete a project."""
    role_names = [r.name.lower() for r in user.roles]
    
    # Only Owner and Manager can delete
    if is_user_owner(session, user.id, user.tenant_id) or "manager" in role_names or "company_admin" in role_names:
        return True
    
    return False


def grant_project_creation_permission(
    session: Session,
    user_id: int,
    tenant_id: int,
    granted_by_user_id: int
) -> UserPermission:
    """Grant a Staff user permission to create projects."""
    # Check if permission already exists
    existing = session.exec(
        select(UserPermission).where(
            UserPermission.user_id == user_id,
            UserPermission.permission_code == "create_projects"
        )
    ).first()
    
    if existing:
        return existing
    
    permission = UserPermission(
        user_id=user_id,
        tenant_id=tenant_id,
        permission_code="create_projects",
        granted_by_user_id=granted_by_user_id
    )
    session.add(permission)
    session.commit()
    session.refresh(permission)
    return permission


def revoke_project_creation_permission(session: Session, user_id: int) -> bool:
    """Revoke a Staff user's permission to create projects."""
    permission = session.exec(
        select(UserPermission).where(
            UserPermission.user_id == user_id,
            UserPermission.permission_code == "create_projects"
        )
    ).first()
    
    if permission:
        session.delete(permission)
        session.commit()
        return True
    
    return False

