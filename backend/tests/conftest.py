"""
Pytest configuration and fixtures for task management tests.
"""
import pytest
from unittest.mock import Mock, MagicMock
from sqlmodel import Session, create_engine
from sqlmodel.pool import StaticPool
from datetime import datetime, date
from decimal import Decimal

from app.models import User, Tenant
from app.models.tasks import (
    Task, Project, Client, TaskStatus, TaskPriority,
    TaskComment, TaskAttachment, TimeEntry, TimeTracker
)


@pytest.fixture
def mock_session():
    """Create a mock database session."""
    session = Mock(spec=Session)
    session.add = Mock()
    session.delete = Mock()
    session.commit = Mock()
    session.refresh = Mock()
    session.exec = Mock()
    session.get = Mock()
    return session


@pytest.fixture
def mock_tenant():
    """Create a mock tenant."""
    tenant = Mock(spec=Tenant)
    tenant.id = 1
    tenant.name = "Test Tenant"
    return tenant


@pytest.fixture
def mock_user(mock_tenant):
    """Create a mock user."""
    user = Mock(spec=User)
    user.id = 1
    user.email = "test@example.com"
    user.tenant_id = mock_tenant.id
    user.is_super_admin = False
    user.is_active = True
    user.roles = []
    return user


@pytest.fixture
def mock_task_status():
    """Create a mock task status."""
    status = Mock(spec=TaskStatus)
    status.id = 1
    status.name = "To Do"
    status.color = "#6b7280"
    status.tenant_id = 1
    return status


@pytest.fixture
def mock_task_priority():
    """Create a mock task priority."""
    priority = Mock(spec=TaskPriority)
    priority.id = 1
    priority.name = "High"
    priority.color = "#ef4444"
    priority.tenant_id = 1
    return priority


@pytest.fixture
def mock_client():
    """Create a mock client."""
    client = Mock(spec=Client)
    client.id = 1
    client.first_name = "John"
    client.last_name = "Doe"
    client.email = "john@example.com"
    client.tenant_id = 1
    return client


@pytest.fixture
def mock_project(mock_client):
    """Create a mock project."""
    project = Mock(spec=Project)
    project.id = 1
    project.name = "Test Project"
    project.client_id = mock_client.id
    project.tenant_id = 1
    return project


@pytest.fixture
def mock_task(mock_project, mock_task_status, mock_task_priority):
    """Create a mock task."""
    task = Mock(spec=Task)
    task.id = 1
    task.title = "Test Task"
    task.description = "Test Description"
    task.project_id = mock_project.id
    task.status_id = mock_task_status.id
    task.priority_id = mock_task_priority.id
    task.tenant_id = 1
    task.created_by = 1
    task.completion_percentage = 0
    task.due_date = date.today()
    task.start_date = date.today()
    task.project = mock_project
    task.status = mock_task_status
    task.priority = mock_task_priority
    task.assignees = []
    task.subtasks = []
    task.created_at = datetime.utcnow()
    task.updated_at = datetime.utcnow()
    return task















