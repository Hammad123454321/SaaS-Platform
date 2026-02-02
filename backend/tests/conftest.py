import pytest
from unittest.mock import Mock


@pytest.fixture
def mock_session():
    session = Mock()
    session.exec.return_value.all.return_value = []
    session.exec.return_value.first.return_value = None
    session.add = Mock()
    session.commit = Mock()
    session.get = Mock()
    return session


@pytest.fixture
def mock_project():
    project = Mock()
    project.id = 1
    project.name = "Project Alpha"
    project.tenant_id = "tenant-1"
    return project


@pytest.fixture
def mock_task():
    task = Mock()
    task.id = 1
    task.title = "Task A"
    task.tenant_id = "tenant-1"
    return task


@pytest.fixture
def mock_user():
    user = Mock()
    user.id = "user-1"
    user.tenant_id = "tenant-1"
    return user
