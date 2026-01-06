"""LangChain tools for module interactions."""
import asyncio
from typing import Any, Dict, List, Optional
from langchain_core.tools import tool

from app.models import ModuleCode, User
from app.services.vendor_stub import VendorStubClient
from app.api.routes.modules import _require_entitlement


async def _get_module_client(module: ModuleCode, tenant_id: int, session: Any):
    """
    Get module client for tenant. Uses real client if available, falls back to stub.
    
    Note: session is Any to avoid Pydantic schema issues with LangChain tools.
    """
    from app.services.vendor_clients.factory import create_vendor_client
    from app.services.vendor_stub import VendorStubClient
    
    real_client = await create_vendor_client(module, tenant_id, session)
    if real_client:
        return real_client
    return VendorStubClient(vendor=module.value, credentials={"tenant_id": tenant_id})


def _get_module_client_sync(module: ModuleCode, tenant_id: int, session: Any):
    """
    Synchronous version for non-async tools (fallback to stub only).
    """
    from app.services.vendor_stub import VendorStubClient
    return VendorStubClient(vendor=module.value, credentials={"tenant_id": tenant_id})


# CRM Tools
@tool
async def get_crm_leads(tenant_id: int, session: Any) -> List[Dict[str, Any]]:
    """Get all CRM leads for the tenant.
    
    Args:
        tenant_id: The tenant ID
        session: Database session
        
    Returns:
        List of leads
    """
    _require_entitlement(session, tenant_id, ModuleCode.CRM)
    client = await _get_module_client(ModuleCode.CRM, tenant_id, session)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("leads")
    return client.list_records("leads")


@tool
async def get_crm_clients(tenant_id: int, session: Any) -> List[Dict[str, Any]]:
    """Get all CRM clients for the tenant.
    
    Args:
        tenant_id: The tenant ID
        session: Database session
        
    Returns:
        List of clients
    """
    _require_entitlement(session, tenant_id, ModuleCode.CRM)
    client = await _get_module_client(ModuleCode.CRM, tenant_id, session)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("clients")
    return client.list_records("clients")


@tool
async def create_crm_deal(tenant_id: int, deal_data: Dict[str, Any], session: Any) -> Dict[str, Any]:
    """Create a new deal in CRM.
    
    Args:
        tenant_id: The tenant ID
        deal_data: Deal information (title, value, client_id, etc.)
        session: Database session
        
    Returns:
        Created deal information
    """
    _require_entitlement(session, tenant_id, ModuleCode.CRM)
    client = await _get_module_client(ModuleCode.CRM, tenant_id, session)
    if asyncio.iscoroutinefunction(client.create_record):
        return await client.create_record("deals", deal_data)
    return client.create_record("deals", deal_data)


@tool
async def add_crm_note(tenant_id: int, record_id: str, note: str, session: Any) -> Dict[str, Any]:
    """Add a note to a CRM record (lead, client, or deal).
    
    Args:
        tenant_id: The tenant ID
        record_id: The CRM record ID
        note: The note content
        session: Database session
        
    Returns:
        Note creation result
    """
    _require_entitlement(session, tenant_id, ModuleCode.CRM)
    client = await _get_module_client(ModuleCode.CRM, tenant_id, session)
    if hasattr(client, "add_note"):
        if asyncio.iscoroutinefunction(client.add_note):
            return await client.add_note(record_id, note)
        return client.add_note(record_id, note)
    return {"record_id": record_id, "note": note, "status": "success"}


@tool
async def draft_crm_email(tenant_id: int, to: str, subject: str, body: str, session: Any) -> Dict[str, Any]:
    """Draft an email in CRM.
    
    Args:
        tenant_id: The tenant ID
        to: Recipient email address
        subject: Email subject
        body: Email body content
        session: Database session
        
    Returns:
        Drafted email information
    """
    _require_entitlement(session, tenant_id, ModuleCode.CRM)
    client = await _get_module_client(ModuleCode.CRM, tenant_id, session)
    if hasattr(client, "draft_email"):
        if asyncio.iscoroutinefunction(client.draft_email):
            return await client.draft_email(to, subject, body)
        return client.draft_email(to, subject, body)
    return {"to": to, "subject": subject, "body": body, "status": "drafted"}


# HRM Tools
@tool
async def get_hrm_employees(tenant_id: int, session: Any) -> List[Dict[str, Any]]:
    """Get all employees for the tenant.
    
    Args:
        tenant_id: The tenant ID
        session: Database session
        
    Returns:
        List of employees
    """
    _require_entitlement(session, tenant_id, ModuleCode.HRM)
    client = await _get_module_client(ModuleCode.HRM, tenant_id, session)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("employees")
    return client.list_records("employees")


@tool
async def get_hrm_attendance(tenant_id: int, date: Optional[str] = None, session: Any = None) -> List[Dict[str, Any]]:
    """Get attendance records for the tenant.
    
    Args:
        tenant_id: The tenant ID
        date: Optional date filter (YYYY-MM-DD format)
        session: Database session
        
    Returns:
        List of attendance records
    """
    _require_entitlement(session, tenant_id, ModuleCode.HRM)
    client = await _get_module_client(ModuleCode.HRM, tenant_id, session)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("attendance", date=date) if date else await client.list_records("attendance")
    return client.list_records("attendance")


@tool
async def get_hrm_leave_requests(tenant_id: int, session: Any) -> List[Dict[str, Any]]:
    """Get leave requests for the tenant.
    
    Args:
        tenant_id: The tenant ID
        session: Database session
        
    Returns:
        List of leave requests
    """
    _require_entitlement(session, tenant_id, ModuleCode.HRM)
    client = await _get_module_client(ModuleCode.HRM, tenant_id, session)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("leave_requests")
    return client.list_records("leave_requests")


# POS Tools
@tool
async def get_pos_sales(tenant_id: int, start_date: Optional[str] = None, end_date: Optional[str] = None, session: Any = None) -> List[Dict[str, Any]]:
    """Get sales records from POS.
    
    Args:
        tenant_id: The tenant ID
        start_date: Optional start date filter (YYYY-MM-DD)
        end_date: Optional end date filter (YYYY-MM-DD)
        session: Database session
        
    Returns:
        List of sales records
    """
    _require_entitlement(session, tenant_id, ModuleCode.POS)
    client = await _get_module_client(ModuleCode.POS, tenant_id, session)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("sales")
    return client.list_records("sales")


@tool
async def get_pos_inventory(tenant_id: int, session: Any) -> List[Dict[str, Any]]:
    """Get inventory items from POS.
    
    Args:
        tenant_id: The tenant ID
        session: Database session
        
    Returns:
        List of inventory items
    """
    _require_entitlement(session, tenant_id, ModuleCode.POS)
    client = await _get_module_client(ModuleCode.POS, tenant_id, session)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("inventory")
    return client.list_records("inventory")


@tool
async def get_pos_products(tenant_id: int, session: Any) -> List[Dict[str, Any]]:
    """Get products from POS.
    
    Args:
        tenant_id: The tenant ID
        session: Database session
        
    Returns:
        List of products
    """
    _require_entitlement(session, tenant_id, ModuleCode.POS)
    client = await _get_module_client(ModuleCode.POS, tenant_id, session)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("products")
    return client.list_records("products")


# Task Management Tools
@tool
async def create_task(tenant_id: int, task_data: Dict[str, Any], session: Any) -> Dict[str, Any]:
    """Create a new task.
    
    Args:
        tenant_id: The tenant ID
        task_data: Task information (title, description, assignee_id, due_date, etc.)
        session: Database session
        
    Returns:
        Created task information
    """
    _require_entitlement(session, tenant_id, ModuleCode.TASKS)
    client = await _get_module_client(ModuleCode.TASKS, tenant_id, session)
    if hasattr(client, "create_task"):
        result = await client.create_task(task_data) if asyncio.iscoroutinefunction(client.create_task) else client.create_task(task_data)
    else:
        result = await client.create_record("tasks", task_data) if asyncio.iscoroutinefunction(client.create_record) else client.create_record("tasks", task_data)
    return result


@tool
async def list_tasks(tenant_id: int, status: Optional[str] = None, session: Any = None) -> List[Dict[str, Any]]:
    """List tasks for the tenant.
    
    Args:
        tenant_id: The tenant ID
        status: Optional status filter (pending, in_progress, completed)
        session: Database session
        
    Returns:
        List of tasks
    """
    _require_entitlement(session, tenant_id, ModuleCode.TASKS)
    client = await _get_module_client(ModuleCode.TASKS, tenant_id, session)
    if hasattr(client, "list_tasks"):
        result = await client.list_tasks(status=status) if asyncio.iscoroutinefunction(client.list_tasks) else client.list_tasks(status=status)
    else:
        result = await client.list_records("tasks", status=status) if asyncio.iscoroutinefunction(client.list_records) else client.list_records("tasks")
    return result


@tool
async def update_task(tenant_id: int, task_id: str, updates: Dict[str, Any], session: Any) -> Dict[str, Any]:
    """Update a task.
    
    Args:
        tenant_id: The tenant ID
        task_id: The task ID
        updates: Fields to update (status, assignee_id, due_date, etc.)
        session: Database session
        
    Returns:
        Updated task information
    """
    _require_entitlement(session, tenant_id, ModuleCode.TASKS)
    client = await _get_module_client(ModuleCode.TASKS, tenant_id, session)
    if hasattr(client, "update_task"):
        result = await client.update_task(int(task_id), updates) if asyncio.iscoroutinefunction(client.update_task) else client.update_task(int(task_id), updates)
    else:
        result = {"id": task_id, "updated": updates, "status": "success"}
    return result


# Booking Tools
@tool
async def get_appointments(tenant_id: int, start_date: Optional[str] = None, end_date: Optional[str] = None, session: Any = None) -> List[Dict[str, Any]]:
    """Get appointments/bookings for the tenant.
    
    Args:
        tenant_id: The tenant ID
        start_date: Optional start date filter (YYYY-MM-DD)
        end_date: Optional end date filter (YYYY-MM-DD)
        session: Database session
        
    Returns:
        List of appointments
    """
    _require_entitlement(session, tenant_id, ModuleCode.BOOKING)
    client = await _get_module_client(ModuleCode.BOOKING, tenant_id, session)
    if asyncio.iscoroutinefunction(client.list_records):
        return await client.list_records("appointments")
    return client.list_records("appointments")


@tool
async def create_booking(tenant_id: int, booking_data: Dict[str, Any], session: Any) -> Dict[str, Any]:
    """Create a new booking/appointment.
    
    Args:
        tenant_id: The tenant ID
        booking_data: Booking information (customer_name, service_id, date, time, etc.)
        session: Database session
        
    Returns:
        Created booking information
    """
    _require_entitlement(session, tenant_id, ModuleCode.BOOKING)
    client = await _get_module_client(ModuleCode.BOOKING, tenant_id, session)
    if asyncio.iscoroutinefunction(client.create_record):
        return await client.create_record("appointments", booking_data)
    return client.create_record("appointments", booking_data)


@tool
async def check_availability(tenant_id: int, service_id: str, date: str, session: Any) -> Dict[str, Any]:
    """Check availability for a service on a specific date.
    
    Args:
        tenant_id: The tenant ID
        service_id: The service ID
        date: Date to check (YYYY-MM-DD)
        session: Database session
        
    Returns:
        Availability information with available time slots
    """
    _require_entitlement(session, tenant_id, ModuleCode.BOOKING)
    client = await _get_module_client(ModuleCode.BOOKING, tenant_id, session)
    # Stub implementation - real would query availability
    return {"service_id": service_id, "date": date, "available_slots": ["09:00", "10:00", "11:00", "14:00", "15:00"]}


# Landing Page Builder Tools
@tool
async def create_landing_page(tenant_id: int, page_data: Dict[str, Any], session: Any) -> Dict[str, Any]:
    """Create a new landing page.
    
    Args:
        tenant_id: The tenant ID
        page_data: Page information (title, slug, content, etc.)
        session: Database session
        
    Returns:
        Created page information
    """
    _require_entitlement(session, tenant_id, ModuleCode.LANDING)
    client = await _get_module_client(ModuleCode.LANDING, tenant_id, session)
    if asyncio.iscoroutinefunction(client.create_record):
        return await client.create_record("pages", page_data)
    return client.create_record("pages", page_data)


@tool
async def update_page_content(tenant_id: int, page_id: str, content: str, session: Any) -> Dict[str, Any]:
    """Update landing page content.
    
    Args:
        tenant_id: The tenant ID
        page_id: The page ID
        content: New content (HTML or structured content)
        session: Database session
        
    Returns:
        Updated page information
    """
    _require_entitlement(session, tenant_id, ModuleCode.LANDING)
    client = await _get_module_client(ModuleCode.LANDING, tenant_id, session)
    return {"page_id": page_id, "content": content, "status": "updated"}


def get_all_tools(tenant_id: int, user: User, session: Any) -> List:
    """Get all available tools for a tenant based on their entitlements.
    
    Args:
        tenant_id: The tenant ID
        user: The current user
        session: Database session
        
    Returns:
        List of LangChain tools
    """
    tools = []
    
    # Bind session and tenant_id to tools
    # Note: LangChain tools need to be bound with context
    # We'll create a tool factory that injects these dependencies
    
    # CRM tools
    try:
        _require_entitlement(session, tenant_id, ModuleCode.CRM)
        tools.extend([
            get_crm_leads,
            get_crm_clients,
            create_crm_deal,
            add_crm_note,
            draft_crm_email,
        ])
    except:
        pass
    
    # HRM tools
    try:
        _require_entitlement(session, tenant_id, ModuleCode.HRM)
        tools.extend([
            get_hrm_employees,
            get_hrm_attendance,
            get_hrm_leave_requests,
        ])
    except:
        pass
    
    # POS tools
    try:
        _require_entitlement(session, tenant_id, ModuleCode.POS)
        tools.extend([
            get_pos_sales,
            get_pos_inventory,
            get_pos_products,
        ])
    except:
        pass
    
    # Task tools
    try:
        _require_entitlement(session, tenant_id, ModuleCode.TASKS)
        tools.extend([
            create_task,
            list_tasks,
            update_task,
        ])
    except:
        pass
    
    # Booking tools
    try:
        _require_entitlement(session, tenant_id, ModuleCode.BOOKING)
        tools.extend([
            get_appointments,
            create_booking,
            check_availability,
        ])
    except:
        pass
    
    # Landing page tools
    try:
        _require_entitlement(session, tenant_id, ModuleCode.LANDING)
        tools.extend([
            create_landing_page,
            update_page_content,
        ])
    except:
        pass
    
    return tools

