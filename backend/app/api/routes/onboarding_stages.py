"""API routes for onboarding stages 1 and 2."""
from datetime import datetime
from fastapi import APIRouter, Depends, HTTPException, status, Request
from fastapi import Request as FastAPIRequest
from sqlmodel import Session, select
from typing import List

from app.api.deps import get_current_user
from app.models import User, Tenant
from app.models.role import Role
from app.models.onboarding import (
    BusinessProfile,
    TenantComplianceRule,
    OwnerConfirmation,
    TeamInvitation,
    StaffOnboardingTask,
)
from app.schemas.onboarding_stages import (
    BusinessProfileCreate,
    BusinessProfileResponse,
    OwnerConfirmationRequest,
    OwnerConfirmationResponse,
    RoleTemplateResponse,
    RoleCreate,
    RoleResponse,
    TeamInvitationCreate,
    TeamInvitationResponse,
    AcceptInvitationRequest,
)
from app.schemas.compliance_stages import PolicyAcknowledgementRequest
from app.services.compliance_service import (
    get_required_hr_policies,
    acknowledge_hr_policies,
    has_user_acknowledged_all_required_policies,
)
from app.services.jurisdiction_service import (
    activate_compliance_rules_for_business_profile,
    get_activated_rules_for_tenant,
)
from app.services.owner_service import confirm_owner, get_owner_for_tenant, is_user_owner
from app.services.role_template_service import seed_role_templates, get_role_templates
from app.services.team_invitation_service import (
    create_team_member,
    send_team_member_credentials_email,
)
from app.services.verification_service import create_verification_token, send_verification_email
from app.core.security import hash_password, create_access_token, create_refresh_token
from app.models.onboarding import (
    PolicyAcceptance,
    PolicyType,
    CommunicationPreferences,
)
from app.models.role import UserRole
from app.seed import ensure_roles_for_tenant

router = APIRouter(prefix="/onboarding", tags=["onboarding-stages"])


# ========== Stage 1: Business Profile ==========

@router.post("/business-profile", response_model=BusinessProfileResponse, status_code=status.HTTP_201_CREATED)
def create_business_profile(
    payload: BusinessProfileCreate,
    current_user: User = Depends(get_current_user),
) -> BusinessProfileResponse:
    """Stage 1: Create business profile with jurisdiction mapping."""
    # Check if profile already exists
    existing = session.exec(
        select(BusinessProfile).where(BusinessProfile.tenant_id == current_user.tenant_id)
    ).first()
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Business profile already exists for this tenant."
        )
    
    # Create business profile
    business_profile = BusinessProfile(
        tenant_id=current_user.tenant_id,
        legal_business_name=payload.legal_business_name,
        operating_name=payload.operating_name,
        province=payload.province,
        country=payload.country,
        timezone=payload.timezone,
        primary_location=payload.primary_location,
        business_email=payload.business_email,
        business_phone=payload.business_phone,
        preferred_notification_channels=payload.preferred_notification_channels,
        is_confirmed=False,  # "Unconfirmed" state
    )
    session.add(business_profile)
    session.commit()
    session.refresh(business_profile)
    
    # Activate compliance rules based on jurisdiction
    activated_rules = activate_compliance_rules_for_business_profile(session, business_profile)
    
    # Get rule codes for response
    rule_codes = [rule.rule_code.value for rule in activated_rules]
    
    # Construct response from business_profile model
    return BusinessProfileResponse(
        id=business_profile.id,  # type: ignore[arg-type]
        tenant_id=business_profile.tenant_id,
        legal_business_name=business_profile.legal_business_name,
        operating_name=business_profile.operating_name,
        province=business_profile.province.value,
        country=business_profile.country,
        timezone=business_profile.timezone,
        primary_location=business_profile.primary_location,
        business_email=business_profile.business_email,
        business_phone=business_profile.business_phone,
        preferred_notification_channels=business_profile.preferred_notification_channels,
        is_confirmed=business_profile.is_confirmed,
        created_at=business_profile.created_at,
        updated_at=business_profile.updated_at,
        activated_compliance_rules=rule_codes
    )


@router.get("/business-profile", response_model=BusinessProfileResponse)
def get_business_profile(
    current_user: User = Depends(get_current_user),
) -> BusinessProfileResponse:
    """Get business profile for current tenant."""
    business_profile = session.exec(
        select(BusinessProfile).where(BusinessProfile.tenant_id == current_user.tenant_id)
    ).first()
    
    if not business_profile:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Business profile not found."
        )
    
    # Get activated compliance rules
    rules = get_activated_rules_for_tenant(session, current_user.tenant_id)
    rule_codes = [rule.rule_code.value for rule in rules]
    
    # Construct response from business_profile model
    return BusinessProfileResponse(
        id=business_profile.id,  # type: ignore[arg-type]
        tenant_id=business_profile.tenant_id,
        legal_business_name=business_profile.legal_business_name,
        operating_name=business_profile.operating_name,
        province=business_profile.province.value,
        country=business_profile.country,
        timezone=business_profile.timezone,
        primary_location=business_profile.primary_location,
        business_email=business_profile.business_email,
        business_phone=business_profile.business_phone,
        preferred_notification_channels=business_profile.preferred_notification_channels,
        is_confirmed=business_profile.is_confirmed,
        created_at=business_profile.created_at,
        updated_at=business_profile.updated_at,
        activated_compliance_rules=rule_codes
    )


@router.put("/business-profile", response_model=BusinessProfileResponse)
def update_business_profile(
    payload: BusinessProfileCreate,
    current_user: User = Depends(get_current_user),
) -> BusinessProfileResponse:
    """Update business profile."""
    business_profile = session.exec(
        select(BusinessProfile).where(BusinessProfile.tenant_id == current_user.tenant_id)
    ).first()
    
    if not business_profile:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Business profile not found."
        )
    
    # Update fields
    business_profile.legal_business_name = payload.legal_business_name
    business_profile.operating_name = payload.operating_name
    business_profile.province = payload.province
    business_profile.country = payload.country
    business_profile.timezone = payload.timezone
    business_profile.primary_location = payload.primary_location
    business_profile.business_email = payload.business_email
    business_profile.business_phone = payload.business_phone
    business_profile.preferred_notification_channels = payload.preferred_notification_channels
    
    session.add(business_profile)
    session.commit()
    session.refresh(business_profile)
    
    # Re-activate compliance rules if jurisdiction changed
    # (For simplicity, we'll just ensure rules are activated)
    activate_compliance_rules_for_business_profile(session, business_profile)
    
    rules = get_activated_rules_for_tenant(session, current_user.tenant_id)
    rule_codes = [rule.rule_code.value for rule in rules]
    
    # Construct response from business_profile model
    return BusinessProfileResponse(
        id=business_profile.id,  # type: ignore[arg-type]
        tenant_id=business_profile.tenant_id,
        legal_business_name=business_profile.legal_business_name,
        operating_name=business_profile.operating_name,
        province=business_profile.province.value,
        country=business_profile.country,
        timezone=business_profile.timezone,
        primary_location=business_profile.primary_location,
        business_email=business_profile.business_email,
        business_phone=business_profile.business_phone,
        preferred_notification_channels=business_profile.preferred_notification_channels,
        is_confirmed=business_profile.is_confirmed,
        created_at=business_profile.created_at,
        updated_at=business_profile.updated_at,
        activated_compliance_rules=rule_codes
    )


# ========== Stage 2: Owner Confirmation ==========

@router.post("/owner/confirm", response_model=OwnerConfirmationResponse, status_code=status.HTTP_201_CREATED)
def confirm_owner_role(
    payload: OwnerConfirmationRequest,
    request: FastAPIRequest,
    current_user: User = Depends(get_current_user),
) -> OwnerConfirmationResponse:
    """Stage 2: Confirm Owner role with responsibility disclaimer."""
    if not payload.responsibility_disclaimer_accepted:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You must accept the responsibility disclaimer to confirm Owner role."
        )
    
    # Check if owner already exists
    existing_owner = get_owner_for_tenant(session, current_user.tenant_id)
    if existing_owner and existing_owner.id != current_user.id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="An owner already exists for this tenant."
        )
    
    ip_address = request.client.host if request.client else None
    user_agent = request.headers.get("user-agent")
    
    confirmation = confirm_owner(
        session=session,
        user_id=current_user.id,
        tenant_id=current_user.tenant_id,
        ip_address=ip_address,
        user_agent=user_agent
    )
    
    return OwnerConfirmationResponse(**confirmation.model_dump())


@router.get("/owner/status")
def get_owner_status(
    current_user: User = Depends(get_current_user),
) -> dict:
    """Check if current user is owner."""
    is_owner = is_user_owner(session, current_user.id, current_user.tenant_id)
    owner = get_owner_for_tenant(session, current_user.tenant_id)
    
    return {
        "is_owner": is_owner,
        "owner_exists": owner is not None,
        "owner_email": owner.email if owner else None
    }


# ========== Stage 2: Role Templates ==========

@router.post("/roles/seed-templates", response_model=List[RoleTemplateResponse])
def seed_role_templates_endpoint(
    current_user: User = Depends(get_current_user),
) -> List[RoleTemplateResponse]:
    """Seed default role templates (Manager, Staff, Accountant) for tenant."""
    # Only owner can seed templates
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can seed role templates."
        )
    
    roles = seed_role_templates(session, current_user.tenant_id)
    return [RoleTemplateResponse(**role.model_dump()) for role in roles]


@router.get("/roles/templates", response_model=List[RoleTemplateResponse])
def get_role_templates_endpoint(
    current_user: User = Depends(get_current_user),
) -> List[RoleTemplateResponse]:
    """Get role templates for tenant."""
    roles = get_role_templates(session, current_user.tenant_id)
    return [RoleTemplateResponse(**role.model_dump()) for role in roles]


@router.post("/roles", response_model=RoleResponse, status_code=status.HTTP_201_CREATED)
def create_role(
    payload: RoleCreate,
    current_user: User = Depends(get_current_user),
) -> RoleResponse:
    """Create a custom role."""
    # Only owner can create roles
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can create roles."
        )
    
    # Check if role name already exists
    existing = session.exec(
        select(Role).where(
            Role.tenant_id == current_user.tenant_id,
            Role.name == payload.name
        )
    ).first()
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Role '{payload.name}' already exists."
        )
    
    role = Role(
        tenant_id=current_user.tenant_id,
        name=payload.name
    )
    session.add(role)
    session.commit()
    session.refresh(role)
    
    # TODO: Assign permissions if provided
    # For now, permissions are managed separately
    
    return RoleResponse(**role.model_dump())


@router.get("/roles", response_model=List[RoleResponse])
def list_roles(
    current_user: User = Depends(get_current_user),
) -> List[RoleResponse]:
    """List all roles for tenant."""
    roles = session.exec(
        select(Role).where(Role.tenant_id == current_user.tenant_id)
    ).all()
    return [RoleResponse(**role.model_dump()) for role in roles]


# ========== Stage 2: Team Invitations ==========

@router.post("/invitations", response_model=TeamInvitationResponse, status_code=status.HTTP_201_CREATED)
def create_team_invitation(
    payload: TeamInvitationCreate,
    current_user: User = Depends(get_current_user),
) -> TeamInvitationResponse:
    """Create a team member account with auto-generated credentials."""
    # Only owner can invite team members
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can invite team members."
        )
    
    try:
        # Create user account with auto-generated password
        user, plain_password = create_team_member(
            session=session,
            tenant_id=current_user.tenant_id,
            invited_by_user_id=current_user.id,
            email=payload.email,
            role_id=payload.role_id
        )
        
        # Send credentials email
        try:
            send_team_member_credentials_email(
                session=session,
                user=user,
                plain_password=plain_password,
                invited_by_user_id=current_user.id
            )
        except Exception as e:
            # Log but don't fail user creation
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f"Failed to send credentials email: {e}")
        
        # Return response in TeamInvitationResponse format for backward compatibility
        # Note: We're still using TeamInvitationResponse but the user is already created
        return TeamInvitationResponse(
            id=user.id,  # Using user ID instead of invitation ID
            tenant_id=user.tenant_id,
            invited_by_user_id=current_user.id,
            email=user.email,
            role_id=payload.role_id,
            expires_at=datetime.utcnow(),  # Not applicable anymore
            accepted_at=datetime.utcnow(),  # Already "accepted" since user is created
            created_at=user.created_at
        )
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )


@router.get("/invitations", response_model=List[TeamInvitationResponse])
def list_invitations(
    current_user: User = Depends(get_current_user),
) -> List[TeamInvitationResponse]:
    """List all team members (users) for tenant."""
    # List all users in the tenant (excluding super admins)
    users = session.exec(
        select(User).where(
            User.tenant_id == current_user.tenant_id,
            User.is_super_admin == False
        )
    ).all()
    
    # Convert users to TeamInvitationResponse format for backward compatibility
    result = []
    for user in users:
        # Get role_id if user has roles
        role_id = None
        if user.roles:
            role_id = user.roles[0].id if user.roles else None
        
        result.append(TeamInvitationResponse(
            id=user.id,
            tenant_id=user.tenant_id,
            invited_by_user_id=user.id,  # Not tracked anymore, use user.id as placeholder
            email=user.email,
            role_id=role_id,
            expires_at=datetime.utcnow(),  # Not applicable
            accepted_at=user.created_at if not user.password_change_required else None,  # Consider "accepted" if password changed
            created_at=user.created_at
        ))
    return result


# Note: /invitations/accept endpoint removed - users are now created directly
# Policy acceptance and password change happen on first login via /auth/first-login/change-password

