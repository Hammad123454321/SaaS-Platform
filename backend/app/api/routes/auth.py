from datetime import datetime
from typing import List
from fastapi import APIRouter, Depends, HTTPException, status, Response
from sqlalchemy.exc import IntegrityError
from sqlmodel import Session, select
from pydantic import BaseModel, Field
import secrets
import logging

from app.config import settings, is_development
from app.core.security import (
    create_access_token,
    create_refresh_token,
    hash_password,
    verify_password,
    decode_token,
)
from app.db import get_session
from app.models import (
    Tenant,
    User,
    ModuleEntitlement,
    ModuleCode,
    UserRole,
    PasswordResetToken,
    ImpersonationAudit,
)
from app.services.tasks import ensure_default_statuses
from app.models.onboarding import (
    PolicyAcceptance,
    PolicyType,
    CommunicationPreferences,
)
from app.models.role import PermissionCode, Role
from app.seed import ensure_roles_for_tenant
from app.schemas import (
    SignupRequest,
    LoginRequest,
    TokenResponse,
    UserRead,
    PasswordResetRequest,
    PasswordResetConfirm,
    RefreshRequest,
    ImpersonateRequest,
)
from app.schemas.onboarding_stages import (
    VerifyEmailRequest,
    ResendVerificationRequest,
    VerificationStatusResponse,
)
from app.api.deps import get_current_user
from app.api.authz import require_permission
from app.services.email import send_password_reset
from app.services.verification_service import (
    create_verification_token,
    send_verification_email,
    verify_email_token,
)
from app.services.audit_service import log_registration_event
from fastapi import Request

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
    session: Session = Depends(get_session)
) -> dict:
    """Stage 0: Register account with policy acceptance and communication preferences."""
    # Validate policy acceptance
    if not payload.accept_privacy_policy or not payload.accept_terms_of_service:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You must accept Privacy Policy and Terms of Service to register."
        )
    
    _validate_password_strength(payload.password)

    # Create draft tenant
    tenant = Tenant(name=payload.tenant_name, is_draft=True)
    # Development mode override: auto-verify email and activate account
    auto_verify = is_development()
    user = User(
        tenant=tenant,
        email=payload.email.lower(),
        hashed_password=hash_password(payload.password),
        is_super_admin=False,
        email_verified=auto_verify,  # Auto-verify in development mode
        is_active=auto_verify,  # Auto-activate in development mode
    )
    try:
        session.add(tenant)
        session.flush()  # may raise duplicate tenant name
        session.add(user)
        session.flush()

        # Store policy acceptances
        policy_version = "1.0"  # Hardcoded for now
        ip_address = request.client.host if request.client else None
        user_agent = request.headers.get("user-agent")
        
        session.add(PolicyAcceptance(
            user_id=user.id,
            policy_type=PolicyType.PRIVACY_POLICY,
            policy_version=policy_version,
            ip_address=ip_address,
            user_agent=user_agent
        ))
        session.add(PolicyAcceptance(
            user_id=user.id,
            policy_type=PolicyType.TERMS_OF_SERVICE,
            policy_version=policy_version,
            ip_address=ip_address,
            user_agent=user_agent
        ))

        # Store communication preferences
        marketing_consent_at = datetime.utcnow() if payload.marketing_email_consent else None
        session.add(CommunicationPreferences(
            user_id=user.id,
            email_enabled=payload.email_enabled,
            sms_enabled=payload.sms_enabled,
            marketing_email_consent=payload.marketing_email_consent,
            marketing_email_consent_at=marketing_consent_at,
            marketing_email_consent_source="signup"
        ))

        roles_by_name = ensure_roles_for_tenant(session, tenant.id)  # type: ignore[arg-type]
        # Assign company_admin role (not super_admin) for tenant owner
        company_admin_role = roles_by_name.get("company_admin")
        if company_admin_role:
            session.add(UserRole(user_id=user.id, role_id=company_admin_role.id))  # type: ignore[arg-type]

        # Initialize entitlements disabled by default
        for module in ModuleCode:
            session.add(
                ModuleEntitlement(
                    tenant_id=tenant.id,  # type: ignore[arg-type]
                    module_code=module,
                    enabled=False,
                    seats=0,
                    ai_access=False,
                )
            )
        session.commit()
        
        # Create default task statuses for the new tenant
        try:
            ensure_default_statuses(session, tenant.id)  # type: ignore[arg-type]
        except Exception as e:
            logger = logging.getLogger(__name__)
            logger.warning(f"Failed to create default statuses for tenant {tenant.id}: {e}")
            # Don't fail signup if status creation fails
    except IntegrityError as exc:
        session.rollback()
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Tenant or user already exists.",
        ) from exc
    
    session.refresh(user)

    # Log registration event
    log_registration_event(
        session=session,
        user_id=user.id,
        tenant_id=user.tenant_id,
        event_type="registration",
        ip_address=ip_address,
        user_agent=user_agent
    )

    # Send verification email (skip in development mode if auto-verified)
    if not auto_verify:
        try:
            send_verification_email(session, user, resend=False)
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
        "email": user.email
    }


@router.post("/login")
def login(payload: LoginRequest, response: Response, session: Session = Depends(get_session)):
    """Login endpoint - blocks unverified users (Stage 0 hard gate)."""
    statement = select(User).where(User.email == payload.email.lower())
    result = session.exec(statement).first()
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
    session: Session = Depends(get_session)
) -> UserRead:
    roles = [r.name for r in current_user.roles]
    return UserRead.model_validate({**current_user.model_dump(), "roles": roles})


@router.get("/onboarding-status")
def get_onboarding_status(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session)
) -> dict:
    """Check if user's onboarding is complete."""
    from app.services.onboarding_completion_service import is_onboarding_complete
    
    is_complete, missing_stage = is_onboarding_complete(session, current_user)
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
def request_password_reset(payload: PasswordResetRequest, session: Session = Depends(get_session)) -> dict[str, str]:
    user = session.exec(select(User).where(User.email == payload.email.lower())).first()
    if not user:
        return {"status": "ok"}  # Avoid user enumeration

    raw_token = secrets.token_urlsafe(32)
    token_hash = hash_password(raw_token)
    reset = PasswordResetToken(user_id=user.id, token_hash=token_hash)  # type: ignore[arg-type]
    session.add(reset)
    session.commit()

    reset_link = f"{settings.frontend_base_url}/reset-password?token={raw_token}"
    send_password_reset(user.email, reset_link)
    return {"status": "ok"}


@router.post("/password-reset/confirm")
def confirm_password_reset(
    payload: PasswordResetConfirm, session: Session = Depends(get_session)
) -> dict[str, str]:
    stmt = select(PasswordResetToken).where(
        PasswordResetToken.used_at.is_(None),
        PasswordResetToken.expires_at >= datetime.utcnow(),
    )
    tokens = session.exec(stmt).all()
    target = None
    for t in tokens:
        if verify_password(payload.token, t.token_hash):
            target = t
            break
    if not target:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Invalid or expired token.")

    user = session.get(User, target.user_id)
    if not user:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Invalid user.")

    _validate_password_strength(payload.new_password)
    user.hashed_password = hash_password(payload.new_password)
    target.used_at = datetime.utcnow()
    session.add(user)
    session.add(target)
    session.commit()

    # Cleanup expired tokens opportunistically
    session.exec(
        PasswordResetToken.__table__.delete().where(PasswordResetToken.expires_at < datetime.utcnow())
    )
    session.commit()
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
def change_password_first_login(
    payload: FirstLoginPasswordChangeRequest,
    request: Request,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session)
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
    session.add(current_user)
    session.flush()
    
    # Store policy acceptances
    policy_version = "1.0"
    ip_address = request.client.host if request.client else None
    user_agent = request.headers.get("user-agent")
    
    from app.models.onboarding import PolicyAcceptance, PolicyType, CommunicationPreferences
    session.add(PolicyAcceptance(
        user_id=current_user.id,
        policy_type=PolicyType.PRIVACY_POLICY,
        policy_version=policy_version,
        ip_address=ip_address,
        user_agent=user_agent
    ))
    session.add(PolicyAcceptance(
        user_id=current_user.id,
        policy_type=PolicyType.TERMS_OF_SERVICE,
        policy_version=policy_version,
        ip_address=ip_address,
        user_agent=user_agent
    ))
    
    # Store communication preferences
    marketing_consent_at = datetime.utcnow() if payload.marketing_email_consent else None
    session.add(CommunicationPreferences(
        user_id=current_user.id,
        email_enabled=payload.email_enabled,
        sms_enabled=payload.sms_enabled,
        marketing_email_consent=payload.marketing_email_consent,
        marketing_email_consent_at=marketing_consent_at,
        marketing_email_consent_source="first_login"
    ))
    
    # Handle HR policy acknowledgements
    from app.services.compliance_service import get_required_hr_policies, acknowledge_hr_policies
    required_policies = get_required_hr_policies(session)
    required_policy_ids = {p.id for p in required_policies}
    
    if payload.hr_policy_acknowledgements:
        acknowledged_ids = set(payload.hr_policy_acknowledgements)
        if not required_policy_ids.issubset(acknowledged_ids):
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="You must acknowledge all required HR policies."
            )
        
        # Record acknowledgements
        acknowledge_hr_policies(
            session=session,
            user_id=current_user.id,
            policy_ids=payload.hr_policy_acknowledgements,
            ip_address=ip_address,
            user_agent=user_agent
        )
    elif required_policies:
        # If policies exist but none acknowledged, block access
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You must acknowledge all required HR policies."
        )
    
    session.commit()
    return {"status": "ok", "message": "Password changed successfully and policies accepted."}


@router.post("/impersonate", response_model=TokenResponse)
def impersonate(
    payload: ImpersonateRequest,
    current_user: User = Depends(require_permission(PermissionCode.IMPERSONATE_USER)),
    session: Session = Depends(get_session),
) -> TokenResponse:
    target = session.get(User, payload.target_user_id)
    if not target or target.tenant_id != current_user.tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found.")
    subject = f"{target.id}:{target.tenant_id}"
    role_names = [r.name for r in target.roles]
    access = create_access_token(subject, roles=role_names, impersonated_by=current_user.id)
    refresh = create_refresh_token(subject, impersonated_by=current_user.id)
    session.add(
        ImpersonationAudit(
            actor_user_id=current_user.id,  # type: ignore[arg-type]
            target_user_id=target.id,  # type: ignore[arg-type]
            reason=payload.reason or "",
        )
    )
    session.commit()
    return TokenResponse(access_token=access, refresh_token=refresh)


@router.post("/logout")
def logout() -> dict[str, str]:
    return {"status": "ok"}


# ========== Stage 0: Email Verification Endpoints ==========

@router.post("/verify-email", response_model=dict)
def verify_email(payload: VerifyEmailRequest, session: Session = Depends(get_session)) -> dict:
    """Verify email address using token."""
    user = verify_email_token(session, payload.token)
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
def resend_verification(
    payload: ResendVerificationRequest,
    session: Session = Depends(get_session)
) -> dict:
    """Resend verification email."""
    user = session.exec(select(User).where(User.email == payload.email.lower())).first()
    if not user:
        # Don't reveal if user exists
        return {"status": "success", "message": "If the email exists, a verification link has been sent."}
    
    if user.email_verified:
        return {"status": "success", "message": "Email is already verified."}
    
    try:
        send_verification_email(session, user, resend=True)
        return {"status": "success", "message": "Verification email sent."}
    except Exception as e:
        logger = logging.getLogger(__name__)
        logger.error(f"Failed to resend verification email: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to send verification email. Please try again later."
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

