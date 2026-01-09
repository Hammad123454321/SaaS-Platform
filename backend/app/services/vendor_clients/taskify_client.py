"""Production-ready HTTP client for Taskify (Laravel) module integration."""
import asyncio
import logging
from typing import Any, Dict, List, Optional
from urllib.parse import urljoin

import httpx
from httpx import Response, TimeoutException, ConnectError, HTTPStatusError

from app.services.vendor_clients.base import BaseVendorClient

logger = logging.getLogger(__name__)


class TaskifyClient(BaseVendorClient):
    """HTTP client for Taskify Laravel API with retry logic, error handling, and tenant isolation."""

    def __init__(
        self,
        base_url: str,
        api_token: str,
        workspace_id: Optional[int] = None,
        timeout: float = 30.0,
        max_retries: int = 3,
    ):
        """
        Initialize Taskify client.

        Args:
            base_url: Base URL of Taskify instance (e.g., "http://taskify:8001")
            api_token: Laravel Sanctum API token for authentication
            workspace_id: Workspace ID to scope requests (maps to FastAPI tenant_id)
            timeout: Request timeout in seconds
            max_retries: Maximum retry attempts for transient failures
        """
        self.base_url = base_url.rstrip("/")
        self.api_token = api_token
        self.workspace_id = workspace_id
        self.timeout = timeout
        self.max_retries = max_retries

        self.client = httpx.AsyncClient(
            base_url=self.base_url,
            timeout=timeout,
            headers={
                "Authorization": f"Bearer {api_token}",
                "Accept": "application/json",
                "Content-Type": "application/json",
            },
        )

    async def _request(
        self,
        method: str,
        endpoint: str,
        params: Optional[Dict[str, Any]] = None,
        json_data: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        """
        Make HTTP request with retry logic and error handling.

        Raises:
            httpx.HTTPStatusError: For 4xx/5xx responses
            httpx.TimeoutException: For timeout errors
            httpx.ConnectError: For connection errors
        """
        url = urljoin(self.base_url + "/", endpoint.lstrip("/"))
        
        # Add workspace context if provided (Taskify uses workspace_id header or query param)
        # Note: Taskify may use workspace_id in headers or query params depending on configuration
        headers = {}
        params_with_workspace = dict(params) if params else {}
        if self.workspace_id:
            headers["X-Workspace-Id"] = str(self.workspace_id)
            # Also add to query params as fallback
            params_with_workspace["workspace_id"] = self.workspace_id

        last_exception = None
        for attempt in range(self.max_retries):
            try:
                response: Response = await self.client.request(
                    method=method,
                    url=url,
                    params=params_with_workspace,
                    json=json_data,
                    headers={**self.client.headers, **headers},
                )
                response.raise_for_status()
                return response.json()

            except TimeoutException as e:
                last_exception = e
                logger.warning(f"Taskify request timeout (attempt {attempt + 1}/{self.max_retries}): {url}")
                if attempt < self.max_retries - 1:
                    await asyncio.sleep(2 ** attempt)  # Exponential backoff
                    continue
                raise

            except ConnectError as e:
                last_exception = e
                logger.warning(f"Taskify connection error (attempt {attempt + 1}/{self.max_retries}): {url}")
                if attempt < self.max_retries - 1:
                    await asyncio.sleep(2 ** attempt)
                    continue
                raise

            except httpx.HTTPStatusError as e:
                # Don't retry 4xx errors (client errors)
                if 400 <= e.response.status_code < 500:
                    logger.error(f"Taskify client error {e.response.status_code}: {e.response.text}")
                    raise
                # Retry 5xx errors (server errors)
                last_exception = e
                logger.warning(f"Taskify server error {e.response.status_code} (attempt {attempt + 1}/{self.max_retries})")
                if attempt < self.max_retries - 1:
                    await asyncio.sleep(2 ** attempt)
                    continue
                raise

        if last_exception:
            raise last_exception

    async def health(self) -> Dict[str, Any]:
        """Check Taskify service health."""
        try:
            # Try a lightweight endpoint - if dashboard stats doesn't exist, try tasks endpoint
            try:
                data = await self._request("GET", "/api/dashboard/statistics")
            except HTTPStatusError as e:
                if e.response.status_code == 404:
                    # Fallback to tasks endpoint if dashboard doesn't exist
                    data = await self._request("GET", "/api/tasks", params={"limit": 1})
                else:
                    raise
            return {"status": "ok", "vendor": "taskify", "workspace_id": self.workspace_id}
        except Exception as e:
            logger.error(f"Taskify health check failed: {e}")
            return {"status": "error", "vendor": "taskify", "error": str(e)}

    # Task Management Methods (mapping to your AI tools)
    async def list_tasks(
        self,
        project_id: Optional[int] = None,
        status: Optional[str] = None,
        assignee_id: Optional[int] = None,
    ) -> List[Dict[str, Any]]:
        """List tasks from Taskify."""
        params = {}
        if project_id:
            params["project_id"] = project_id
        if status:
            params["status"] = status
        if assignee_id:
            params["assignee_id"] = assignee_id

        data = await self._request("GET", "/api/tasks", params=params)
        # Taskify returns tasks in 'data' key or directly as array
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def list_records(self, resource: str, **filters: Any) -> List[Dict[str, Any]]:
        """List records for a resource (tasks/projects/clients/meetings/todos/notes/statuses/priorities)."""
        if resource == "tasks":
            project_id = self._coerce_int(filters.get("project_id"))
            assignee_id = self._coerce_int(filters.get("assignee_id"))
            status = filters.get("status")
            return await self.list_tasks(
                project_id=project_id,
                status=status,
                assignee_id=assignee_id,
            )

        if resource == "projects":
            return await self.list_projects()

        if resource == "clients":
            return await self.list_clients()

        if resource == "meetings":
            return await self.list_meetings()

        if resource == "todos":
            return await self.list_todos()

        if resource == "notes":
            return await self.list_notes()

        if resource in {"statuses", "status"}:
            data = await self._request("GET", "/api/statuses", params=filters or None)
            if isinstance(data, dict) and "data" in data:
                return data["data"]
            return data if isinstance(data, list) else []

        if resource in {"priorities", "priority"}:
            data = await self._request("GET", "/api/priorities", params=filters or None)
            if isinstance(data, dict) and "data" in data:
                return data["data"]
            return data if isinstance(data, list) else []

        data = await self._request("GET", f"/api/{resource}", params=filters or None)
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_record(self, resource: str, payload: Dict[str, Any]) -> Dict[str, Any]:
        """
        Generic create_record method for compatibility with base interface.
        
        Maps resource names to Taskify endpoints.
        """
        endpoint_map = {
            "tasks": "/api/tasks/store",
            "projects": "/api/projects/store",
            "clients": "/api/clients/store",
            "meetings": "/api/meetings/store",
            "todos": "/api/todos/store",
            "notes": "/api/notes/store",
            "statuses": "/api/status/store",
            "status": "/api/status/store",
            "priorities": "/api/priorities/store",
            "priority": "/api/priorities/store",
        }
        
        endpoint = endpoint_map.get(resource, f"/api/{resource}/store")
        
        # Normalize payload based on resource type
        if resource == "tasks":
            normalized = {
                "title": payload.get("title", ""),
                "description": payload.get("description", ""),
                "project_id": payload.get("project_id"),
                "status_id": payload.get("status_id"),
                "priority_id": payload.get("priority_id"),
                "due_date": payload.get("due_date"),
                "assignee_ids": payload.get("assignee_ids", []),
            }
        elif resource == "projects":
            normalized = {
                "name": payload.get("name", ""),
                "description": payload.get("description", ""),
                "client_id": payload.get("client_id"),
                "budget": payload.get("budget"),
                "deadline": payload.get("deadline"),
            }
        elif resource == "meetings":
            normalized = {
                "title": payload.get("title", ""),
                "description": payload.get("description", ""),
                "start_time": payload.get("start_time"),
                "end_time": payload.get("end_time"),
                "location": payload.get("location"),
            }
        elif resource == "todos":
            normalized = {
                "title": payload.get("title", ""),
                "description": payload.get("description", ""),
                "due_date": payload.get("due_date"),
                "status": payload.get("status"),
                "priority_id": payload.get("priority_id"),
            }
        elif resource == "notes":
            normalized = {
                "title": payload.get("title", ""),
                "note": payload.get("note", ""),
            }
        elif resource in {"statuses", "status"}:
            normalized = {
                "name": payload.get("name", ""),
                "color": payload.get("color"),
            }
        elif resource in {"priorities", "priority"}:
            normalized = {
                "name": payload.get("name", ""),
                "color": payload.get("color"),
            }
        else:
            normalized = payload
        
        data = await self._request("POST", endpoint, json_data=normalized)
        return data.get("data", data) if isinstance(data, dict) else data

    async def create_task(self, task_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new task in Taskify."""
        # Map your AI tool's task_data to Taskify's expected format
        payload = {
            "title": task_data.get("title", ""),
            "description": task_data.get("description", ""),
            "project_id": task_data.get("project_id"),
            "status_id": task_data.get("status_id"),
            "priority_id": task_data.get("priority_id"),
            "due_date": task_data.get("due_date"),
            "assignee_ids": task_data.get("assignee_ids", []),
        }
        data = await self._request("POST", "/api/tasks/store", json_data=payload)
        return data.get("data", data) if isinstance(data, dict) else data

    async def update_task(self, task_id: int, updates: Dict[str, Any]) -> Dict[str, Any]:
        """Update an existing task."""
        payload = {
            "id": task_id,
            "title": updates.get("title"),
            "description": updates.get("description"),
            "status_id": updates.get("status_id"),
            "priority_id": updates.get("priority_id"),
            "due_date": updates.get("due_date"),
            "project_id": updates.get("project_id"),
            "assignee_ids": updates.get("assignee_ids"),
        }
        data = await self._request("POST", "/api/tasks/update", json_data=payload)
        return data.get("data", data) if isinstance(data, dict) else data

    async def list_projects(self) -> List[Dict[str, Any]]:
        """List all projects."""
        data = await self._request("GET", "/api/projects")
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_project(self, project_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new project."""
        payload = {
            "name": project_data.get("name", ""),
            "description": project_data.get("description", ""),
            "client_id": project_data.get("client_id"),
            "budget": project_data.get("budget"),
            "deadline": project_data.get("deadline"),
        }
        data = await self._request("POST", "/api/projects/store", json_data=payload)
        return data.get("data", data) if isinstance(data, dict) else data

    async def list_clients(self) -> List[Dict[str, Any]]:
        """List all clients."""
        data = await self._request("GET", "/api/clients")
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_user(self, user_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Create a user in Taskify.
        
        Args:
            user_data: User data with fields like:
                - first_name: str
                - last_name: str
                - email: str
                - password: str
                - role: Optional[int] (role ID)
                - status: Optional[int] (1 for active, 0 for inactive)
                - require_ev: Optional[int] (0 to skip email verification, 1 to require)
        
        Returns:
            Created user data
        """
        payload = {
            "first_name": user_data.get("first_name", ""),
            "last_name": user_data.get("last_name", ""),
            "email": user_data.get("email", ""),
            "password": user_data.get("password", ""),
            "is_team_member": True,  # Always create as team member for SaaS platform users
        }
        
        # Optional fields
        if "role" in user_data:
            payload["role"] = user_data["role"]
        if "status" in user_data:
            payload["status"] = user_data["status"]
        if "require_ev" in user_data:
            payload["require_ev"] = user_data["require_ev"]
        
        data = await self._request("POST", "/api/users/store", json_data=payload)
        return data.get("data", data) if isinstance(data, dict) else data

    async def update_record(self, resource: str, record_id: str, payload: Dict[str, Any]) -> Dict[str, Any]:
        """Generic update_record method for compatibility with base interface."""
        endpoint_map = {
            "tasks": "/api/tasks/update",
            "projects": "/api/projects/update",
            "clients": "/api/clients/update",
            "meetings": "/api/meetings/update",
            "todos": "/api/todos/update",
            "notes": "/api/notes/update",
            "statuses": "/api/status/update",
            "status": "/api/status/update",
            "priorities": "/api/priorities/update",
            "priority": "/api/priorities/update",
        }
        
        endpoint = endpoint_map.get(resource, f"/api/{resource}/update")
        
        # Add ID to payload for Taskify
        payload_with_id = {**payload, "id": int(record_id)}
        
        data = await self._request("POST", endpoint, json_data=payload_with_id)
        return data.get("data", data) if isinstance(data, dict) else data

    async def delete_record(self, resource: str, record_id: str) -> Dict[str, Any]:
        """Generic delete_record method."""
        endpoint_map = {
            "tasks": f"/api/tasks/destroy/{record_id}",
            "projects": f"/api/projects/destroy/{record_id}",
            "clients": f"/api/clients/destroy/{record_id}",
            "meetings": f"/api/meetings/destroy/{record_id}",
            "todos": f"/api/todos/destroy/{record_id}",
            "notes": f"/api/notes/destroy/{record_id}",
            "statuses": f"/api/status/destroy/{record_id}",
            "status": f"/api/status/destroy/{record_id}",
            "priorities": f"/api/priorities/destroy/{record_id}",
            "priority": f"/api/priorities/destroy/{record_id}",
        }
        
        endpoint = endpoint_map.get(resource, f"/api/{resource}/destroy/{record_id}")
        
        data = await self._request("DELETE", endpoint)
        return data.get("data", data) if isinstance(data, dict) else data

    # Task-specific methods
    async def update_task_status(self, task_id: int, status_id: int) -> Dict[str, Any]:
        """Update task status."""
        data = await self._request("PATCH", f"/api/tasks/{task_id}/status", json_data={"status_id": status_id})
        return data.get("data", data) if isinstance(data, dict) else data

    async def update_task_priority(self, task_id: int, priority_id: int) -> Dict[str, Any]:
        """Update task priority."""
        data = await self._request("PATCH", f"/api/tasks/{task_id}/priority", json_data={"priority_id": priority_id})
        return data.get("data", data) if isinstance(data, dict) else data

    async def add_task_comment(self, task_id: int, comment: str) -> Dict[str, Any]:
        """Add comment to a task."""
        # Taskify uses /api/tasks/{id}/comments endpoint
        data = await self._request("POST", f"/api/tasks/{task_id}/comments", json_data={"comment": comment})
        return data.get("data", data) if isinstance(data, dict) else data

    async def get_task_comments(self, task_id: int) -> List[Dict[str, Any]]:
        """Get comments for a task."""
        # Taskify uses /api/tasks/{id}/comments/list endpoint
        data = await self._request("GET", f"/api/tasks/{task_id}/comments/list")
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def upload_task_media(self, task_id: int, file_data: bytes, filename: str) -> Dict[str, Any]:
        """Upload media to a task."""
        # Note: This would need multipart/form-data handling
        # For now, return a placeholder
        raise NotImplementedError("File uploads need multipart/form-data handling")

    async def get_task_media(self, task_id: int) -> List[Dict[str, Any]]:
        """Get media for a task."""
        data = await self._request("GET", f"/api/tasks/get-media/{task_id}")
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    # Meetings
    async def list_meetings(self) -> List[Dict[str, Any]]:
        """List all meetings."""
        data = await self._request("GET", "/api/meetings")
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_meeting(self, meeting_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new meeting."""
        data = await self._request("POST", "/api/meetings/store", json_data=meeting_data)
        return data.get("data", data) if isinstance(data, dict) else data

    # Todos
    async def list_todos(self) -> List[Dict[str, Any]]:
        """List all todos."""
        data = await self._request("GET", "/api/todos")
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_todo(self, todo_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new todo."""
        data = await self._request("POST", "/api/todos/store", json_data=todo_data)
        return data.get("data", data) if isinstance(data, dict) else data

    async def update_todo_status(self, todo_id: int, status: str) -> Dict[str, Any]:
        """Update todo status."""
        data = await self._request("PATCH", f"/api/todos/{todo_id}/status", json_data={"status": status})
        return data.get("data", data) if isinstance(data, dict) else data

    # Notes
    async def list_notes(self) -> List[Dict[str, Any]]:
        """List all notes."""
        data = await self._request("GET", "/api/notes")
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_note(self, note_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new note."""
        data = await self._request("POST", "/api/notes/store", json_data=note_data)
        return data.get("data", data) if isinstance(data, dict) else data

    # Milestones
    async def list_milestones(self, project_id: Optional[int] = None) -> List[Dict[str, Any]]:
        """List milestones, optionally filtered by project."""
        params = {}
        if project_id:
            params["project_id"] = project_id
        data = await self._request("GET", "/api/milestones", params=params if params else None)
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_milestone(self, milestone_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new milestone."""
        data = await self._request("POST", "/api/milestones/store", json_data=milestone_data)
        return data.get("data", data) if isinstance(data, dict) else data

    async def update_milestone(self, milestone_id: int, updates: Dict[str, Any]) -> Dict[str, Any]:
        """Update a milestone."""
        payload = {**updates, "id": milestone_id}
        data = await self._request("POST", "/api/milestones/update", json_data=payload)
        return data.get("data", data) if isinstance(data, dict) else data

    async def delete_milestone(self, milestone_id: int) -> Dict[str, Any]:
        """Delete a milestone."""
        data = await self._request("DELETE", f"/api/milestones/destroy/{milestone_id}")
        return data.get("data", data) if isinstance(data, dict) else data

    # Task Lists
    async def list_task_lists(self) -> List[Dict[str, Any]]:
        """List all task lists."""
        data = await self._request("GET", "/api/task-lists/list")
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_task_list(self, task_list_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new task list."""
        data = await self._request("POST", "/api/task-lists/store", json_data=task_list_data)
        return data.get("data", data) if isinstance(data, dict) else data

    async def update_task_list(self, task_list_id: int, updates: Dict[str, Any]) -> Dict[str, Any]:
        """Update a task list."""
        payload = {**updates, "id": task_list_id}
        data = await self._request("POST", "/api/task-lists/update", json_data=payload)
        return data.get("data", data) if isinstance(data, dict) else data

    async def delete_task_list(self, task_list_id: int) -> Dict[str, Any]:
        """Delete a task list."""
        data = await self._request("DELETE", f"/api/task-lists/destroy/{task_list_id}")
        return data.get("data", data) if isinstance(data, dict) else data

    # Time Tracker / Task Time Entries
    async def list_time_trackers(self, task_id: Optional[int] = None) -> List[Dict[str, Any]]:
        """List time tracker entries, optionally filtered by task."""
        params = {}
        if task_id:
            params["task_id"] = task_id
        data = await self._request("GET", "/api/time-tracker/list", params=params if params else None)
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_time_tracker(self, time_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new time tracker entry."""
        data = await self._request("POST", "/api/time-tracker/store", json_data=time_data)
        return data.get("data", data) if isinstance(data, dict) else data

    async def update_time_tracker(self, time_id: int, updates: Dict[str, Any]) -> Dict[str, Any]:
        """Update a time tracker entry."""
        payload = {**updates, "id": time_id}
        data = await self._request("POST", "/api/time-tracker/update", json_data=payload)
        return data.get("data", data) if isinstance(data, dict) else data

    async def delete_time_tracker(self, time_id: int) -> Dict[str, Any]:
        """Delete a time tracker entry."""
        data = await self._request("DELETE", f"/api/time-tracker/destroy/{time_id}")
        return data.get("data", data) if isinstance(data, dict) else data

    async def list_task_time_entries(self, task_id: int) -> List[Dict[str, Any]]:
        """List time entries for a specific task."""
        data = await self._request("GET", f"/api/tasks/{task_id}/time-entries", params={"isApi": True})
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    # Tags
    async def list_tags(self) -> List[Dict[str, Any]]:
        """List all tags."""
        data = await self._request("GET", "/api/tags")
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_tag(self, tag_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new tag."""
        data = await self._request("POST", "/api/tags/store", json_data=tag_data)
        return data.get("data", data) if isinstance(data, dict) else data

    async def update_tag(self, tag_id: int, updates: Dict[str, Any]) -> Dict[str, Any]:
        """Update a tag."""
        payload = {**updates, "id": tag_id}
        data = await self._request("POST", "/api/tags/update", json_data=payload)
        return data.get("data", data) if isinstance(data, dict) else data

    async def delete_tag(self, tag_id: int) -> Dict[str, Any]:
        """Delete a tag."""
        data = await self._request("DELETE", f"/api/tags/destroy/{tag_id}")
        return data.get("data", data) if isinstance(data, dict) else data

    # Recurring Tasks
    async def get_recurring_task(self, task_id: int) -> Optional[Dict[str, Any]]:
        """Get recurring task configuration for a task."""
        # Taskify includes recurring task in task details
        task_data = await self._request("GET", f"/api/tasks/{task_id}")
        if isinstance(task_data, dict):
            task = task_data.get("data", task_data)
            if isinstance(task, dict) and "recurring_task" in task:
                return task["recurring_task"]
        return None

    # Status Timelines
    async def get_status_timelines(self, task_id: int) -> List[Dict[str, Any]]:
        """Get status change timeline for a task."""
        data = await self._request("GET", f"/api/tasks/{task_id}/status-timelines")
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    # Favorites/Pinned
    async def update_task_favorite(self, task_id: int, is_favorite: bool) -> Dict[str, Any]:
        """Update task favorite status."""
        data = await self._request("PATCH", f"/api/tasks/{task_id}/favorite", json_data={"favorite": 1 if is_favorite else 0})
        return data.get("data", data) if isinstance(data, dict) else data

    async def update_task_pinned(self, task_id: int, is_pinned: bool) -> Dict[str, Any]:
        """Update task pinned status."""
        data = await self._request("PATCH", f"/api/tasks/{task_id}/pinned", json_data={"pinned": 1 if is_pinned else 0})
        return data.get("data", data) if isinstance(data, dict) else data

    # Media/File Attachments
    async def upload_task_media(self, task_id: int, file_path: str, file_content: bytes, filename: str) -> Dict[str, Any]:
        """Upload media/file to a task."""
        # Note: This requires multipart/form-data handling
        # For now, we'll use a workaround with httpx's files parameter
        import httpx
        
        files = {
            "file": (filename, file_content, "application/octet-stream")
        }
        data = {
            "task_id": task_id,
            "isApi": True,
        }
        
        url = urljoin(self.base_url + "/", f"/api/tasks/upload-media".lstrip("/"))
        headers = {
            "Authorization": f"Bearer {self.api_token}",
            "Accept": "application/json",
        }
        if self.workspace_id:
            headers["workspace_id"] = str(self.workspace_id)
        
        async with httpx.AsyncClient(timeout=self.timeout) as client:
            response = await client.post(url, files=files, data=data, headers=headers)
            response.raise_for_status()
            return response.json().get("data", response.json()) if isinstance(response.json(), dict) else response.json()

    async def delete_task_media(self, media_id: int) -> Dict[str, Any]:
        """Delete media from a task."""
        data = await self._request("DELETE", f"/api/tasks/delete-media/{media_id}")
        return data.get("data", data) if isinstance(data, dict) else data

    # Task Dependencies (via subtasks - Taskify uses subtasks for dependencies)
    async def get_task_subtasks(self, task_id: int) -> List[Dict[str, Any]]:
        """Get subtasks/dependencies for a task."""
        task_data = await self._request("GET", f"/api/tasks/{task_id}")
        if isinstance(task_data, dict):
            task = task_data.get("data", task_data)
            if isinstance(task, dict) and "subtasks" in task:
                return task["subtasks"]
        return []

    # Bulk Operations
    async def bulk_delete_tasks(self, task_ids: List[int]) -> Dict[str, Any]:
        """Bulk delete tasks."""
        # Taskify uses POST /tasks/destroy_multiple
        data = await self._request("POST", "/api/tasks/destroy_multiple", json_data={"task_ids": task_ids})
        return data.get("data", data) if isinstance(data, dict) else data

    async def duplicate_task(self, task_id: int) -> Dict[str, Any]:
        """Duplicate a task."""
        # Taskify uses GET /tasks/duplicate/{id}
        data = await self._request("GET", f"/api/tasks/duplicate/{task_id}")
        return data.get("data", data) if isinstance(data, dict) else data

    # Activity Log
    async def get_activity_log(self, task_id: Optional[int] = None, limit: Optional[int] = None) -> List[Dict[str, Any]]:
        """Get activity log, optionally filtered by task."""
        params = {}
        if task_id:
            params["task_id"] = task_id
        if limit:
            params["limit"] = limit
        data = await self._request("GET", "/api/activity-log", params=params if params else None)
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    # Custom Fields
    async def list_custom_fields(self, module: str = "task") -> List[Dict[str, Any]]:
        """List custom fields for a module."""
        params = {"module": module}
        data = await self._request("GET", "/api/custom-fields/list", params=params)
        if isinstance(data, dict) and "data" in data:
            return data["data"]
        return data if isinstance(data, list) else []

    async def create_custom_field(self, field_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a custom field."""
        data = await self._request("POST", "/api/custom-fields", json_data=field_data)
        return data.get("data", data) if isinstance(data, dict) else data

    async def update_custom_field(self, field_id: int, updates: Dict[str, Any]) -> Dict[str, Any]:
        """Update a custom field."""
        payload = {**updates, "id": field_id}
        data = await self._request("POST", f"/api/custom-fields/update/{field_id}", json_data=payload)
        return data.get("data", data) if isinstance(data, dict) else data

    async def delete_custom_field(self, field_id: int) -> Dict[str, Any]:
        """Delete a custom field."""
        data = await self._request("DELETE", f"/api/custom-fields/destroy/{field_id}")
        return data.get("data", data) if isinstance(data, dict) else data

    async def close(self):
        """Close the HTTP client."""
        await self.client.aclose()

    @staticmethod
    def _coerce_int(value: Any) -> Optional[int]:
        try:
            return int(value)
        except (TypeError, ValueError):
            return None

