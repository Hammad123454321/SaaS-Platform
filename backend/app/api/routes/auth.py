from datetime import datetime
from typing import List

import logging
import secrets
from fastapi import APIRouter, Depends, HTTPException, Request, Response, status
from pydantic import BaseModel, Field

from app.api.authz import require_permission
from app.api.deps import get_current_user
from app.config import is_development, settings
from app.core.security import (
    create_access_token,
    create_refresh_token,
    decode_token,
    hash_password,
    verify_password,
)
from app.models import (
    ImpersonationAudit,
    ModuleCode,
    ModuleEntitlement,
    PasswordResetToken,
    Tenant,
    User,
    UserRole,
)
from app.models.onboarding import (
    CommunicationPreferences,
    PolicyAcceptance,
    PolicyType,
)
from app.models.role import PermissionCode, Role
from app.schemas import (
    ImpersonateRequest,
    LoginRequest,
    PasswordResetConfirm,
    PasswordResetRequest,
    RefreshRequest,
    SignupRequest,
    TokenResponse,
    UserRead,
)
from app.schemas.onboarding_stages import (
    ResendVerificationRequest,
    VerificationStatusResponse,
    VerifyEmailRequest,
)
from app.seed import ensure_roles_for_tenant
from app.services.audit_service import log_registration_event
from app.services.email import send_password_reset
from app.services.tasks import ensure_default_statuses
from app.services.verification_service import (
    send_verification_email,
    verify_email_token,
)

router = APIRouter(prefix="/auth", tags=["auth"])


def _validate_password_strength(password: str) -> None:
    if len(password) < settings.password_min_length:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Password must be at least {settings.password_min_length} characters.",
        )
    if len(password) > settings.password_max_length:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Password must be at most {settings.password_max_length} characters (bcrypt limit).",
        )
    if settings.password_require_special and not any(
        ch in "!@#$%^&*()-_=+[]{}|;:,.<>?/" for ch in password
    ):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Password must contain at least one special character.",
        )


@router.post("/signup", response_model=dict, status_code=status.HTTP_201_CREATED)
async def signup(
    payload: SignupRequest,
    request: Request,
) -> dict:
    """Stage 0: Register account with policy acceptance and communication preferences."""
    # Validate policy acceptance
    if not payload.accept_privacy_policy or not payload.accept_terms_of_service:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You must accept Privacy Policy and Terms of Service to register.",
        )

    _validate_password_strength(payload.password)

    # Create draft tenant (Mongo/Beanie)
    tenant = Tenant(name=payload.tenant_name, is_draft=True)
    try:
        await tenant.insert()
    except Exception:
        # Likely duplicate tenant name or other constraint violation
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Tenant already exists.",
        )

    # Development mode override: auto-verify email and activate account
    auto_verify = is_development()
    user = User(
        tenant_id=str(tenant.id),
        tenant=tenant,
        email=payload.email.lower(),
        hashed_password=hash_password(payload.password),
        is_super_admin=False,
        email_verified=auto_verify,
        is_active=auto_verify,
        is_owner=True,
    )

    try:
        await user.insert()
    except Exception:
        # Clean up tenant if user creation fails due to duplicate email, etc.
        await tenant.delete()
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="User already exists.",
        )

    # Store policy acceptances
    policy_version = "1.0"  # Hardcoded for now
    ip_address = request.client.host if request.client else None
    user_agent = request.headers.get("user-agent")

    await PolicyAcceptance(
        user_id=str(user.id),
        policy_type=PolicyType.PRIVACY_POLICY,
        policy_version=policy_version,
        ip_address=ip_address,
        user_agent=user_agent,
    ).insert()
    await PolicyAcceptance(
        user_id=str(user.id),
        policy_type=PolicyType.TERMS_OF_SERVICE,
        policy_version=policy_version,
        ip_address=ip_address,
        user_agent=user_agent,
    ).insert()

    # Store communication preferences
    marketing_consent_at = datetime.utcnow() if payload.marketing_email_consent else None
    await CommunicationPreferences(
        user_id=str(user.id),
        email_enabled=payload.email_enabled,
        sms_enabled=payload.sms_enabled,
        marketing_email_consent=payload.marketing_email_consent,
        marketing_email_consent_at=marketing_consent_at,
        marketing_email_consent_source="signup",
    ).insert()

    # Ensure roles and assign company_admin to owner
    roles_by_name = await ensure_roles_for_tenant(str(tenant.id))
    company_admin_role = roles_by_name.get("company_admin")
    if company_admin_role:
        await UserRole(
            user_id=str(user.id),
            role_id=str(company_admin_role.id),
        ).insert()

    # Initialize entitlements disabled by default
    for module in ModuleCode:
        await ModuleEntitlement(
            tenant_id=str(tenant.id),
            module_code=module,
            enabled=False,
            seats=0,
            ai_access=False,
        ).insert()

    # Create default task statuses for the new tenant (Mongo-aware helper)
    try:
        await ensure_default_statuses(str(tenant.id))
    except Exception as e:
        logger = logging.getLogger(__name__)
        logger.warning(f"Failed to create default statuses for tenant {tenant.id}: {e}")
        # Don't fail signup if status creation fails

    # Log registration event
    await log_registration_event(
        user_id=str(user.id),
        tenant_id=str(tenant.id),
        event_type="registration",
        ip_address=ip_address,
        user_agent=user_agent,
    )

    # Send verification email (skip in development mode if auto-verified)
    if not auto_verify:
        try:
            await send_verification_email(user, resend=False)
        except Exception as e:
            logger = logging.getLogger(__name__)
            logger.error(f"Failed to send verification email: {e}")
            # Don't fail signup if email fails, but log it

    # Return success message based on mode
    message = (
        "Registration successful. Account auto-verified (development mode)."
        if auto_verify
        else "Registration successful. Please check your email to verify your account."
    )
    return {
        "status": "success",
        "message": message,
        "email": user.email,
    }


@router.post("/login")
async def login(payload: LoginRequest, response: Response):
    """Login endpoint - blocks unverified users (Stage 0 hard gate)."""
    result = await User.find_one(User.email == payload.email.lower())
    if not result or not verify_password(payload.password, result.hashed_password):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid credentials.")
    
    # Stage 0: Hard gate - block login if email not verified
    # Development mode override: skip email verification check
    if not is_development() and not result.email_verified:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Email not verified. Please check your email and verify your account before logging in."
        )
    
    if not result.is_active:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Account is inactive.")

    subject = f"{result.id}:{result.tenant_id}"
    # Collect role names for JWT claim
    role_names = [r.name for r in result.roles]
    access = create_access_token(subject, roles=role_names)
    refresh = create_refresh_token(subject)
    
    # Include password_change_required flag in response
    return {
        "access_token": access,
        "refresh_token": refresh,
        "password_change_required": result.password_change_required
    }


@router.get("/me", response_model=UserRead)
def me(
    current_user: User = Depends(get_current_user),
) -> UserRead:
    roles = [r.name for r in current_user.roles]
    return UserRead.model_validate({**current_user.model_dump(), "roles": roles})


@router.get("/onboarding-status")
async def get_onboarding_status(
    current_user: User = Depends(get_current_user),
) -> dict:
    """Check if user's onboarding is complete."""
    from app.services.onboarding_completion_service import is_onboarding_complete

    is_complete, missing_stage = await is_onboarding_complete(current_user)
    return {
        "onboarding_complete": is_complete,
        "missing_stage": missing_stage
    }


@router.post("/refresh", response_model=TokenResponse)
def refresh_tokens(payload: RefreshRequest, response: Response) -> TokenResponse:
    data = decode_token(payload.refresh_token, refresh=True)
    if not data or "sub" not in data:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid refresh token.")
    access = create_access_token(data["sub"], roles=data.get("roles", []), impersonated_by=data.get("impersonated_by"))
    new_refresh = create_refresh_token(data["sub"], impersonated_by=data.get("impersonated_by"))
    return TokenResponse(access_token=access, refresh_token=new_refresh)


@router.post("/password-reset/request")
async def request_password_reset(payload: PasswordResetRequest) -> dict[str, str]:
    user = await User.find_one(User.email == payload.email.lower())
    if not user:
        return {"status": "ok"}  # Avoid user enumeration

    raw_token = secrets.token_urlsafe(32)
    token_hash = hash_password(raw_token)
    reset = PasswordResetToken(user_id=str(user.id), token_hash=token_hash)
    await reset.insert()

    reset_link = f"{settings.frontend_base_url}/reset-password?token={raw_token}"
    send_password_reset(user.email, reset_link)
    return {"status": "ok"}


@router.post("/password-reset/confirm")
async def confirm_password_reset(
    payload: PasswordResetConfirm,
) -> dict[str, str]:
    """Confirm password reset using a Beanie-stored reset token."""
    now = datetime.utcnow()

    # Find a valid token document and verify the raw token against the stored hash
    candidates = await PasswordResetToken.find(
        PasswordResetToken.used_at.is_(None),  # type: ignore[arg-type]
        PasswordResetToken.expires_at >= now,
    ).to_list()

    target: PasswordResetToken | None = None
    for t in candidates:
        if verify_password(payload.token, t.token_hash):
            target = t
            break

    if not target:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Invalid or expired token.",
        )

    user = await User.get(target.user_id)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Invalid user.",
        )

    _validate_password_strength(payload.new_password)
    user.hashed_password = hash_password(payload.new_password)
    target.used_at = now

    await user.save()
    await target.save()

    # Cleanup expired tokens opportunistically
    expired_tokens = await PasswordResetToken.find(
        PasswordResetToken.expires_at < now
    ).to_list()
    for t in expired_tokens:
        await t.delete()

    return {"status": "ok"}


class FirstLoginPasswordChangeRequest(BaseModel):
    """Request for password change on first login with policy acceptance."""
    new_password: str
    accept_privacy_policy: bool = Field(description="Must be True")
    accept_terms_of_service: bool = Field(description="Must be True")
    email_enabled: bool = Field(default=True)
    sms_enabled: bool = Field(default=False)
    marketing_email_consent: bool = Field(default=False)
    hr_policy_acknowledgements: List[int] = Field(default=[], description="List of HR policy IDs to acknowledge")


@router.post("/first-login/change-password")
async def change_password_first_login(
    payload: FirstLoginPasswordChangeRequest,
    request: Request,
    current_user: User = Depends(get_current_user),
) -> dict[str, str]:
    """Change password on first login and accept policies."""
    # Check if password change is required
    if not current_user.password_change_required:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Password change is not required for this account."
        )
    
    # Validate policy acceptance
    if not payload.accept_privacy_policy or not payload.accept_terms_of_service:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You must accept Privacy Policy and Terms of Service."
        )
    
    # Validate and update password
    _validate_password_strength(payload.new_password)
    current_user.hashed_password = hash_password(payload.new_password)
    current_user.password_change_required = False  # Clear the flag
    await current_user.save()

    # Store policy acceptances
    policy_version = "1.0"
    ip_address = request.client.host if request.client else None
    user_agent = request.headers.get("user-agent")

    await PolicyAcceptance(
        user_id=str(current_user.id),
        policy_type=PolicyType.PRIVACY_POLICY,
        policy_version=policy_version,
        ip_address=ip_address,
        user_agent=user_agent,
    ).insert()
    await PolicyAcceptance(
        user_id=str(current_user.id),
        policy_type=PolicyType.TERMS_OF_SERVICE,
        policy_version=policy_version,
        ip_address=ip_address,
        user_agent=user_agent,
    ).insert()

    # Store communication preferences
    marketing_consent_at = datetime.utcnow() if payload.marketing_email_consent else None
    await CommunicationPreferences(
        user_id=str(current_user.id),
        email_enabled=payload.email_enabled,
        sms_enabled=payload.sms_enabled,
        marketing_email_consent=payload.marketing_email_consent,
        marketing_email_consent_at=marketing_consent_at,
        marketing_email_consent_source="first_login",
    ).insert()

    # Handle HR policy acknowledgements
    from app.services.compliance_service import (
        get_required_hr_policies,
        acknowledge_hr_policies,
    )

    required_policies = await get_required_hr_policies()
    required_policy_ids = {p.id for p in required_policies}
    
    if payload.hr_policy_acknowledgements:
        acknowledged_ids = set(payload.hr_policy_acknowledgements)
        if not required_policy_ids.issubset(acknowledged_ids):
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="You must acknowledge all required HR policies."
            )
        
        # Record acknowledgements
        await acknowledge_hr_policies(
            user_id=str(current_user.id),
            policy_ids=[str(pid) for pid in payload.hr_policy_acknowledgements],
            ip_address=ip_address,
            user_agent=user_agent,
        )
    elif required_policies:
        # If policies exist but none acknowledged, block access
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You must acknowledge all required HR policies."
        )
    
    return {
        "status": "ok",
        "message": "Password changed successfully and policies accepted.",
    }


@router.post("/impersonate", response_model=TokenResponse)
async def impersonate(
    payload: ImpersonateRequest,
    current_user: User = Depends(
        require_permission(PermissionCode.IMPERSONATE_USER)
    ),
) -> TokenResponse:
    target = await User.get(payload.target_user_id)
    if not target or target.tenant_id != current_user.tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="User not found."
        )

    subject = f"{target.id}:{target.tenant_id}"
    role_names = [r.name for r in target.roles]
    access = create_access_token(
        subject, roles=role_names, impersonated_by=str(current_user.id)
    )
    refresh = create_refresh_token(
        subject, impersonated_by=str(current_user.id)
    )

    await ImpersonationAudit(
        actor_user_id=str(current_user.id),
        target_user_id=str(target.id),
        reason=payload.reason or "",
    ).insert()

    return TokenResponse(access_token=access, refresh_token=refresh)


@router.post("/logout")
def logout() -> dict[str, str]:
    return {"status": "ok"}


# ========== Stage 0: Email Verification Endpoints ==========

@router.post("/verify-email", response_model=dict)
async def verify_email(payload: VerifyEmailRequest) -> dict:
    """Verify email address using token."""
    user = await verify_email_token(payload.token)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Invalid or expired verification token."
        )
    
    return {
        "status": "success",
        "message": "Email verified successfully. You can now log in.",
        "email": user.email
    }


@router.post("/resend-verification", response_model=dict)
async def resend_verification(
    payload: ResendVerificationRequest,
) -> dict:
    """Resend verification email."""
    user = await User.find_one(User.email == payload.email.lower())
    if not user:
        # Don't reveal if user exists
        return {"status": "success", "message": "If the email exists, a verification link has been sent."}
    
    if user.email_verified:
        return {"status": "success", "message": "Email is already verified."}

    try:
        await send_verification_email(user, resend=True)
        return {"status": "success", "message": "Verification email sent."}
    except Exception as e:
        logger = logging.getLogger(__name__)
        logger.error(f"Failed to resend verification email: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to send verification email. Please try again later.",
        )


@router.get("/verification-status", response_model=VerificationStatusResponse)
def get_verification_status(
    current_user: User = Depends(get_current_user)
) -> VerificationStatusResponse:
    """Get email verification status for current user."""
    return VerificationStatusResponse(
        email_verified=current_user.email_verified,
        email=current_user.email
    )

