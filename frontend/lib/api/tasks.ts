import apiClient from "./client";
import { Task, TaskFormData, TaskFilters, KanbanColumn } from "@/types/task";
import { ApiResponse } from "@/types/api";

export const tasksApi = {
  // List tasks with filters
  list: async (filters?: TaskFilters): Promise<Task[]> => {
    const response = await apiClient.get<ApiResponse<Task[]>>(
      "/modules/tasks/records",
      {
        params: { resource: "tasks", ...filters },
      }
    );
    return response.data.data;
  },

  // Get single task
  get: async (id: string): Promise<Task> => {
    const response = await apiClient.get<ApiResponse<Task>>(
      `/modules/tasks/records/${id}`,
      {
        params: { resource: "tasks" },
      }
    );
    return response.data.data;
  },

  // Create task
  create: async (data: TaskFormData): Promise<Task> => {
    const response = await apiClient.post<ApiResponse<Task>>(
      "/modules/tasks/records",
      data,
      {
        params: { resource: "tasks" },
      }
    );
    return response.data.data;
  },

  // Update task
  update: async (id: string, data: Partial<TaskFormData>): Promise<Task> => {
    const response = await apiClient.patch<ApiResponse<Task>>(
      `/modules/tasks/records/${id}`,
      data,
      {
        params: { resource: "tasks" },
      }
    );
    return response.data.data;
  },

  // Delete task
  delete: async (id: string): Promise<void> => {
    await apiClient.delete(`/modules/tasks/records/${id}`, {
      params: { resource: "tasks" },
    });
  },

  // Get my tasks (for staff)
  getMyTasks: async (): Promise<Task[]> => {
    const response = await apiClient.get<ApiResponse<Task[]>>(
      "/modules/tasks/my-tasks"
    );
    return response.data.data;
  },

  // Kanban view
  getKanban: async (projectId?: string): Promise<Record<string, KanbanColumn>> => {
    const response = await apiClient.get<ApiResponse<Record<string, KanbanColumn>>>(
      "/modules/tasks/kanban",
      {
        params: projectId ? { project_id: projectId } : {},
      }
    );
    return response.data.data;
  },

  // Move task in kanban
  moveTask: async (taskId: string, statusId: string): Promise<void> => {
    await apiClient.patch(
      `/modules/tasks/kanban/${taskId}/move`,
      null,
      {
        params: { status_id: statusId },
      }
    );
  },

  // Task actions
  toggleFavorite: async (taskId: string, isFavorite: boolean): Promise<void> => {
    await apiClient.patch(
      `/modules/tasks/tasks/${taskId}/favorite`,
      null,
      {
        params: { is_favorite: !isFavorite },
      }
    );
  },

  togglePinned: async (taskId: string, isPinned: boolean): Promise<void> => {
    await apiClient.patch(
      `/modules/tasks/tasks/${taskId}/pinned`,
      null,
      {
        params: { is_pinned: !isPinned },
      }
    );
  },

  duplicate: async (taskId: string): Promise<Task> => {
    const response = await apiClient.post<ApiResponse<Task>>(
      `/modules/tasks/tasks/${taskId}/duplicate`
    );
    return response.data.data;
  },

  bulkDelete: async (taskIds: string[]): Promise<void> => {
    await apiClient.post("/modules/tasks/tasks/bulk-delete", {
      task_ids: taskIds,
    });
  },

  // Upload media
  uploadMedia: async (taskId: string, file: File): Promise<void> => {
    const formData = new FormData();
    formData.append("file", file);
    await apiClient.post(
      `/modules/tasks/tasks/${taskId}/media`,
      formData,
      {
        headers: { "Content-Type": "multipart/form-data" },
      }
    );
  },
};





