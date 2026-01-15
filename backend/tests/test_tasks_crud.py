"""
Tests for Task CRUD operations.
"""
import pytest
from unittest.mock import Mock, MagicMock
from datetime import date
from fastapi import HTTPException

from app.services.tasks import (
    create_task,
    get_task,
    list_tasks,
    update_task,
    delete_task,
)


def test_create_task(mock_session, mock_user, mock_project, mock_task_status):
    """Test creating a task."""
    from app.models.tasks import Task
    
    task_data = {
        "title": "New Task",
        "description": "Task description",
        "project_id": mock_project.id,
        "status_id": mock_task_status.id,
        "due_date": date.today(),
    }
    
    # Mock session.exec for checking existing tasks
    mock_session.exec.return_value.first.return_value = None
    
    # Mock session.add to capture the task
    created_task = Mock(spec=Task)
    created_task.id = 1
    created_task.title = task_data["title"]
    created_task.project_id = task_data["project_id"]
    created_task.status_id = task_data["status_id"]
    created_task.tenant_id = mock_user.tenant_id
    created_task.created_by = mock_user.id
    
    def add_side_effect(obj):
        if isinstance(obj, Task):
            obj.id = 1
            obj.tenant_id = mock_user.tenant_id
            obj.created_by = mock_user.id
    
    mock_session.add.side_effect = add_side_effect
    
    # Mock refresh
    def refresh_side_effect(obj):
        pass
    
    mock_session.refresh.side_effect = refresh_side_effect
    
    # Execute
    result = create_task(mock_session, mock_user.tenant_id, mock_user.id, task_data)
    
    # Verify
    assert result is not None
    mock_session.add.assert_called()
    mock_session.commit.assert_called()


def test_get_task(mock_session, mock_task):
    """Test getting a task."""
    from app.models.tasks import Task
    
    mock_session.get.return_value = mock_task
    
    result = get_task(mock_session, mock_task.tenant_id, mock_task.id)
    
    assert result is not None
    assert result.id == mock_task.id
    mock_session.get.assert_called()


def test_get_task_not_found(mock_session):
    """Test getting a non-existent task."""
    mock_session.get.return_value = None
    
    result = get_task(mock_session, 1, 999)
    
    assert result is None


def test_list_tasks(mock_session, mock_task):
    """Test listing tasks."""
    from app.models.tasks import Task
    from sqlmodel import select
    
    mock_session.exec.return_value.all.return_value = [mock_task]
    
    result = list_tasks(mock_session, mock_task.tenant_id)
    
    assert len(result) == 1
    assert result[0].id == mock_task.id
    mock_session.exec.assert_called()


def test_update_task(mock_session, mock_task):
    """Test updating a task."""
    mock_session.get.return_value = mock_task
    
    updates = {"title": "Updated Task", "completion_percentage": 50}
    
    result = update_task(mock_session, mock_task.tenant_id, mock_task.id, updates)
    
    assert result is not None
    mock_session.add.assert_called()
    mock_session.commit.assert_called()


def test_delete_task(mock_session, mock_task):
    """Test deleting a task."""
    mock_session.get.return_value = mock_task
    
    delete_task(mock_session, mock_task.tenant_id, mock_task.id)
    
    mock_session.delete.assert_called_with(mock_task)
    mock_session.commit.assert_called()


def test_delete_task_not_found(mock_session):
    """Test deleting a non-existent task."""
    mock_session.get.return_value = None
    
    with pytest.raises(HTTPException) as exc_info:
        delete_task(mock_session, 1, 999)
    
    assert exc_info.value.status_code == 404

