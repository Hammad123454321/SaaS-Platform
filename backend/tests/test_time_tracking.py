"""
Tests for Time Tracking operations.
"""
import pytest
from types import SimpleNamespace
from unittest.mock import AsyncMock

from app.services import tasks_time

VALID_TASK_ID = "507f1f77bcf86cd799439012"


@pytest.mark.asyncio
async def test_create_time_entry_success(monkeypatch):
    task = SimpleNamespace(id=VALID_TASK_ID, tenant_id="tenant-1")

    class DummyTaskModel:
        id = object()
        tenant_id = object()

        @staticmethod
        async def find_one(*args, **kwargs):
            return task

    inserts = []

    class DummyTimeEntry:
        def __init__(self, **kwargs):
            self.kwargs = kwargs

        async def insert(self):
            inserts.append(self.kwargs)

    monkeypatch.setattr(tasks_time, "Task", DummyTaskModel)
    monkeypatch.setattr(tasks_time, "TimeEntry", DummyTimeEntry)

    result = await tasks_time.create_time_entry("tenant-1", VALID_TASK_ID, "user-1", {"hours": 1})
    assert result is not None
    assert len(inserts) == 1


@pytest.mark.asyncio
async def test_start_and_stop_time_tracker(monkeypatch):
    class DummyTaskModel:
        id = object()
        tenant_id = object()

        @staticmethod
        async def find_one(*args, **kwargs):
            return SimpleNamespace(id=VALID_TASK_ID, tenant_id="tenant-1")

    class DummyTrackerModel:
        tenant_id = object()
        user_id = object()
        is_running = object()
        id = object()

        @staticmethod
        async def find_one(*args, **kwargs):
            return None

        def __init__(self, **kwargs):
            self.__dict__.update(kwargs)
            self.id = "507f1f77bcf86cd799439013"

        async def insert(self):
            return None

        async def save(self):
            return None

    monkeypatch.setattr(tasks_time, "Task", DummyTaskModel)
    monkeypatch.setattr(tasks_time, "TimeTracker", DummyTrackerModel)
    monkeypatch.setattr(tasks_time, "create_time_entry", AsyncMock(return_value=None))

    tracker = await tasks_time.start_time_tracker("tenant-1", "user-1", VALID_TASK_ID, "Working")
    assert tracker.is_running is True

    # Patch find_one to return tracker for stop
    async def find_one_stop(*args, **kwargs):
        return tracker

    monkeypatch.setattr(DummyTrackerModel, "find_one", find_one_stop)

    result = await tasks_time.stop_time_tracker("tenant-1", "507f1f77bcf86cd799439013", "user-1")
    assert result.is_running is False
