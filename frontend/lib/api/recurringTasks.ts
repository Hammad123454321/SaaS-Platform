import apiClient from "./client";
import { ApiResponse } from "@/types/api";

export interface RecurringTask {
  id: number;
  task_id: number;
  recurrence_pattern: "daily" | "weekly" | "monthly" | "yearly" | "custom";
  recurrence_interval: number;
  recurrence_days?: number[];
  recurrence_end_date?: string;
  recurrence_count?: number;
  next_occurrence?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface RecurringTaskFormData {
  recurrence_pattern: "daily" | "weekly" | "monthly" | "yearly" | "custom";
  recurrence_interval: number;
  recurrence_days?: number[];
  recurrence_end_date?: string;
  recurrence_count?: number;
}

export const recurringTasksApi = {
  get: async (taskId: number): Promise<RecurringTask | null> => {
    try {
      const response = await apiClient.get<ApiResponse<RecurringTask>>(
        `/modules/tasks/tasks/${taskId}/recurring`
      );
      return response.data.data;
    } catch {
      return null;
    }
  },

  list: async (): Promise<RecurringTask[]> => {
    const response = await apiClient.get<ApiResponse<RecurringTask[]>>(
      "/modules/tasks/recurring-tasks"
    );
    return response.data.data;
  },

  create: async (taskId: number, data: RecurringTaskFormData): Promise<RecurringTask> => {
    const response = await apiClient.post<ApiResponse<RecurringTask>>(
      `/modules/tasks/tasks/${taskId}/recurring`,
      data
    );
    return response.data.data;
  },

  update: async (taskId: number, data: Partial<RecurringTaskFormData>): Promise<RecurringTask> => {
    const response = await apiClient.patch<ApiResponse<RecurringTask>>(
      `/modules/tasks/tasks/${taskId}/recurring`,
      data
    );
    return response.data.data;
  },

  delete: async (taskId: number): Promise<void> => {
    await apiClient.delete(`/modules/tasks/tasks/${taskId}/recurring`);
  },
};

