import apiClient from "./client";
import { ApiResponse } from "@/types/api";

export interface Milestone {
  id: string;
  name: string;
  description?: string;
  project_id: string;
  due_date?: string;
  completed_at?: string;
  created_at: string;
  updated_at: string;
}

export interface MilestoneFormData {
  name: string;
  description?: string;
  project_id: string;
  due_date?: string;
}

export const milestonesApi = {
  list: async (project_id?: string): Promise<Milestone[]> => {
    const params: any = {};
    if (project_id) params.project_id = project_id;
    const response = await apiClient.get<ApiResponse<Milestone[]>>(
      "/modules/tasks/milestones",
      { params }
    );
    return response.data.data;
  },

  get: async (id: string): Promise<Milestone> => {
    const response = await apiClient.get<ApiResponse<Milestone>>(
      `/modules/tasks/milestones/${id}`
    );
    return response.data.data;
  },

  create: async (data: MilestoneFormData): Promise<Milestone> => {
    const response = await apiClient.post<ApiResponse<Milestone>>(
      "/modules/tasks/milestones",
      data
    );
    return response.data.data;
  },

  update: async (id: string, data: Partial<MilestoneFormData>): Promise<Milestone> => {
    const response = await apiClient.patch<ApiResponse<Milestone>>(
      `/modules/tasks/milestones/${id}`,
      data
    );
    return response.data.data;
  },

  delete: async (id: string): Promise<void> => {
    await apiClient.delete(`/modules/tasks/milestones/${id}`);
  },

  getStats: async (id: string): Promise<any> => {
    const response = await apiClient.get(`/modules/tasks/milestones/${id}/stats`);
    return response.data.data;
  },
};

