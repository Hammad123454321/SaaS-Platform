import apiClient from "./client";
import { ApiResponse } from "@/types/api";

export interface TaskDependency {
  id: string;
  task_id: string;
  depends_on_task_id: string;
  dependency_type: "blocks" | "blocked_by" | "related";
  created_at: string;
}

export interface TaskDependencyFormData {
  depends_on_task_id: string;
  dependency_type: "blocks" | "blocked_by" | "related";
}

export const taskDependenciesApi = {
  list: async (taskId: string): Promise<TaskDependency[]> => {
    const response = await apiClient.get<ApiResponse<TaskDependency[]>>(
      `/modules/tasks/tasks/${taskId}/dependencies`
    );
    return response.data.data;
  },

  getBlocking: async (taskId: string): Promise<TaskDependency[]> => {
    const response = await apiClient.get<ApiResponse<TaskDependency[]>>(
      `/modules/tasks/tasks/${taskId}/dependencies/blocking`
    );
    return response.data.data;
  },

  create: async (taskId: string, data: TaskDependencyFormData): Promise<TaskDependency> => {
    const response = await apiClient.post<ApiResponse<TaskDependency>>(
      `/modules/tasks/tasks/${taskId}/dependencies`,
      data
    );
    return response.data.data;
  },

  delete: async (dependencyId: string): Promise<void> => {
    await apiClient.delete(`/modules/tasks/dependencies/${dependencyId}`);
  },
};
