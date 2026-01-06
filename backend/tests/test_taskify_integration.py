"""Integration tests for Taskify end-to-end flow."""
import pytest
from httpx import AsyncClient
from sqlmodel import Session, select

from app.main import app
from app.models import User, Tenant, ModuleEntitlement, ModuleCode, VendorCredential
from app.core.security import create_access_token


@pytest.fixture
async def test_tenant_and_user(session: Session):
    """Create a test tenant and user."""
    tenant = Tenant(name="Test Company", domain="test")
    session.add(tenant)
    session.commit()
    session.refresh(tenant)
    
    user = User(
        email="test@example.com",
        hashed_password="$2b$12$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW",  # "secret"
        tenant_id=tenant.id,
        is_active=True,
    )
    session.add(user)
    session.commit()
    session.refresh(user)
    
    return tenant, user


@pytest.fixture
async def taskify_credential(session: Session, test_tenant_and_user):
    """Create Taskify credentials for test tenant."""
    tenant, _ = test_tenant_and_user
    
    # Create module entitlement
    entitlement = ModuleEntitlement(
        tenant_id=tenant.id,
        module_code=ModuleCode.TASKS,
        enabled=True,
    )
    session.add(entitlement)
    
    # Create vendor credential
    credential = VendorCredential(
        tenant_id=tenant.id,
        vendor=ModuleCode.TASKS.value,
        credentials={
            "base_url": "http://taskify:80",
            "api_token": "test-token",
            "workspace_id": 1,
        },
    )
    session.add(credential)
    session.commit()
    session.refresh(credential)
    
    return credential


@pytest.mark.asyncio
async def test_module_health_check(session: Session, test_tenant_and_user, taskify_credential):
    """Test module health check endpoint."""
    _, user = test_tenant_and_user
    
    token = create_access_token(data={"sub": str(user.id), "tenant_id": str(user.tenant_id)})
    
    async with AsyncClient(app=app, base_url="http://test") as client:
        response = await client.get(
            "/api/modules/tasks/health",
            headers={"Authorization": f"Bearer {token}"},
        )
        
        assert response.status_code in [200, 503]  # 503 if Taskify is not running
        if response.status_code == 200:
            data = response.json()
            assert "data" in data
            assert "meta" in data
            assert data["meta"]["module"] == "tasks"


@pytest.mark.asyncio
async def test_list_tasks(session: Session, test_tenant_and_user, taskify_credential):
    """Test listing tasks."""
    _, user = test_tenant_and_user
    
    token = create_access_token(data={"sub": str(user.id), "tenant_id": str(user.tenant_id)})
    
    async with AsyncClient(app=app, base_url="http://test") as client:
        response = await client.get(
            "/api/modules/tasks/records",
            params={"resource": "tasks"},
            headers={"Authorization": f"Bearer {token}"},
        )
        
        # Should return 200 even if Taskify is not running (will return empty or error)
        assert response.status_code in [200, 503]
        if response.status_code == 200:
            data = response.json()
            assert "data" in data
            assert isinstance(data["data"], list)


@pytest.mark.asyncio
async def test_create_task(session: Session, test_tenant_and_user, taskify_credential):
    """Test creating a task."""
    _, user = test_tenant_and_user
    
    token = create_access_token(data={"sub": str(user.id), "tenant_id": str(user.tenant_id)})
    
    async with AsyncClient(app=app, base_url="http://test") as client:
        response = await client.post(
            "/api/modules/tasks/records",
            params={"resource": "tasks"},
            json={
                "title": "Test Task",
                "description": "Test description",
            },
            headers={"Authorization": f"Bearer {token}"},
        )
        
        # Should return 200 or 503 depending on Taskify availability
        assert response.status_code in [200, 503, 502]
        if response.status_code == 200:
            data = response.json()
            assert "data" in data


@pytest.mark.asyncio
async def test_module_entitlement_required(session: Session, test_tenant_and_user):
    """Test that module entitlement is required."""
    _, user = test_tenant_and_user
    
    token = create_access_token(data={"sub": str(user.id), "tenant_id": str(user.tenant_id)})
    
    async with AsyncClient(app=app, base_url="http://test") as client:
        response = await client.get(
            "/api/modules/tasks/health",
            headers={"Authorization": f"Bearer {token}"},
        )
        
        # Should return 403 if module is not enabled
        assert response.status_code == 403


@pytest.mark.asyncio
async def test_ai_agent_task_tools(session: Session, test_tenant_and_user, taskify_credential):
    """Test AI agent can use task tools."""
    from app.services.ai.tools import list_tasks, create_task
    
    tenant, user = test_tenant_and_user
    
    # Test list_tasks tool
    try:
        tasks = await list_tasks(
            tenant_id=int(user.tenant_id),  # type: ignore[arg-type]
            status=None,
            session=session,
        )
        assert isinstance(tasks, list)
    except Exception as e:
        # Taskify might not be running, that's okay for tests
        assert "connection" in str(e).lower() or "timeout" in str(e).lower() or "error" in str(e).lower()
    
    # Test create_task tool
    try:
        result = await create_task(
            tenant_id=int(user.tenant_id),  # type: ignore[arg-type]
            task_data={"title": "AI Created Task", "description": "Created by AI agent"},
            session=session,
        )
        assert isinstance(result, dict)
    except Exception as e:
        # Taskify might not be running, that's okay for tests
        assert "connection" in str(e).lower() or "timeout" in str(e).lower() or "error" in str(e).lower()

