from fastapi import APIRouter, Depends, HTTPException, status
from sqlmodel import Session, select

from app.api.deps import get_current_user
from app.api.authz import require_permission
from app.db import get_session
from app.models import ModuleEntitlement, ModuleCode, User, Tenant
from app.schemas import EntitlementRead, EntitlementToggleRequest
from app.models.role import PermissionCode
from app.services.audit import log_audit

router = APIRouter(prefix="/entitlements", tags=["entitlements"])


@router.get("", response_model=list[EntitlementRead])
def list_entitlements(
    current_user: User = Depends(get_current_user), session: Session = Depends(get_session)
) -> list[EntitlementRead]:
    statement = select(ModuleEntitlement).where(ModuleEntitlement.tenant_id == current_user.tenant_id)
    entitlements = session.exec(statement).all()
    return [EntitlementRead.model_validate({
        "module_code": e.module_code,
        "enabled": e.enabled,
        "seats": e.seats,
        "ai_access": e.ai_access,
    }) for e in entitlements]


@router.post("/{module_code}", response_model=EntitlementRead)
async def toggle_entitlement(
    module_code: ModuleCode,
    payload: EntitlementToggleRequest,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_ENTITLEMENTS)),
    session: Session = Depends(get_session),
) -> EntitlementRead:
    # Ensure tenant exists (guards against stale users)
    tenant = session.get(Tenant, current_user.tenant_id)
    if not tenant:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Tenant not found.")

    stmt = select(ModuleEntitlement).where(
        ModuleEntitlement.tenant_id == current_user.tenant_id,
        ModuleEntitlement.module_code == module_code,
    )
    entitlement = session.exec(stmt).first()
    if not entitlement:
        entitlement = ModuleEntitlement(
            tenant_id=current_user.tenant_id,
            module_code=module_code,
        )
        session.add(entitlement)

    was_enabled = entitlement.enabled
    entitlement.enabled = payload.enabled
    if payload.seats is not None:
        entitlement.seats = payload.seats
    if payload.ai_access is not None:
        entitlement.ai_access = payload.ai_access

    session.add(entitlement)
    session.commit()
    session.refresh(entitlement)
    
    # If module was just enabled, sync all existing users to this module
    if payload.enabled and not was_enabled:
        try:
            from app.services.module_onboarding import sync_all_users_to_module
            sync_result = await sync_all_users_to_module(
                session=session,
                tenant_id=current_user.tenant_id,
                module_code=module_code,
            )
            import logging
            logger = logging.getLogger(__name__)
            logger.info(f"Synced users to {module_code.value}: {sync_result}")
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f"Failed to sync users to {module_code.value}: {e}")
    
    log_audit(
        session,
        tenant_id=current_user.tenant_id,
        actor_user_id=current_user.id,  # type: ignore[arg-type]
        action="entitlement.toggle",
        target=str(module_code),
        details={"enabled": payload.enabled, "seats": payload.seats, "ai_access": payload.ai_access},
    )
    return EntitlementRead.model_validate({
        "module_code": entitlement.module_code,
        "enabled": entitlement.enabled,
        "seats": entitlement.seats,
        "ai_access": entitlement.ai_access,
    })

