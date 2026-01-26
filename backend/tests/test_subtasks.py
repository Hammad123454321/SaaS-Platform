"""
Tests for Subtask operations.
"""
import pytest
from unittest.mock import Mock
from fastapi import HTTPException

from app.services.tasks_subtasks import (
    create_subtask,
    get_subtask,
    list_subtasks,
    update_subtask,
    delete_subtask,
)


def test_create_subtask(mock_session, mock_task, mock_task_status):
    """Test creating a subtask."""
    from app.models.tasks import Task
    
    subtask_data = {
        "title": "Subtask",
        "description": "Subtask description",
        "status_id": mock_task_status.id,
    }
    
    # Mock get_task to return parent
    def get_task_side_effect(session, tenant_id, task_id):
        if task_id == mock_task.id:
            return mock_task
        return None
    
    # Mock session for creating subtask
    created_subtask = Mock(spec=Task)
    created_subtask.id = 2
    created_subtask.parent_id = mock_task.id
    created_subtask.title = subtask_data["title"]
    
    mock_session.add = Mock()
    mock_session.commit = Mock()
    mock_session.refresh = Mock()
    
    # Import and patch get_task
    import app.services.tasks_subtasks as subtasks_module
    subtasks_module.get_task = Mock(side_effect=get_task_side_effect)
    
    # Mock the task creation
    def add_side_effect(obj):
        if isinstance(obj, Task):
            obj.id = 2
            obj.parent_id = mock_task.id
    
    mock_session.add.side_effect = add_side_effect
    
    result = create_subtask(
        mock_session,
        mock_task.tenant_id,
        mock_task.id,
        subtask_data,
        1
    )
    
    assert result is not None
    mock_session.add.assert_called()
    mock_session.commit.assert_called()


def test_list_subtasks(mock_session, mock_task):
    """Test listing subtasks."""
    from app.models.tasks import Task
    from sqlmodel import select
    
    subtask = Mock(spec=Task)
    subtask.id = 2
    subtask.parent_id = mock_task.id
    
    mock_session.exec.return_value.all.return_value = [subtask]
    
    result = list_subtasks(mock_session, mock_task.tenant_id, mock_task.id)
    
    assert len(result) == 1
    assert result[0].parent_id == mock_task.id


















