import apiClient from "./client";
import { ApiResponse } from "@/types/api";

export interface TaskList {
  id: number;
  name: string;
  description?: string;
  project_id: number;
  display_order: number;
  created_at: string;
  updated_at: string;
}

export interface TaskListFormData {
  name: string;
  description?: string;
  project_id: number;
  display_order?: number;
}

export const taskListsApi = {
  list: async (project_id?: number): Promise<TaskList[]> => {
    const params: any = {};
    if (project_id) params.project_id = project_id;
    const response = await apiClient.get<ApiResponse<TaskList[]>>(
      "/modules/tasks/task-lists",
      { params }
    );
    return response.data.data;
  },

  create: async (data: TaskListFormData): Promise<TaskList> => {
    const response = await apiClient.post<ApiResponse<TaskList>>(
      "/modules/tasks/task-lists",
      data
    );
    return response.data.data;
  },

  update: async (id: number, data: Partial<TaskListFormData>): Promise<TaskList> => {
    const response = await apiClient.patch<ApiResponse<TaskList>>(
      `/modules/tasks/task-lists/${id}`,
      data
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/modules/tasks/task-lists/${id}`);
  },

  getStats: async (id: number): Promise<any> => {
    const response = await apiClient.get(`/modules/tasks/task-lists/${id}/stats`);
    return response.data.data;
  },
};

