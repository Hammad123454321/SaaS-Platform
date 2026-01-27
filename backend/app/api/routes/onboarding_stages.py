"""API routes for onboarding stages 1 and 2."""
from datetime import datetime
from fastapi import APIRouter, Depends, HTTPException, status, Request
from fastapi import Request as FastAPIRequest
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
async def create_business_profile(
    payload: BusinessProfileCreate,
    current_user: User = Depends(get_current_user),
) -> BusinessProfileResponse:
    """Stage 1: Create business profile with jurisdiction mapping."""
    tenant_id = str(current_user.tenant_id)
    
    existing = await BusinessProfile.find_one(
        BusinessProfile.tenant_id == tenant_id
    )
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Business profile already exists for this tenant."
        )
    
    business_profile = BusinessProfile(
        tenant_id=tenant_id,
        legal_business_name=payload.legal_business_name,
        operating_name=payload.operating_name,
        province=payload.province,
        country=payload.country,
        timezone=payload.timezone,
        primary_location=payload.primary_location,
        business_email=payload.business_email,
        business_phone=payload.business_phone,
        preferred_notification_channels=payload.preferred_notification_channels,
        is_confirmed=False,
    )
    await business_profile.insert()
    
    activated_rules = await activate_compliance_rules_for_business_profile(business_profile)
    rule_codes = [rule.rule_code.value for rule in activated_rules]
    
    return BusinessProfileResponse(
        id=str(business_profile.id),
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
async def get_business_profile(
    current_user: User = Depends(get_current_user),
) -> BusinessProfileResponse:
    """Get business profile for current tenant."""
    tenant_id = str(current_user.tenant_id)
    
    business_profile = await BusinessProfile.find_one(
        BusinessProfile.tenant_id == tenant_id
    )
    
    if not business_profile:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Business profile not found."
        )
    
    rules = await get_activated_rules_for_tenant(tenant_id)
    rule_codes = [rule.rule_code.value for rule in rules]
    
    return BusinessProfileResponse(
        id=str(business_profile.id),
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
async def update_business_profile(
    payload: BusinessProfileCreate,
    current_user: User = Depends(get_current_user),
) -> BusinessProfileResponse:
    """Update business profile."""
    tenant_id = str(current_user.tenant_id)
    
    business_profile = await BusinessProfile.find_one(
        BusinessProfile.tenant_id == tenant_id
    )
    
    if not business_profile:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Business profile not found."
        )
    
    business_profile.legal_business_name = payload.legal_business_name
    business_profile.operating_name = payload.operating_name
    business_profile.province = payload.province
    business_profile.country = payload.country
    business_profile.timezone = payload.timezone
    business_profile.primary_location = payload.primary_location
    business_profile.business_email = payload.business_email
    business_profile.business_phone = payload.business_phone
    business_profile.preferred_notification_channels = payload.preferred_notification_channels
    
    await business_profile.save()
    
    await activate_compliance_rules_for_business_profile(business_profile)
    
    rules = await get_activated_rules_for_tenant(tenant_id)
    rule_codes = [rule.rule_code.value for rule in rules]
    
    return BusinessProfileResponse(
        id=str(business_profile.id),
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
async def confirm_owner_role(
    payload: OwnerConfirmationRequest,
    request: FastAPIRequest,
    current_user: User = Depends(get_current_user),
) -> OwnerConfirmationResponse:
    """Stage 2: Confirm Owner role with responsibility disclaimer."""
    tenant_id = str(current_user.tenant_id)
    
    if not payload.responsibility_disclaimer_accepted:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You must accept the responsibility disclaimer to confirm Owner role."
        )
    
    existing_owner = await get_owner_for_tenant(tenant_id)
    if existing_owner and str(existing_owner.id) != str(current_user.id):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="An owner already exists for this tenant."
        )
    
    ip_address = request.client.host if request.client else None
    user_agent = request.headers.get("user-agent")
    
    confirmation = await confirm_owner(
        user_id=str(current_user.id),
        tenant_id=tenant_id,
        ip_address=ip_address,
        user_agent=user_agent
    )
    
    return OwnerConfirmationResponse(**confirmation.model_dump())


@router.get("/owner/status")
async def get_owner_status(
    current_user: User = Depends(get_current_user),
) -> dict:
    """Check if current user is owner."""
    tenant_id = str(current_user.tenant_id)
    is_owner = await is_user_owner(str(current_user.id), tenant_id)
    owner = await get_owner_for_tenant(tenant_id)
    
    return {
        "is_owner": is_owner,
        "owner_exists": owner is not None,
        "owner_email": owner.email if owner else None
    }


# ========== Stage 2: Role Templates ==========

@router.post("/roles/seed-templates", response_model=List[RoleTemplateResponse])
async def seed_role_templates_endpoint(
    current_user: User = Depends(get_current_user),
) -> List[RoleTemplateResponse]:
    """Seed default role templates (Manager, Staff, Accountant) for tenant."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can seed role templates."
        )
    
    roles = await seed_role_templates(tenant_id)
    return [RoleTemplateResponse(**role.model_dump()) for role in roles]


@router.get("/roles/templates", response_model=List[RoleTemplateResponse])
async def get_role_templates_endpoint(
    current_user: User = Depends(get_current_user),
) -> List[RoleTemplateResponse]:
    """Get role templates for tenant."""
    tenant_id = str(current_user.tenant_id)
    roles = await get_role_templates(tenant_id)
    return [RoleTemplateResponse(**role.model_dump()) for role in roles]


@router.post("/roles", response_model=RoleResponse, status_code=status.HTTP_201_CREATED)
async def create_role(
    payload: RoleCreate,
    current_user: User = Depends(get_current_user),
) -> RoleResponse:
    """Create a custom role."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can create roles."
        )
    
    existing = await Role.find_one(
        Role.tenant_id == tenant_id,
        Role.name == payload.name
    )
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Role '{payload.name}' already exists."
        )
    
    role = Role(
        tenant_id=tenant_id,
        name=payload.name
    )
    await role.insert()
    
    return RoleResponse(**role.model_dump())


@router.get("/roles", response_model=List[RoleResponse])
async def list_roles(
    current_user: User = Depends(get_current_user),
) -> List[RoleResponse]:
    """List all roles for tenant."""
    tenant_id = str(current_user.tenant_id)
    roles = await Role.find(Role.tenant_id == tenant_id).to_list()
    return [RoleResponse(**role.model_dump()) for role in roles]


# ========== Stage 2: Team Invitations ==========

@router.post("/invitations", response_model=TeamInvitationResponse, status_code=status.HTTP_201_CREATED)
async def create_team_invitation(
    payload: TeamInvitationCreate,
    current_user: User = Depends(get_current_user),
) -> TeamInvitationResponse:
    """Create a team member account with auto-generated credentials."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can invite team members."
        )
    
    try:
        user, plain_password = await create_team_member(
            tenant_id=tenant_id,
            invited_by_user_id=str(current_user.id),
            email=payload.email,
            role_id=payload.role_id
        )
        
        try:
            await send_team_member_credentials_email(
                user=user,
                plain_password=plain_password,
                invited_by_user_id=str(current_user.id)
            )
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f"Failed to send credentials email: {e}")
        
        return TeamInvitationResponse(
            id=str(user.id),
            tenant_id=user.tenant_id,
            invited_by_user_id=str(current_user.id),
            email=user.email,
            role_id=payload.role_id,
            expires_at=datetime.utcnow(),
            accepted_at=datetime.utcnow(),
            created_at=user.created_at
        )
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )


@router.get("/invitations", response_model=List[TeamInvitationResponse])
async def list_invitations(
    current_user: User = Depends(get_current_user),
) -> List[TeamInvitationResponse]:
    """List all team members (users) for tenant."""
    tenant_id = str(current_user.tenant_id)
    
    users = await User.find(
        User.tenant_id == tenant_id,
        User.is_super_admin == False
    ).to_list()
    
    result = []
    for user in users:
        user_roles = await UserRole.find(UserRole.user_id == str(user.id)).to_list()
        role_id = user_roles[0].role_id if user_roles else None
        
        result.append(TeamInvitationResponse(
            id=str(user.id),
            tenant_id=user.tenant_id,
            invited_by_user_id=str(user.id),
            email=user.email,
            role_id=role_id,
            expires_at=datetime.utcnow(),
            accepted_at=user.created_at if not user.password_change_required else None,
            created_at=user.created_at
        ))
    return result
