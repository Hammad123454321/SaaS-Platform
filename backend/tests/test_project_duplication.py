"""
Tests for Project and Task Duplication.
"""
import pytest
from unittest.mock import Mock, patch

from app.services.tasks_duplication import (
    duplicate_project,
    duplicate_task,
)


def test_duplicate_project(mock_session, mock_project, mock_task):
    """Test duplicating a project."""
    from app.models import Project, Task
    
    # Mock get project
    mock_session.get.return_value = mock_project
    
    # Mock create_project
    new_project = Mock(spec=Project)
    new_project.id = 2
    new_project.name = f"{mock_project.name} (Copy)"
    
    # Mock list tasks
    mock_session.exec.return_value.all.return_value = [mock_task]
    
    with patch("app.services.tasks_duplication.create_project") as mock_create_project:
        mock_create_project.return_value = new_project
        
        with patch("app.services.tasks_duplication.create_task") as mock_create_task:
            new_task = Mock(spec=Task)
            new_task.id = 2
            mock_create_task.return_value = new_task
            
            result = duplicate_project(
                mock_session,
                mock_project.tenant_id,
                mock_project.id,
                None,
                1
            )
            
            assert result is not None
            assert result.id == new_project.id


def test_duplicate_task(mock_session, mock_task):
    """Test duplicating a task."""
    from app.models.tasks import Task
    
    # Mock get_task
    def get_task_side_effect(session, tenant_id, task_id):
        if task_id == mock_task.id:
            return mock_task
        return None
    
    with patch("app.services.tasks_duplication.get_task") as mock_get_task:
        mock_get_task.side_effect = get_task_side_effect
        
        with patch("app.services.tasks_duplication.create_task") as mock_create_task:
            new_task = Mock(spec=Task)
            new_task.id = 2
            new_task.title = f"{mock_task.title} (Copy)"
            mock_create_task.return_value = new_task
            
            result = duplicate_task(
                mock_session,
                mock_task.tenant_id,
                mock_task.id,
                None,
                True,
                1
            )
            
            assert result is not None
            assert result.id == new_task.id

















