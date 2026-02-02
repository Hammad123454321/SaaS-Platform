"""
Tests for Project and Task Duplication.
"""
import pytest
from types import SimpleNamespace
from unittest.mock import AsyncMock

from app.services import tasks_duplication


class DummyQuery:
    def __init__(self, result):
        self._result = result

    async def to_list(self):
        return self._result


@pytest.mark.asyncio
async def test_duplicate_project(monkeypatch):
    valid_project_id = "507f1f77bcf86cd799439011"
    original_project = SimpleNamespace(
        id=valid_project_id,
        tenant_id="tenant-1",
        name="Project A",
        description="",
        client_id=None,
        budget=None,
        start_date=None,
        deadline=None,
        status=None,
    )
    new_project = SimpleNamespace(id="proj-2")
    original_task = SimpleNamespace(id="task-1")

    class DummyProjectModel:
        id = object()
        tenant_id = object()

        @staticmethod
        async def find_one(*args, **kwargs):
            return original_project

    class DummyTaskModel:
        tenant_id = object()
        project_id = object()
        parent_id = object()

        @staticmethod
        def find(*args, **kwargs):
            return None

    monkeypatch.setattr(tasks_duplication, "Project", DummyProjectModel)
    monkeypatch.setattr(tasks_duplication, "Task", DummyTaskModel)
    monkeypatch.setattr(tasks_duplication, "create_project", AsyncMock(return_value=new_project))

    call_count = {"count": 0}

    def fake_find(*args, **kwargs):
        call_count["count"] += 1
        if call_count["count"] == 1:
            return DummyQuery([original_task])
        return DummyQuery([])

    monkeypatch.setattr(tasks_duplication.Task, "find", fake_find)
    monkeypatch.setattr(tasks_duplication, "_duplicate_task", AsyncMock(return_value=SimpleNamespace(id="task-2")))

    result = await tasks_duplication.duplicate_project("tenant-1", valid_project_id, "user-1")
    assert result == new_project


@pytest.mark.asyncio
async def test_duplicate_task_with_subtasks(monkeypatch):
    original_task = SimpleNamespace(
        id="task-1",
        title="Task A",
        description="",
        notes="",
        status_id=None,
        priority_id=None,
        project_id="proj-1",
        start_date=None,
        due_date=None,
        completion_percentage=0,
        billing_type=None,
        client_can_discuss=False,
        assignee_ids=[],
    )
    new_task = SimpleNamespace(id="task-2")

    monkeypatch.setattr(tasks_duplication, "get_task", AsyncMock(return_value=original_task))
    monkeypatch.setattr(tasks_duplication, "create_task", AsyncMock(return_value=new_task))
    class DummyTaskModel:
        tenant_id = object()
        parent_id = object()

        @staticmethod
        def find(*args, **kwargs):
            return DummyQuery([])

    monkeypatch.setattr(tasks_duplication, "Task", DummyTaskModel)

    result = await tasks_duplication.duplicate_task_with_subtasks("tenant-1", "task-1", "user-1")
    assert result == new_task
