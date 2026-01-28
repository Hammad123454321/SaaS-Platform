"""Task Management Access Control Service - Stage 3

Role-based access control for task management operations.
"""
from typing import Optional

from app.models.user import User
from app.models.role import Role
from app.models.permissions import UserPermission
from app.models.tasks import Task, Project
from app.models import UserRole
from app.services.owner_service import is_user_owner


async def _get_user_role_names(user: User) -> list[str]:
    """Helper to fetch role names for a user."""
    user_roles = await UserRole.find(UserRole.user_id == str(user.id)).to_list()
    role_names = []
    for ur in user_roles:
        role = await Role.get(ur.role_id)
        if role:
            role_names.append(role.name.lower())
    return role_names


async def can_user_access_task_management(user: User) -> bool:
    """Check if user can access task management module at all."""
    # Accountant has read-only access, others have full access
    role_names = await _get_user_role_names(user)
    
    # Accountant, Staff, Manager, Owner, company_admin all can access
    # Only users with no roles cannot access
    return len(role_names) > 0


async def can_user_create_task(user: User) -> bool:
    """Check if user can create tasks."""
    role_names = await _get_user_role_names(user)
    
    # Owner, Manager, Staff can create tasks
    # Accountant cannot create (read-only)
    if "accountant" in role_names:
        return False
    
    return "owner" in role_names or "manager" in role_names or "staff" in role_names or "company_admin" in role_names


async def can_user_update_task(user: User, task: Task) -> bool:
    """Check if user can update a task."""
    role_names = await _get_user_role_names(user)
    
    # Owner and Manager have full access
    is_owner = await is_user_owner(str(user.id), user.tenant_id)
    if is_owner or "manager" in role_names or "company_admin" in role_names:
        return True
    
    # Staff can only change status
    if "staff" in role_names:
        return True  # We'll check specific fields in the update endpoint
    
    # Accountant is read-only
    return False


async def can_user_change_task_status(user: User) -> bool:
    """Check if user can change task status."""
    role_names = await _get_user_role_names(user)
    
    # Owner, Manager, Staff can change status
    # Accountant cannot
    if "accountant" in role_names:
        return False
    
    return "owner" in role_names or "manager" in role_names or "staff" in role_names or "company_admin" in role_names


async def can_user_delete_task(user: User, task: Task) -> bool:
    """Check if user can delete a task."""
    # Required tasks cannot be deleted by anyone
    if task.is_required:
        return False
    
    role_names = await _get_user_role_names(user)
    
    # Only Owner and Manager can delete
    is_owner = await is_user_owner(str(user.id), user.tenant_id)
    if is_owner or "manager" in role_names or "company_admin" in role_names:
        return True
    
    return False


async def can_user_create_project(user: User) -> bool:
    """Check if user can create projects."""
    role_names = await _get_user_role_names(user)
    
    # Owner and Manager can always create projects
    is_owner = await is_user_owner(str(user.id), user.tenant_id)
    if is_owner or "manager" in role_names or "company_admin" in role_names:
        return True
    
    # Staff can create projects if granted permission
    if "staff" in role_names:
        permission = await UserPermission.find_one(
            UserPermission.user_id == str(user.id),
            UserPermission.permission_code == "create_projects"
        )
        return permission is not None
    
    return False


async def can_user_update_project(user: User, project: Project) -> bool:
    """Check if user can update a project."""
    role_names = await _get_user_role_names(user)
    
    # Owner and Manager have full access
    is_owner = await is_user_owner(str(user.id), user.tenant_id)
    if is_owner or "manager" in role_names or "company_admin" in role_names:
        return True
    
    # Staff can update if they created it or have permission
    if "staff" in role_names:
        # Check if project has created_by field and matches user
        if hasattr(project, 'created_by') and project.created_by == str(user.id):
            return True
        permission = await UserPermission.find_one(
            UserPermission.user_id == str(user.id),
            UserPermission.permission_code == "create_projects"
        )
        return permission is not None
    
    return False


async def can_user_delete_project(user: User, project: Project) -> bool:
    """Check if user can delete a project."""
    role_names = await _get_user_role_names(user)
    
    # Only Owner and Manager can delete
    is_owner = await is_user_owner(str(user.id), user.tenant_id)
    if is_owner or "manager" in role_names or "company_admin" in role_names:
        return True
    
    return False


async def grant_project_creation_permission(
    user_id: str,
    tenant_id: str,
    granted_by_user_id: str
) -> UserPermission:
    """Grant a Staff user permission to create projects."""
    # Check if permission already exists
    existing = await UserPermission.find_one(
        UserPermission.user_id == user_id,
        UserPermission.permission_code == "create_projects"
    )
    
    if existing:
        return existing
    
    permission = UserPermission(
        user_id=user_id,
        tenant_id=tenant_id,
        permission_code="create_projects",
        granted_by_user_id=granted_by_user_id
    )
    await permission.insert()
    return permission


async def revoke_project_creation_permission(user_id: str) -> bool:
    """Revoke a Staff user's permission to create projects."""
    permission = await UserPermission.find_one(
        UserPermission.user_id == user_id,
        UserPermission.permission_code == "create_projects"
    )
    
    if permission:
        await permission.delete()
        return True
    
    return False
