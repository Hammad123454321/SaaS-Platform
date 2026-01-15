import apiClient from "./client";
import { ApiResponse } from "@/types/api";

export interface TaskMetrics {
  total_tasks: number;
  completed_tasks: number;
  in_progress_tasks: number;
  overdue_tasks: number;
  completion_rate: number;
  tasks_by_status: Record<string, number>;
  tasks_by_priority: Record<string, number>;
}

export interface EmployeeProgress {
  user_id: number;
  user_name: string;
  total_tasks: number;
  completed_tasks: number;
  completion_rate: number;
}

export const taskMetricsApi = {
  getDashboardMetrics: async (): Promise<TaskMetrics> => {
    const response = await apiClient.get<ApiResponse<TaskMetrics>>(
      "/modules/tasks/dashboard/metrics"
    );
    return response.data.data;
  },

  getEmployeeProgress: async (): Promise<EmployeeProgress[]> => {
    const response = await apiClient.get<ApiResponse<EmployeeProgress[]>>(
      "/modules/tasks/dashboard/employee-progress"
    );
    return response.data.data;
  },
};

