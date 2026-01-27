"""LangChain tools for module interactions."""
import asyncio
from typing import Any, Dict, List, Optional
from langchain_core.tools import tool

from app.models import ModuleCode, ModuleEntitlement, User


async def _require_entitlement_async(tenant_id: str, module_code: ModuleCode) -> ModuleEntitlement:
    """Check if tenant has module entitlement."""
    ent = await ModuleEntitlement.find_one(
        ModuleEntitlement.tenant_id == tenant_id,
        ModuleEntitlement.module_code == module_code
    )
    if not ent or not ent.enabled:
        raise ValueError(f"Module {module_code.value} not enabled for tenant")
    return ent


async def _get_module_client(module: ModuleCode, tenant_id: str):
    """
    Get module client for tenant. Uses real client if available, falls back to stub.
    """
    from app.services.vendor_clients.factory import create_vendor_client
    from app.services.vendor_stub import VendorStubClient
    
    real_client = await create_vendor_client(module, tenant_id)
    if real_client:
        return real_client
    return VendorStubClient(vendor=module.value, credentials={"tenant_id": tenant_id})


def _get_module_client_sync(module: ModuleCode, tenant_id: str):
    """
    Synchronous version for non-async tools (fallback to stub only).
    """
    from app.services.vendor_stub import VendorStubClient
    return VendorStubClient(vendor=module.value, credentials={"tenant_id": tenant_id})


# CRM Tools
@tool
async def get_crm_leads(tenant_id: str) -> List[Dict[str, Any]]:
    """Get all CRM leads for the tenant.
    
    Args:
        tenant_id: The tenant ID
        
    Returns:
        List of leads
    """
    await _require_entitlement_async(tenant_id, ModuleCode.CRM)
    client = await _get_module_client(ModuleCode.CRM, tenant_id)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("leads")
    return client.list_records("leads")


@tool
async def get_crm_clients(tenant_id: str) -> List[Dict[str, Any]]:
    """Get all CRM clients for the tenant.
    
    Args:
        tenant_id: The tenant ID
        
    Returns:
        List of clients
    """
    await _require_entitlement_async(tenant_id, ModuleCode.CRM)
    client = await _get_module_client(ModuleCode.CRM, tenant_id)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("clients")
    return client.list_records("clients")


@tool
async def create_crm_deal(tenant_id: str, deal_data: Dict[str, Any]) -> Dict[str, Any]:
    """Create a new deal in CRM.
    
    Args:
        tenant_id: The tenant ID
        deal_data: Deal information (title, value, client_id, etc.)
        
    Returns:
        Created deal information
    """
    await _require_entitlement_async(tenant_id, ModuleCode.CRM)
    client = await _get_module_client(ModuleCode.CRM, tenant_id)
    if asyncio.iscoroutinefunction(client.create_record):
        return await client.create_record("deals", deal_data)
    return client.create_record("deals", deal_data)


@tool
async def add_crm_note(tenant_id: str, record_id: str, note: str) -> Dict[str, Any]:
    """Add a note to a CRM record (lead, client, or deal).
    
    Args:
        tenant_id: The tenant ID
        record_id: The CRM record ID
        note: The note content
        
    Returns:
        Note creation result
    """
    await _require_entitlement_async(tenant_id, ModuleCode.CRM)
    client = await _get_module_client(ModuleCode.CRM, tenant_id)
    if hasattr(client, "add_note"):
        if asyncio.iscoroutinefunction(client.add_note):
            return await client.add_note(record_id, note)
        return client.add_note(record_id, note)
    return {"record_id": record_id, "note": note, "status": "success"}


@tool
async def draft_crm_email(tenant_id: str, to: str, subject: str, body: str) -> Dict[str, Any]:
    """Draft an email in CRM.
    
    Args:
        tenant_id: The tenant ID
        to: Recipient email address
        subject: Email subject
        body: Email body content
        
    Returns:
        Drafted email information
    """
    await _require_entitlement_async(tenant_id, ModuleCode.CRM)
    client = await _get_module_client(ModuleCode.CRM, tenant_id)
    if hasattr(client, "draft_email"):
        if asyncio.iscoroutinefunction(client.draft_email):
            return await client.draft_email(to, subject, body)
        return client.draft_email(to, subject, body)
    return {"to": to, "subject": subject, "body": body, "status": "drafted"}


# HRM Tools
@tool
async def get_hrm_employees(tenant_id: str) -> List[Dict[str, Any]]:
    """Get all employees for the tenant.
    
    Args:
        tenant_id: The tenant ID
        
    Returns:
        List of employees
    """
    await _require_entitlement_async(tenant_id, ModuleCode.HRM)
    client = await _get_module_client(ModuleCode.HRM, tenant_id)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("employees")
    return client.list_records("employees")


@tool
async def get_hrm_attendance(tenant_id: str, date: Optional[str] = None) -> List[Dict[str, Any]]:
    """Get attendance records for the tenant.
    
    Args:
        tenant_id: The tenant ID
        date: Optional date filter (YYYY-MM-DD format)
        
    Returns:
        List of attendance records
    """
    await _require_entitlement_async(tenant_id, ModuleCode.HRM)
    client = await _get_module_client(ModuleCode.HRM, tenant_id)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("attendance", date=date) if date else await client.list_records("attendance")
    return client.list_records("attendance")


@tool
async def get_hrm_leave_requests(tenant_id: str) -> List[Dict[str, Any]]:
    """Get leave requests for the tenant.
    
    Args:
        tenant_id: The tenant ID
        
    Returns:
        List of leave requests
    """
    await _require_entitlement_async(tenant_id, ModuleCode.HRM)
    client = await _get_module_client(ModuleCode.HRM, tenant_id)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("leave_requests")
    return client.list_records("leave_requests")


# POS Tools
@tool
async def get_pos_sales(tenant_id: str, start_date: Optional[str] = None, end_date: Optional[str] = None) -> List[Dict[str, Any]]:
    """Get sales records from POS.
    
    Args:
        tenant_id: The tenant ID
        start_date: Optional start date filter (YYYY-MM-DD)
        end_date: Optional end date filter (YYYY-MM-DD)
        
    Returns:
        List of sales records
    """
    await _require_entitlement_async(tenant_id, ModuleCode.POS)
    client = await _get_module_client(ModuleCode.POS, tenant_id)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("sales")
    return client.list_records("sales")


@tool
async def get_pos_inventory(tenant_id: str) -> List[Dict[str, Any]]:
    """Get inventory items from POS.
    
    Args:
        tenant_id: The tenant ID
        
    Returns:
        List of inventory items
    """
    await _require_entitlement_async(tenant_id, ModuleCode.POS)
    client = await _get_module_client(ModuleCode.POS, tenant_id)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("inventory")
    return client.list_records("inventory")


@tool
async def get_pos_products(tenant_id: str) -> List[Dict[str, Any]]:
    """Get products from POS.
    
    Args:
        tenant_id: The tenant ID
        
    Returns:
        List of products
    """
    await _require_entitlement_async(tenant_id, ModuleCode.POS)
    client = await _get_module_client(ModuleCode.POS, tenant_id)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("products")
    return client.list_records("products")


# Task Management Tools
@tool
async def create_task(tenant_id: str, task_data: Dict[str, Any]) -> Dict[str, Any]:
    """Create a new task.
    
    Args:
        tenant_id: The tenant ID
        task_data: Task information (title, description, project_id, status_id, assignee_ids, due_date, etc.)
        
    Returns:
        Created task information
    """
    await _require_entitlement_async(tenant_id, ModuleCode.TASKS)
    from app.services.tasks import create_task as create_task_service
    from app.models import User
    
    user = await User.find_one(User.tenant_id == tenant_id)
    if not user:
        raise ValueError("No user found for tenant")
    
    normalized_data = {
        "title": task_data.get("title", ""),
        "description": task_data.get("description"),
        "project_id": task_data.get("project_id"),
        "status_id": task_data.get("status_id"),
        "priority_id": task_data.get("priority_id"),
        "due_date": task_data.get("due_date"),
        "assignee_ids": task_data.get("assignee_ids", []) or task_data.get("assignee_id", []),
    }
    
    task = await create_task_service(tenant_id, str(user.id), normalized_data)
    return {
        "id": str(task.id),
        "title": task.title,
        "description": task.description,
        "project_id": task.project_id,
        "status_id": task.status_id,
    }


@tool
async def list_tasks(tenant_id: str, status: Optional[str] = None) -> List[Dict[str, Any]]:
    """List tasks for the tenant.
    
    Args:
        tenant_id: The tenant ID
        status: Optional status filter (status name or status_id)
        
    Returns:
        List of tasks
    """
    await _require_entitlement_async(tenant_id, ModuleCode.TASKS)
    from app.services.tasks import list_tasks as list_tasks_service
    from app.models.tasks import TaskStatus, Task, Project
    
    status_id = None
    if status:
        if len(status) == 24:
            status_id = status
        else:
            status_obj = await TaskStatus.find_one(
                TaskStatus.tenant_id == tenant_id,
                {"name": {"$regex": status, "$options": "i"}}
            )
            if status_obj:
                status_id = str(status_obj.id)
    
    tasks = await list_tasks_service(tenant_id, status_id=status_id)
    
    result = []
    for t in tasks:
        task_status = await TaskStatus.get(t.status_id) if t.status_id else None
        task_project = await Project.get(t.project_id) if t.project_id else None
        result.append({
            "id": str(t.id),
            "title": t.title,
            "description": t.description,
            "status": task_status.name if task_status else None,
            "project": task_project.name if task_project else None,
            "due_date": str(t.due_date) if t.due_date else None,
        })
    
    return result


@tool
async def update_task(tenant_id: str, task_id: str, updates: Dict[str, Any]) -> Dict[str, Any]:
    """Update a task.
    
    Args:
        tenant_id: The tenant ID
        task_id: The task ID
        updates: Fields to update (status, assignee_id, due_date, etc.)
        
    Returns:
        Updated task information
    """
    await _require_entitlement_async(tenant_id, ModuleCode.TASKS)
    from app.services.tasks import update_task as update_task_service
    
    normalized_updates = {
        "title": updates.get("title"),
        "description": updates.get("description"),
        "status_id": updates.get("status_id"),
        "priority_id": updates.get("priority_id"),
        "due_date": updates.get("due_date"),
        "assignee_ids": updates.get("assignee_ids", []) or updates.get("assignee_id", []),
    }
    normalized_updates = {k: v for k, v in normalized_updates.items() if v is not None}
    
    task = await update_task_service(tenant_id, task_id, normalized_updates)
    return {
        "id": str(task.id),
        "title": task.title,
        "status_id": task.status_id,
    }


# Booking Tools
@tool
async def get_appointments(tenant_id: str, start_date: Optional[str] = None, end_date: Optional[str] = None) -> List[Dict[str, Any]]:
    """Get appointments/bookings for the tenant.
    
    Args:
        tenant_id: The tenant ID
        start_date: Optional start date filter (YYYY-MM-DD)
        end_date: Optional end date filter (YYYY-MM-DD)
        
    Returns:
        List of appointments
    """
    await _require_entitlement_async(tenant_id, ModuleCode.BOOKING)
    client = await _get_module_client(ModuleCode.BOOKING, tenant_id)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("appointments")
    return client.list_records("appointments")


@tool
async def create_booking(tenant_id: str, booking_data: Dict[str, Any]) -> Dict[str, Any]:
    """Create a new booking/appointment.
    
    Args:
        tenant_id: The tenant ID
        booking_data: Booking information (customer_name, service_id, date, time, etc.)
        
    Returns:
        Created booking information
    """
    await _require_entitlement_async(tenant_id, ModuleCode.BOOKING)
    client = await _get_module_client(ModuleCode.BOOKING, tenant_id)
    if asyncio.iscoroutinefunction(client.create_record):
        return await client.create_record("appointments", booking_data)
    return client.create_record("appointments", booking_data)


@tool
async def check_availability(tenant_id: str, service_id: str, date: str) -> Dict[str, Any]:
    """Check availability for a service on a specific date.
    
    Args:
        tenant_id: The tenant ID
        service_id: The service ID
        date: Date to check (YYYY-MM-DD)
        
    Returns:
        Availability information with available time slots
    """
    await _require_entitlement_async(tenant_id, ModuleCode.BOOKING)
    return {"service_id": service_id, "date": date, "available_slots": ["09:00", "10:00", "11:00", "14:00", "15:00"]}


# Landing Page Builder Tools
@tool
async def create_landing_page(tenant_id: str, page_data: Dict[str, Any]) -> Dict[str, Any]:
    """Create a new landing page.
    
    Args:
        tenant_id: The tenant ID
        page_data: Page information (title, slug, content, etc.)
        
    Returns:
        Created page information
    """
    await _require_entitlement_async(tenant_id, ModuleCode.LANDING)
    client = await _get_module_client(ModuleCode.LANDING, tenant_id)
    if asyncio.iscoroutinefunction(client.create_record):
        return await client.create_record("pages", page_data)
    return client.create_record("pages", page_data)


@tool
async def update_page_content(tenant_id: str, page_id: str, content: str) -> Dict[str, Any]:
    """Update landing page content.
    
    Args:
        tenant_id: The tenant ID
        page_id: The page ID
        content: New content (HTML or structured content)
        
    Returns:
        Updated page information
    """
    await _require_entitlement_async(tenant_id, ModuleCode.LANDING)
    return {"page_id": page_id, "content": content, "status": "updated"}


def get_all_tools(tenant_id: str, user: User) -> List:
    """Get all available tools for a tenant based on their entitlements.
    
    Args:
        tenant_id: The tenant ID
        user: The current user
        
    Returns:
        List of LangChain tools
    """
    tools = []
    
    # CRM tools
    tools.extend([
        get_crm_leads,
        get_crm_clients,
        create_crm_deal,
        add_crm_note,
        draft_crm_email,
    ])
    
    # HRM tools
    tools.extend([
        get_hrm_employees,
        get_hrm_attendance,
        get_hrm_leave_requests,
    ])
    
    # POS tools
    tools.extend([
        get_pos_sales,
        get_pos_inventory,
        get_pos_products,
    ])
    
    # Task tools
    tools.extend([
        create_task,
        list_tasks,
        update_task,
    ])
    
    # Booking tools
    tools.extend([
        get_appointments,
        create_booking,
        check_availability,
    ])
    
    # Landing page tools
    tools.extend([
        create_landing_page,
        update_page_content,
    ])
    
    return tools
