"""
Tests for Time Tracking operations.
"""
import pytest
from unittest.mock import Mock
from datetime import date, datetime
from decimal import Decimal
from fastapi import HTTPException

from app.services.tasks_time import (
    create_time_entry,
    list_time_entries,
    start_time_tracker,
    stop_time_tracker,
    get_time_report,
)


def test_create_time_entry(mock_session, mock_task, mock_user):
    """Test creating a time entry."""
    from app.models.tasks import TimeEntry
    
    time_data = {
        "hours": Decimal("2.5"),
        "date": date.today(),
        "description": "Worked on task",
        "is_billable": True,
    }
    
    mock_session.get.return_value = mock_task
    
    created_entry = Mock(spec=TimeEntry)
    created_entry.id = 1
    created_entry.hours = time_data["hours"]
    
    def add_side_effect(obj):
        if isinstance(obj, TimeEntry):
            obj.id = 1
    
    mock_session.add.side_effect = add_side_effect
    
    result = create_time_entry(
        mock_session,
        mock_task.tenant_id,
        mock_task.id,
        mock_user.id,
        time_data
    )
    
    assert result is not None
    mock_session.add.assert_called()
    mock_session.commit.assert_called()


def test_start_time_tracker(mock_session, mock_user, mock_task):
    """Test starting a time tracker."""
    from app.models.tasks import TimeTracker
    
    # Mock no active tracker
    mock_session.exec.return_value.first.return_value = None
    mock_session.get.return_value = mock_task
    
    created_tracker = Mock(spec=TimeTracker)
    created_tracker.id = 1
    created_tracker.is_running = True
    
    def add_side_effect(obj):
        if isinstance(obj, TimeTracker):
            obj.id = 1
            obj.is_running = True
    
    mock_session.add.side_effect = add_side_effect
    
    result = start_time_tracker(
        mock_session,
        mock_user.tenant_id,
        mock_user.id,
        mock_task.id,
        "Working on task"
    )
    
    assert result is not None
    assert result.is_running is True
    mock_session.add.assert_called()
    mock_session.commit.assert_called()


def test_stop_time_tracker(mock_session, mock_user):
    """Test stopping a time tracker."""
    from app.models.tasks import TimeTracker
    
    tracker = Mock(spec=TimeTracker)
    tracker.id = 1
    tracker.is_running = True
    tracker.start_date_time = datetime.utcnow()
    tracker.task_id = 1
    tracker.user_id = mock_user.id
    
    mock_session.get.return_value = tracker
    
    result = stop_time_tracker(
        mock_session,
        mock_user.tenant_id,
        tracker.id,
        mock_user.id
    )
    
    assert result.is_running is False
    mock_session.add.assert_called()
    mock_session.commit.assert_called()

















