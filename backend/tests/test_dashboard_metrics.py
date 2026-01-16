"""
Tests for Dashboard Metrics.
"""
import pytest
from unittest.mock import Mock
from datetime import date, timedelta

from app.services.tasks_dashboard import (
    get_dashboard_metrics,
    get_employee_progress_overview,
)


def test_get_dashboard_metrics(mock_session, mock_task, mock_task_status):
    """Test getting dashboard metrics."""
    from app.models.tasks import TaskStatus, TaskStatusCategory
    from sqlmodel import select
    
    # Mock status category
    mock_task_status.category = TaskStatusCategory.TODO
    
    # Mock tasks query
    mock_session.exec.return_value.all.return_value = [mock_task]
    
    # Mock status query
    def exec_side_effect(query):
        if "TaskStatus" in str(query):
            status_mock = Mock()
            status_mock.id = mock_task_status.id
            status_mock.name = mock_task_status.name
            status_mock.category = TaskStatusCategory.DONE
            return Mock(all=lambda: [status_mock])
        return Mock(all=lambda: [mock_task])
    
    mock_session.exec.side_effect = exec_side_effect
    mock_session.get.return_value = mock_task_status
    
    result = get_dashboard_metrics(mock_session, mock_task.tenant_id)
    
    assert "summary" in result
    assert "status_breakdown" in result
    assert "priority_breakdown" in result
    assert result["summary"]["total_tasks"] >= 0


def test_get_employee_progress_overview(mock_session, mock_user):
    """Test getting employee progress overview."""
    from app.models import User
    from sqlmodel import select
    
    # Mock users query
    mock_session.exec.return_value.all.return_value = [mock_user]
    
    # Mock tasks query (empty for simplicity)
    def exec_side_effect(query):
        return Mock(all=lambda: [])
    
    mock_session.exec.side_effect = exec_side_effect
    
    result = get_employee_progress_overview(mock_session, mock_user.tenant_id)
    
    assert "employees" in result
    assert "total_employees" in result
    assert isinstance(result["employees"], list)















