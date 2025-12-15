from fastapi import APIRouter, Depends, HTTPException, status
from sqlmodel import Session, select

from app.api.authz import require_permission
from app.db import get_session
from app.models import User, ModuleCode, ModuleEntitlement
from app.services.vendor_stub import VendorStubClient
from app.models.role import PermissionCode
from app.services.audit import log_audit

router = APIRouter(prefix="/modules", tags=["modules"])


def _stub_client_for(module: ModuleCode, tenant_id: int) -> VendorStubClient:
    # In real impl, pick vendor + credentials from VendorCredential; here stub only.
    return VendorStubClient(vendor=module.value, credentials={"tenant_id": tenant_id})


def _require_entitlement(
    session: Session, tenant_id: int, module_code: ModuleCode
) -> ModuleEntitlement:
    stmt = select(ModuleEntitlement).where(
        ModuleEntitlement.tenant_id == tenant_id, ModuleEntitlement.module_code == module_code
    )
    ent = session.exec(stmt).first()
    if not ent or not ent.enabled:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Module not enabled.")
    return ent


@router.get("/{module_code}/health")
def module_health(
    module_code: ModuleCode,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = _stub_client_for(module_code, current_user.tenant_id)
    return {"data": client.health(), "meta": {"module": module_code}}


@router.get("/{module_code}/records")
def list_records(
    module_code: ModuleCode,
    resource: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> list[dict]:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = _stub_client_for(module_code, current_user.tenant_id)
    return {"data": client.list_records(resource), "meta": {"module": module_code, "resource": resource}}


@router.post("/{module_code}/records")
def create_record(
    module_code: ModuleCode,
    resource: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = _stub_client_for(module_code, current_user.tenant_id)
    result = client.create_record(resource, payload)
    log_audit(
        session,
        tenant_id=current_user.tenant_id,
        actor_user_id=current_user.id,  # type: ignore[arg-type]
        action="module.create_record",
        target=f"{module_code}:{resource}",
        details={"payload_keys": list(payload.keys())},
    )
    return {
        "data": result,
        "meta": {"module": module_code, "resource": resource},
    }


@router.post("/{module_code}/records/{record_id}/notes")
def add_note(
    module_code: ModuleCode,
    record_id: str,
    note: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = _stub_client_for(module_code, current_user.tenant_id)
    result = client.add_note(record_id, note)
    log_audit(
        session,
        tenant_id=current_user.tenant_id,
        actor_user_id=current_user.id,  # type: ignore[arg-type]
        action="module.add_note",
        target=f"{module_code}:{record_id}",
    )
    return {
        "data": result,
        "meta": {"module": module_code, "record_id": record_id},
    }


@router.post("/{module_code}/draft-email")
def draft_email(
    module_code: ModuleCode,
    to: str,
    subject: str,
    body: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = _stub_client_for(module_code, current_user.tenant_id)
    result = client.draft_email(to, subject, body)
    log_audit(
        session,
        tenant_id=current_user.tenant_id,
        actor_user_id=current_user.id,  # type: ignore[arg-type]
        action="module.draft_email",
        target=f"{module_code}:{to}",
    )
    return {
        "data": result,
        "meta": {"module": module_code, "to": to},
    }

