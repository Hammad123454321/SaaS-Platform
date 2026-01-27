from fastapi import APIRouter, Depends, HTTPException, status

from app.api.deps import get_current_user
from app.api.authz import require_permission
from app.models import ModuleEntitlement, ModuleCode, User, Tenant
from app.schemas import EntitlementRead, EntitlementToggleRequest
from app.models.role import PermissionCode
from app.services.audit import log_audit

router = APIRouter(prefix="/entitlements", tags=["entitlements"])


@router.get("", response_model=list[EntitlementRead])
async def list_entitlements(
    current_user: User = Depends(get_current_user),
) -> list[EntitlementRead]:
    tenant_id = str(current_user.tenant_id)
    entitlements = await ModuleEntitlement.find(
        ModuleEntitlement.tenant_id == tenant_id
    ).to_list()
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
) -> EntitlementRead:
    tenant_id = str(current_user.tenant_id)
    tenant = await Tenant.get(current_user.tenant_id)
    if not tenant:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Tenant not found.")

    entitlement = await ModuleEntitlement.find_one(
        ModuleEntitlement.tenant_id == tenant_id,
        ModuleEntitlement.module_code == module_code,
    )
    if not entitlement:
        entitlement = ModuleEntitlement(
            tenant_id=tenant_id,
            module_code=module_code,
        )

    was_enabled = entitlement.enabled
    entitlement.enabled = payload.enabled
    if payload.seats is not None:
        entitlement.seats = payload.seats
    if payload.ai_access is not None:
        entitlement.ai_access = payload.ai_access

    await entitlement.save()
    
    if payload.enabled and not was_enabled:
        try:
            from app.services.module_onboarding import sync_all_users_to_module
            sync_result = await sync_all_users_to_module(
                tenant_id=tenant_id,
                module_code=module_code,
            )
            import logging
            logger = logging.getLogger(__name__)
            logger.info(f"Synced users to {module_code.value}: {sync_result}")
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f"Failed to sync users to {module_code.value}: {e}")
    
    await log_audit(
        tenant_id=tenant_id,
        actor_user_id=str(current_user.id),
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
