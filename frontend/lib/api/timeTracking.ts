import apiClient from "./client";
import { ApiResponse } from "@/types/api";

export interface TimeEntry {
  id: number;
  task_id: number;
  user_id: number;
  start_time: string;
  end_time?: string;
  duration_minutes?: number;
  description?: string;
  date?: string;
  created_at: string;
}

export interface TimeTracker {
  id: number;
  task_id: number;
  user_id: number;
  start_time: string;
  message?: string;
  created_at: string;
}

export interface TimeEntryFormData {
  task_id: number;
  start_time: string;
  end_time?: string;
  duration_minutes?: number;
  description?: string;
}

export const timeTrackingApi = {
  // Time Entries
  listEntries: async (task_id?: number, user_id?: number): Promise<TimeEntry[]> => {
    const params: any = {};
    if (task_id) params.task_id = task_id;
    if (user_id) params.user_id = user_id;
    const response = await apiClient.get<ApiResponse<TimeEntry[]>>(
      "/modules/tasks/time-entries",
      { params }
    );
    return response.data.data;
  },

  createEntry: async (data: TimeEntryFormData): Promise<TimeEntry> => {
    const response = await apiClient.post<ApiResponse<TimeEntry>>(
      "/modules/tasks/time-entries",
      data
    );
    return response.data.data;
  },

  // Time Tracker
  startTracker: async (taskId: number, message?: string): Promise<TimeTracker> => {
    const response = await apiClient.post<ApiResponse<TimeTracker>>(
      "/modules/tasks/time-tracker/start",
      { task_id: taskId, message }
    );
    return response.data.data;
  },

  stopTracker: async (trackerId: number): Promise<void> => {
    await apiClient.post(`/modules/tasks/time-tracker/${trackerId}/stop`);
  },

  getActiveTracker: async (): Promise<TimeTracker | null> => {
    try {
      const response = await apiClient.get<ApiResponse<TimeTracker>>(
        "/modules/tasks/time-tracker/active"
      );
      return response.data.data;
    } catch {
      return null;
    }
  },

  getReports: async (start_date?: string, end_date?: string): Promise<any> => {
    const params: any = {};
    if (start_date) params.start_date = start_date;
    if (end_date) params.end_date = end_date;
    const response = await apiClient.get("/modules/tasks/time-reports", { params });
    return response.data.data;
  },
};

