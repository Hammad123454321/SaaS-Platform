import asyncio
from fastapi import APIRouter, Depends, HTTPException, status, Request
from sqlmodel import Session, select

from app.api.authz import require_permission
from app.db import get_session
from app.models import User, ModuleCode, ModuleEntitlement
from app.services.vendor_stub import VendorStubClient
from app.services.vendor_clients.factory import create_vendor_client
from app.models.role import PermissionCode
from app.services.audit import log_audit

router = APIRouter(prefix="/modules", tags=["modules"])


async def _get_client_for(module: ModuleCode, tenant_id: int, session: Session):
    """
    Get vendor client for module. Falls back to stub if no real client available.
    
    Returns:
        Real vendor client (TaskifyClient, etc.) or VendorStubClient as fallback
    """
    real_client = await create_vendor_client(module, tenant_id, session)
    if real_client:
        return real_client
    # Fallback to stub for development/testing
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
async def module_health(
    module_code: ModuleCode,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = await _get_client_for(module_code, current_user.tenant_id, session)
    try:
        if hasattr(client, "health"):
            if asyncio.iscoroutinefunction(client.health):
                health_result = await client.health()
            else:
                health_result = client.health()
        else:
            health_result = {"status": "unknown", "vendor": module_code.value}
        return {"data": health_result, "meta": {"module": module_code}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.get("/{module_code}/records")
async def list_records(
    module_code: ModuleCode,
    resource: str,
    request: Request,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = await _get_client_for(module_code, current_user.tenant_id, session)
    try:
        filters = {k: v for k, v in request.query_params.items() if k != "resource"}
        if hasattr(client, "list_records") and callable(getattr(client, "list_records")):
            if asyncio.iscoroutinefunction(client.list_records):
                records = await client.list_records(resource, **filters)
            else:
                records = client.list_records(resource, **filters)
        else:
            records = []
        return {"data": records, "meta": {"module": module_code, "resource": resource}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/records")
async def create_record(
    module_code: ModuleCode,
    resource: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = await _get_client_for(module_code, current_user.tenant_id, session)
    try:
        if hasattr(client, "create_record") and callable(getattr(client, "create_record")):
            if asyncio.iscoroutinefunction(client.create_record):
                result = await client.create_record(resource, payload)
            else:
                result = client.create_record(resource, payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support create_record")
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
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.patch("/{module_code}/records/{record_id}")
async def update_record(
    module_code: ModuleCode,
    record_id: str,
    resource: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = await _get_client_for(module_code, current_user.tenant_id, session)
    try:
        if resource == "tasks" and hasattr(client, "update_task"):
            try:
                task_id = int(record_id)
            except ValueError as exc:
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail="Task ID must be an integer.",
                ) from exc
            if asyncio.iscoroutinefunction(client.update_task):
                result = await client.update_task(task_id, payload)
            else:
                result = client.update_task(task_id, payload)
        elif hasattr(client, "update_record") and callable(getattr(client, "update_record")):
            if asyncio.iscoroutinefunction(client.update_record):
                result = await client.update_record(resource, record_id, payload)
            else:
                result = client.update_record(resource, record_id, payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support update_record")

        log_audit(
            session,
            tenant_id=current_user.tenant_id,
            actor_user_id=current_user.id,  # type: ignore[arg-type]
            action="module.update_record",
            target=f"{module_code}:{resource}:{record_id}",
            details={"payload_keys": list(payload.keys())},
        )
        return {
            "data": result,
            "meta": {"module": module_code, "resource": resource, "record_id": record_id},
        }
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/records/{record_id}/notes")
async def add_note(
    module_code: ModuleCode,
    record_id: str,
    note: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = await _get_client_for(module_code, current_user.tenant_id, session)
    try:
        if hasattr(client, "add_note") and callable(getattr(client, "add_note")):
            if asyncio.iscoroutinefunction(client.add_note):
                result = await client.add_note(record_id, note)
            else:
                result = client.add_note(record_id, note)
        else:
            # Fallback: use create_record to add a note as a comment
            result = {"record_id": record_id, "note": note, "vendor": module_code.value}
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
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.delete("/{module_code}/records/{record_id}")
async def delete_record(
    module_code: ModuleCode,
    record_id: str,
    resource: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Delete a record from the module (pure wrapper - forwards to Taskify)."""
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = await _get_client_for(module_code, current_user.tenant_id, session)
    try:
        if hasattr(client, "delete_record") and callable(getattr(client, "delete_record")):
            if asyncio.iscoroutinefunction(client.delete_record):
                result = await client.delete_record(resource, record_id)
            else:
                result = client.delete_record(resource, record_id)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support delete_record")
        
        log_audit(
            session,
            tenant_id=current_user.tenant_id,
            actor_user_id=current_user.id,  # type: ignore[arg-type]
            action="module.delete_record",
            target=f"{module_code}:{resource}:{record_id}",
        )
        return {
            "data": result,
            "meta": {"module": module_code, "resource": resource, "record_id": record_id},
        }
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/records/{record_id}/comments")
async def add_comment(
    module_code: ModuleCode,
    record_id: str,
    resource: str,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Add a comment to a record (pure wrapper - forwards to Taskify)."""
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = await _get_client_for(module_code, current_user.tenant_id, session)
    try:
        comment_text = payload.get("comment", "")
        # For tasks, use task-specific comment endpoint
        if resource == "tasks" and hasattr(client, "add_task_comment"):
            task_id = int(record_id)
            if asyncio.iscoroutinefunction(client.add_task_comment):
                result = await client.add_task_comment(task_id, comment_text)
            else:
                result = client.add_task_comment(task_id, comment_text)
        elif hasattr(client, "add_note"):
            if asyncio.iscoroutinefunction(client.add_note):
                result = await client.add_note(record_id, comment_text)
            else:
                result = client.add_note(record_id, comment_text)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support comments")
        
        log_audit(
            session,
            tenant_id=current_user.tenant_id,
            actor_user_id=current_user.id,  # type: ignore[arg-type]
            action="module.add_comment",
            target=f"{module_code}:{resource}:{record_id}",
        )
        return {
            "data": result,
            "meta": {"module": module_code, "resource": resource, "record_id": record_id},
        }
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.get("/{module_code}/records/{record_id}/comments")
async def get_comments(
    module_code: ModuleCode,
    record_id: str,
    resource: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    """Get comments for a record (pure wrapper - forwards to Taskify)."""
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = await _get_client_for(module_code, current_user.tenant_id, session)
    try:
        if resource == "tasks" and hasattr(client, "get_task_comments"):
            task_id = int(record_id)
            if asyncio.iscoroutinefunction(client.get_task_comments):
                comments = await client.get_task_comments(task_id)
            else:
                comments = client.get_task_comments(task_id)
        else:
            comments = []
        
        return {
            "data": comments,
            "meta": {"module": module_code, "resource": resource, "record_id": record_id},
        }
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/draft-email")
async def draft_email(
    module_code: ModuleCode,
    to: str,
    subject: str,
    body: str,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
    session: Session = Depends(get_session),
) -> dict:
    _require_entitlement(session, current_user.tenant_id, module_code)
    client = await _get_client_for(module_code, current_user.tenant_id, session)
    try:
        if hasattr(client, "draft_email") and callable(getattr(client, "draft_email")):
            if asyncio.iscoroutinefunction(client.draft_email):
                result = await client.draft_email(to, subject, body)
            else:
                result = client.draft_email(to, subject, body)
        else:
            result = {"to": to, "subject": subject, "body": body, "vendor": module_code.value}
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
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()

