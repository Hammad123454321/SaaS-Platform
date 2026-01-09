from datetime import datetime
from fastapi import APIRouter, Depends, HTTPException, status, Response
from sqlalchemy.exc import IntegrityError
from sqlmodel import Session, select
import secrets
import logging

from app.config import settings
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
from app.api.deps import get_current_user
from app.api.authz import require_permission
from app.services.email import send_password_reset

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


@router.post("/signup", response_model=TokenResponse, status_code=status.HTTP_201_CREATED)
async def signup(payload: SignupRequest, response: Response, session: Session = Depends(get_session)) -> TokenResponse:
    _validate_password_strength(payload.password)

    tenant = Tenant(name=payload.tenant_name)
    user = User(
        tenant=tenant,
        email=payload.email.lower(),
        hashed_password=hash_password(payload.password),
        is_super_admin=False,  # Only platform admins should have this flag
    )
    try:
        session.add(tenant)
        session.flush()  # may raise duplicate tenant name
        session.add(user)

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
    except IntegrityError as exc:
        session.rollback()
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Tenant or user already exists.",
        ) from exc
    
    session.refresh(user)

    # Provision user to enabled modules (if any are enabled)
    # Note: By default modules are disabled, so this won't run initially
    # But if modules are enabled later, users will be provisioned
    try:
        from app.services.module_onboarding import provision_user_to_modules
        await provision_user_to_modules(session, user, payload.password)
    except Exception as e:
        # Log but don't fail signup if module provisioning fails
        logger = logging.getLogger(__name__)
        logger.warning(f"Failed to provision user to modules: {e}")

    # Get role names for JWT token
    user_roles = session.exec(
        select(Role)
        .join(UserRole)
        .where(UserRole.user_id == user.id)
    ).all()
    role_names = [r.name for r in user_roles]

    subject = f"{user.id}:{user.tenant_id}"
    access = create_access_token(subject, roles=role_names)
    refresh = create_refresh_token(subject)
    return TokenResponse(access_token=access, refresh_token=refresh)


@router.post("/login", response_model=TokenResponse)
def login(payload: LoginRequest, response: Response, session: Session = Depends(get_session)) -> TokenResponse:
    statement = select(User).where(User.email == payload.email.lower(), User.is_active == True)  # noqa: E712
    result = session.exec(statement).first()
    if not result or not verify_password(payload.password, result.hashed_password):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid credentials.")

    subject = f"{result.id}:{result.tenant_id}"
    # Collect role names for JWT claim
    role_names = [r.name for r in result.roles]
    access = create_access_token(subject, roles=role_names)
    refresh = create_refresh_token(subject)
    return TokenResponse(access_token=access, refresh_token=refresh)


@router.get("/me", response_model=UserRead)
def me(current_user: User = Depends(get_current_user)) -> UserRead:
    roles = [r.name for r in current_user.roles]
    return UserRead.model_validate({**current_user.model_dump(), "roles": roles})


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

