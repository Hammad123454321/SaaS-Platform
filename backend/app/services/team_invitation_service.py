"""Service for team invitations."""
import secrets
import string
from datetime import datetime, timedelta
from typing import Optional, List
from sqlmodel import Session, select

from app.models.onboarding import TeamInvitation, StaffOnboardingTask
from app.models.user import User
from app.models.role import Role, UserRole
from app.services.email_service import email_service
from app.core.security import hash_password


def generate_secure_password(length: int = 16) -> str:
    """Generate a secure random password."""
    # Use a mix of uppercase, lowercase, digits, and special characters
    characters = string.ascii_letters + string.digits + "!@#$%^&*"
    password = ''.join(secrets.choice(characters) for _ in range(length))
    # Ensure password has at least one of each type
    if not any(c.isupper() for c in password):
        password = password[:-1] + secrets.choice(string.ascii_uppercase)
    if not any(c.islower() for c in password):
        password = password[:-1] + secrets.choice(string.ascii_lowercase)
    if not any(c.isdigit() for c in password):
        password = password[:-1] + secrets.choice(string.digits)
    if not any(c in "!@#$%^&*" for c in password):
        password = password[:-1] + secrets.choice("!@#$%^&*")
    return password


def create_team_member(
    session: Session,
    tenant_id: int,
    invited_by_user_id: int,
    email: str,
    role_id: Optional[int] = None
) -> tuple[User, str]:
    """
    Create a team member user account with auto-generated password.
    
    Returns (User, plain_password) tuple.
    """
    # Check if user already exists
    existing_user = session.exec(
        select(User).where(
            User.email == email.lower(),
            User.tenant_id == tenant_id
        )
    ).first()
    
    if existing_user:
        raise ValueError(f"User with email {email} already exists in this tenant")
    
    # Verify role belongs to tenant if provided
    if role_id:
        role = session.get(Role, role_id)
        if not role or role.tenant_id != tenant_id:
            raise ValueError("Invalid role")
    
    # Generate secure password
    plain_password = generate_secure_password()
    hashed_password = hash_password(plain_password)
    
    # Create user account
    user = User(
        tenant_id=tenant_id,
        email=email.lower(),
        hashed_password=hashed_password,
        is_active=True,
        is_super_admin=False,
        email_verified=True,  # Auto-verify for team members
        password_change_required=True,  # Require password change on first login
    )
    session.add(user)
    session.flush()
    
    # Assign role if provided
    if role_id:
        session.add(UserRole(user_id=user.id, role_id=role_id))
    
    # Create onboarding tasks for staff
    create_staff_onboarding_tasks_for_user(session, user)
    
    session.commit()
    session.refresh(user)
    
    return user, plain_password


def create_staff_onboarding_tasks_for_user(session: Session, user: User) -> List[StaffOnboardingTask]:
    """Create simple onboarding tasks for team member."""
    tasks_data = [
        {
            "title": "Complete account setup",
            "description": "Set up your account profile and preferences"
        },
        {
            "title": "Review company policies",
            "description": "Read and acknowledge company policies and procedures"
        },
        {
            "title": "Complete training",
            "description": "Complete required training modules"
        }
    ]
    
    tasks = []
    for task_data in tasks_data:
        task = StaffOnboardingTask(
            tenant_id=user.tenant_id,
            invitation_id=None,  # No invitation anymore
            assigned_to_email=user.email,
            task_title=task_data["title"],
            task_description=task_data["description"],
            due_date=datetime.utcnow() + timedelta(days=14)  # 2 weeks from now
        )
        session.add(task)
        tasks.append(task)
    
    session.flush()
    return tasks


def send_team_member_credentials_email(
    session: Session,
    user: User,
    plain_password: str,
    invited_by_user_id: int
) -> bool:
    """Send credentials email to the new team member."""
    inviter = session.get(User, invited_by_user_id)
    
    # Get tenant name
    from app.models.tenant import Tenant
    tenant_obj = session.get(Tenant, user.tenant_id)
    tenant_name = tenant_obj.name if tenant_obj else "the organization"
    
    # Get role name
    role_name = None
    if user.roles:
        role_name = user.roles[0].name if user.roles else None
    
    inviter_name = inviter.email.split("@")[0] if inviter else "Admin"
    
    return email_service.send_team_member_credentials_email(
        to_email=user.email,
        password=plain_password,
        inviter_name=inviter_name,
        tenant_name=tenant_name,
        role_name=role_name,
        login_url=f"{email_service.get_frontend_base_url()}/login"
    )

