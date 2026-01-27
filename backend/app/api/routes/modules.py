import asyncio
from typing import Optional
from fastapi import APIRouter, Depends, HTTPException, status, Request

from app.api.authz import require_permission
from app.models import User, ModuleCode, ModuleEntitlement
from app.services.vendor_stub import VendorStubClient
from app.services.vendor_clients.factory import create_vendor_client
from app.models.role import PermissionCode
from app.services.audit import log_audit

router = APIRouter(prefix="/modules", tags=["modules"])


async def _get_client_for(module: ModuleCode, tenant_id: str, user_id: str = None):
    """
    Get vendor client for module. Falls back to stub if no real client available.
    
    Note: TASKS module has its own dedicated routes and doesn't use this.
    
    Returns:
        Real vendor client or VendorStubClient as fallback
    """
    if module == ModuleCode.TASKS:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Tasks module uses dedicated routes at /modules/tasks"
        )
    
    real_client = await create_vendor_client(module, tenant_id)
    if real_client:
        return real_client
    return VendorStubClient(vendor=module.value, credentials={"tenant_id": tenant_id})


async def _require_entitlement(
    tenant_id: str, module_code: ModuleCode
) -> ModuleEntitlement:
    ent = await ModuleEntitlement.find_one(
        ModuleEntitlement.tenant_id == tenant_id,
        ModuleEntitlement.module_code == module_code
    )
    if not ent or not ent.enabled:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Module not enabled.")
    return ent


@router.get("/{module_code}/health")
async def module_health(
    module_code: ModuleCode,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id)
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
) -> dict:
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id)
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
) -> dict:
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id)
    try:
        if hasattr(client, "create_record") and callable(getattr(client, "create_record")):
            if asyncio.iscoroutinefunction(client.create_record):
                result = await client.create_record(resource, payload)
            else:
                result = client.create_record(resource, payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support create_record")
        await log_audit(
            tenant_id=tenant_id,
            actor_user_id=str(current_user.id),
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
) -> dict:
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id)
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

        await log_audit(
            tenant_id=tenant_id,
            actor_user_id=str(current_user.id),
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
) -> dict:
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id)
    try:
        if hasattr(client, "add_note") and callable(getattr(client, "add_note")):
            if asyncio.iscoroutinefunction(client.add_note):
                result = await client.add_note(record_id, note)
            else:
                result = client.add_note(record_id, note)
        else:
            result = {"record_id": record_id, "note": note, "vendor": module_code.value}
        await log_audit(
            tenant_id=tenant_id,
            actor_user_id=str(current_user.id),
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
) -> dict:
    """Delete a record from the module (pure wrapper - forwards to Taskify)."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id)
    try:
        if hasattr(client, "delete_record") and callable(getattr(client, "delete_record")):
            if asyncio.iscoroutinefunction(client.delete_record):
                result = await client.delete_record(resource, record_id)
            else:
                result = client.delete_record(resource, record_id)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support delete_record")
        
        await log_audit(
            tenant_id=tenant_id,
            actor_user_id=str(current_user.id),
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
) -> dict:
    """Add a comment to a record (pure wrapper - forwards to Taskify)."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id)
    try:
        comment_text = payload.get("comment", "")
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
        
        await log_audit(
            tenant_id=tenant_id,
            actor_user_id=str(current_user.id),
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
) -> dict:
    """Get comments for a record (pure wrapper - forwards to Taskify)."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id)
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
) -> dict:
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id)
    try:
        if hasattr(client, "draft_email") and callable(getattr(client, "draft_email")):
            if asyncio.iscoroutinefunction(client.draft_email):
                result = await client.draft_email(to, subject, body)
            else:
                result = client.draft_email(to, subject, body)
        else:
            result = {"to": to, "subject": subject, "body": body, "vendor": module_code.value}
        await log_audit(
            tenant_id=tenant_id,
            actor_user_id=str(current_user.id),
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


# ========== MILESTONES ==========
@router.get("/{module_code}/milestones")
async def list_milestones(
    module_code: ModuleCode,
    project_id: Optional[int] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """List milestones, optionally filtered by project."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "list_milestones"):
            if asyncio.iscoroutinefunction(client.list_milestones):
                milestones = await client.list_milestones(project_id=project_id)
            else:
                milestones = client.list_milestones(project_id=project_id)
        else:
            milestones = []
        return {"data": milestones, "meta": {"module": module_code, "project_id": project_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/milestones")
async def create_milestone(
    module_code: ModuleCode,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Create a new milestone."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "create_milestone"):
            if asyncio.iscoroutinefunction(client.create_milestone):
                result = await client.create_milestone(payload)
            else:
                result = client.create_milestone(payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support milestones")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.create_milestone", target=f"{module_code}", details={"milestone": payload.get("title")})
        return {"data": result, "meta": {"module": module_code}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.patch("/{module_code}/milestones/{milestone_id}")
async def update_milestone(
    module_code: ModuleCode,
    milestone_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Update a milestone."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "update_milestone"):
            if asyncio.iscoroutinefunction(client.update_milestone):
                result = await client.update_milestone(milestone_id, payload)
            else:
                result = client.update_milestone(milestone_id, payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support milestones")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.update_milestone", target=f"{module_code}:{milestone_id}")
        return {"data": result, "meta": {"module": module_code, "milestone_id": milestone_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.delete("/{module_code}/milestones/{milestone_id}")
async def delete_milestone(
    module_code: ModuleCode,
    milestone_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Delete a milestone."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "delete_milestone"):
            if asyncio.iscoroutinefunction(client.delete_milestone):
                result = await client.delete_milestone(milestone_id)
            else:
                result = client.delete_milestone(milestone_id)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support milestones")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.delete_milestone", target=f"{module_code}:{milestone_id}")
        return {"data": result, "meta": {"module": module_code, "milestone_id": milestone_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


# ========== TASK LISTS ==========
@router.get("/{module_code}/task-lists")
async def list_task_lists(
    module_code: ModuleCode,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """List all task lists."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "list_task_lists"):
            if asyncio.iscoroutinefunction(client.list_task_lists):
                task_lists = await client.list_task_lists()
            else:
                task_lists = client.list_task_lists()
        else:
            task_lists = []
        return {"data": task_lists, "meta": {"module": module_code}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/task-lists")
async def create_task_list(
    module_code: ModuleCode,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Create a new task list."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "create_task_list"):
            if asyncio.iscoroutinefunction(client.create_task_list):
                result = await client.create_task_list(payload)
            else:
                result = client.create_task_list(payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support task lists")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.create_task_list", target=f"{module_code}")
        return {"data": result, "meta": {"module": module_code}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.patch("/{module_code}/task-lists/{task_list_id}")
async def update_task_list(
    module_code: ModuleCode,
    task_list_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Update a task list."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "update_task_list"):
            if asyncio.iscoroutinefunction(client.update_task_list):
                result = await client.update_task_list(task_list_id, payload)
            else:
                result = client.update_task_list(task_list_id, payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support task lists")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.update_task_list", target=f"{module_code}:{task_list_id}")
        return {"data": result, "meta": {"module": module_code, "task_list_id": task_list_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.delete("/{module_code}/task-lists/{task_list_id}")
async def delete_task_list(
    module_code: ModuleCode,
    task_list_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Delete a task list."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "delete_task_list"):
            if asyncio.iscoroutinefunction(client.delete_task_list):
                result = await client.delete_task_list(task_list_id)
            else:
                result = client.delete_task_list(task_list_id)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support task lists")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.delete_task_list", target=f"{module_code}:{task_list_id}")
        return {"data": result, "meta": {"module": module_code, "task_list_id": task_list_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


# ========== TIME TRACKER ==========
@router.get("/{module_code}/time-tracker")
async def list_time_trackers(
    module_code: ModuleCode,
    task_id: Optional[int] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """List time tracker entries."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "list_time_trackers"):
            if asyncio.iscoroutinefunction(client.list_time_trackers):
                entries = await client.list_time_trackers(task_id=task_id)
            else:
                entries = client.list_time_trackers(task_id=task_id)
        else:
            entries = []
        return {"data": entries, "meta": {"module": module_code, "task_id": task_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/time-tracker")
async def create_time_tracker(
    module_code: ModuleCode,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Create a new time tracker entry."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "create_time_tracker"):
            if asyncio.iscoroutinefunction(client.create_time_tracker):
                result = await client.create_time_tracker(payload)
            else:
                result = client.create_time_tracker(payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support time tracker")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.create_time_tracker", target=f"{module_code}")
        return {"data": result, "meta": {"module": module_code}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.patch("/{module_code}/time-tracker/{time_id}")
async def update_time_tracker(
    module_code: ModuleCode,
    time_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Update a time tracker entry."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "update_time_tracker"):
            if asyncio.iscoroutinefunction(client.update_time_tracker):
                result = await client.update_time_tracker(time_id, payload)
            else:
                result = client.update_time_tracker(time_id, payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support time tracker")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.update_time_tracker", target=f"{module_code}:{time_id}")
        return {"data": result, "meta": {"module": module_code, "time_id": time_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.delete("/{module_code}/time-tracker/{time_id}")
async def delete_time_tracker(
    module_code: ModuleCode,
    time_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Delete a time tracker entry."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "delete_time_tracker"):
            if asyncio.iscoroutinefunction(client.delete_time_tracker):
                result = await client.delete_time_tracker(time_id)
            else:
                result = client.delete_time_tracker(time_id)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support time tracker")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.delete_time_tracker", target=f"{module_code}:{time_id}")
        return {"data": result, "meta": {"module": module_code, "time_id": time_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.get("/{module_code}/tasks/{task_id}/time-entries")
async def list_task_time_entries(
    module_code: ModuleCode,
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """List time entries for a specific task."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "list_task_time_entries"):
            if asyncio.iscoroutinefunction(client.list_task_time_entries):
                entries = await client.list_task_time_entries(task_id)
            else:
                entries = client.list_task_time_entries(task_id)
        else:
            entries = []
        return {"data": entries, "meta": {"module": module_code, "task_id": task_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


# ========== TAGS ==========
@router.get("/{module_code}/tags")
async def list_tags(
    module_code: ModuleCode,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """List all tags."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "list_tags"):
            if asyncio.iscoroutinefunction(client.list_tags):
                tags = await client.list_tags()
            else:
                tags = client.list_tags()
        else:
            tags = []
        return {"data": tags, "meta": {"module": module_code}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/tags")
async def create_tag(
    module_code: ModuleCode,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Create a new tag."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "create_tag"):
            if asyncio.iscoroutinefunction(client.create_tag):
                result = await client.create_tag(payload)
            else:
                result = client.create_tag(payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support tags")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.create_tag", target=f"{module_code}")
        return {"data": result, "meta": {"module": module_code}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.patch("/{module_code}/tags/{tag_id}")
async def update_tag(
    module_code: ModuleCode,
    tag_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Update a tag."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "update_tag"):
            if asyncio.iscoroutinefunction(client.update_tag):
                result = await client.update_tag(tag_id, payload)
            else:
                result = client.update_tag(tag_id, payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support tags")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.update_tag", target=f"{module_code}:{tag_id}")
        return {"data": result, "meta": {"module": module_code, "tag_id": tag_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.delete("/{module_code}/tags/{tag_id}")
async def delete_tag(
    module_code: ModuleCode,
    tag_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Delete a tag."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "delete_tag"):
            if asyncio.iscoroutinefunction(client.delete_tag):
                result = await client.delete_tag(tag_id)
            else:
                result = client.delete_tag(tag_id)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support tags")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.delete_tag", target=f"{module_code}:{tag_id}")
        return {"data": result, "meta": {"module": module_code, "tag_id": tag_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


# ========== TASK-SPECIFIC FEATURES ==========
@router.get("/{module_code}/tasks/{task_id}/status-timelines")
async def get_status_timelines(
    module_code: ModuleCode,
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get status change timeline for a task."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "get_status_timelines"):
            if asyncio.iscoroutinefunction(client.get_status_timelines):
                timelines = await client.get_status_timelines(task_id)
            else:
                timelines = client.get_status_timelines(task_id)
        else:
            timelines = []
        return {"data": timelines, "meta": {"module": module_code, "task_id": task_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.patch("/{module_code}/tasks/{task_id}/favorite")
async def update_task_favorite(
    module_code: ModuleCode,
    task_id: int,
    is_favorite: bool,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Update task favorite status."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "update_task_favorite"):
            if asyncio.iscoroutinefunction(client.update_task_favorite):
                result = await client.update_task_favorite(task_id, is_favorite)
            else:
                result = client.update_task_favorite(task_id, is_favorite)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support favorites")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.update_task_favorite", target=f"{module_code}:{task_id}")
        return {"data": result, "meta": {"module": module_code, "task_id": task_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.patch("/{module_code}/tasks/{task_id}/pinned")
async def update_task_pinned(
    module_code: ModuleCode,
    task_id: int,
    is_pinned: bool,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Update task pinned status."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "update_task_pinned"):
            if asyncio.iscoroutinefunction(client.update_task_pinned):
                result = await client.update_task_pinned(task_id, is_pinned)
            else:
                result = client.update_task_pinned(task_id, is_pinned)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support pinned")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.update_task_pinned", target=f"{module_code}:{task_id}")
        return {"data": result, "meta": {"module": module_code, "task_id": task_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/tasks/{task_id}/media")
async def upload_task_media(
    module_code: ModuleCode,
    task_id: int,
    request: Request,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Upload media/file to a task."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        form = await request.form()
        file = form.get("file")
        if not file:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="No file provided")
        
        file_content = await file.read()
        filename = file.filename or "upload"
        
        if hasattr(client, "upload_task_media"):
            if asyncio.iscoroutinefunction(client.upload_task_media):
                result = await client.upload_task_media(task_id, "", file_content, filename)
            else:
                result = client.upload_task_media(task_id, "", file_content, filename)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support media upload")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.upload_task_media", target=f"{module_code}:{task_id}")
        return {"data": result, "meta": {"module": module_code, "task_id": task_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.delete("/{module_code}/tasks/media/{media_id}")
async def delete_task_media(
    module_code: ModuleCode,
    media_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Delete media from a task."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "delete_task_media"):
            if asyncio.iscoroutinefunction(client.delete_task_media):
                result = await client.delete_task_media(media_id)
            else:
                result = client.delete_task_media(media_id)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support media deletion")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.delete_task_media", target=f"{module_code}:{media_id}")
        return {"data": result, "meta": {"module": module_code, "media_id": media_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.get("/{module_code}/tasks/{task_id}/subtasks")
async def get_task_subtasks(
    module_code: ModuleCode,
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get subtasks/dependencies for a task."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "get_task_subtasks"):
            if asyncio.iscoroutinefunction(client.get_task_subtasks):
                subtasks = await client.get_task_subtasks(task_id)
            else:
                subtasks = client.get_task_subtasks(task_id)
        else:
            subtasks = []
        return {"data": subtasks, "meta": {"module": module_code, "task_id": task_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.get("/{module_code}/tasks/{task_id}/recurring")
async def get_recurring_task(
    module_code: ModuleCode,
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get recurring task configuration for a task."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "get_recurring_task"):
            if asyncio.iscoroutinefunction(client.get_recurring_task):
                recurring = await client.get_recurring_task(task_id)
            else:
                recurring = client.get_recurring_task(task_id)
        else:
            recurring = None
        return {"data": recurring, "meta": {"module": module_code, "task_id": task_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


# ========== BULK OPERATIONS ==========
@router.post("/{module_code}/tasks/bulk-delete")
async def bulk_delete_tasks(
    module_code: ModuleCode,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Bulk delete tasks."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        task_ids = payload.get("task_ids", [])
        if not task_ids:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="task_ids array is required")
        
        if hasattr(client, "bulk_delete_tasks"):
            if asyncio.iscoroutinefunction(client.bulk_delete_tasks):
                result = await client.bulk_delete_tasks(task_ids)
            else:
                result = client.bulk_delete_tasks(task_ids)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support bulk delete")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.bulk_delete_tasks", target=f"{module_code}", details={"count": len(task_ids)})
        return {"data": result, "meta": {"module": module_code, "deleted_count": len(task_ids)}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/tasks/{task_id}/duplicate")
async def duplicate_task(
    module_code: ModuleCode,
    task_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Duplicate a task."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "duplicate_task"):
            if asyncio.iscoroutinefunction(client.duplicate_task):
                result = await client.duplicate_task(task_id)
            else:
                result = client.duplicate_task(task_id)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support task duplication")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.duplicate_task", target=f"{module_code}:{task_id}")
        return {"data": result, "meta": {"module": module_code, "task_id": task_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


# ========== ACTIVITY LOG ==========
@router.get("/{module_code}/activity-log")
async def get_activity_log(
    module_code: ModuleCode,
    task_id: Optional[int] = None,
    limit: Optional[int] = None,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Get activity log."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "get_activity_log"):
            if asyncio.iscoroutinefunction(client.get_activity_log):
                log = await client.get_activity_log(task_id=task_id, limit=limit)
            else:
                log = client.get_activity_log(task_id=task_id, limit=limit)
        else:
            log = []
        return {"data": log, "meta": {"module": module_code, "task_id": task_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


# ========== CUSTOM FIELDS ==========
@router.get("/{module_code}/custom-fields")
async def list_custom_fields(
    module_code: ModuleCode,
    module: str = "task",
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """List custom fields for a module."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "list_custom_fields"):
            if asyncio.iscoroutinefunction(client.list_custom_fields):
                fields = await client.list_custom_fields(module=module)
            else:
                fields = client.list_custom_fields(module=module)
        else:
            fields = []
        return {"data": fields, "meta": {"module": module_code, "module_type": module}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.post("/{module_code}/custom-fields")
async def create_custom_field(
    module_code: ModuleCode,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Create a custom field."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "create_custom_field"):
            if asyncio.iscoroutinefunction(client.create_custom_field):
                result = await client.create_custom_field(payload)
            else:
                result = client.create_custom_field(payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support custom fields")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.create_custom_field", target=f"{module_code}")
        return {"data": result, "meta": {"module": module_code}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.patch("/{module_code}/custom-fields/{field_id}")
async def update_custom_field(
    module_code: ModuleCode,
    field_id: int,
    payload: dict,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Update a custom field."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "update_custom_field"):
            if asyncio.iscoroutinefunction(client.update_custom_field):
                result = await client.update_custom_field(field_id, payload)
            else:
                result = client.update_custom_field(field_id, payload)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support custom fields")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.update_custom_field", target=f"{module_code}:{field_id}")
        return {"data": result, "meta": {"module": module_code, "field_id": field_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()


@router.delete("/{module_code}/custom-fields/{field_id}")
async def delete_custom_field(
    module_code: ModuleCode,
    field_id: int,
    current_user: User = Depends(require_permission(PermissionCode.ACCESS_MODULES)),
) -> dict:
    """Delete a custom field."""
    tenant_id = str(current_user.tenant_id)
    await _require_entitlement(tenant_id, module_code)
    client = await _get_client_for(module_code, tenant_id, str(current_user.id))
    try:
        if hasattr(client, "delete_custom_field"):
            if asyncio.iscoroutinefunction(client.delete_custom_field):
                result = await client.delete_custom_field(field_id)
            else:
                result = client.delete_custom_field(field_id)
        else:
            raise HTTPException(status_code=status.HTTP_501_NOT_IMPLEMENTED, detail="Module does not support custom fields")
        await log_audit(tenant_id=tenant_id, actor_user_id=str(current_user.id), action="module.delete_custom_field", target=f"{module_code}:{field_id}")
        return {"data": result, "meta": {"module": module_code, "field_id": field_id}}
    finally:
        if hasattr(client, "close") and asyncio.iscoroutinefunction(client.close):
            await client.close()
        elif hasattr(client, "close"):
            client.close()
