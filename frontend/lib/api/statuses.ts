import apiClient from "./client";
import { Status, StatusFormData } from "@/types/status";
import { ApiResponse, DropdownItem } from "@/types/api";

export const statusesApi = {
  list: async (): Promise<Status[]> => {
    const response = await apiClient.get<ApiResponse<Status[]>>(
      "/modules/tasks/records",
      { params: { resource: "statuses" } }
    );
    return response.data.data;
  },

  create: async (data: StatusFormData): Promise<Status> => {
    const response = await apiClient.post<ApiResponse<Status>>(
      "/modules/tasks/records",
      { ...data, name: data.title },
      { params: { resource: "statuses" } }
    );
    return response.data.data;
  },

  update: async (id: number, data: Partial<StatusFormData>): Promise<Status> => {
    const response = await apiClient.patch<ApiResponse<Status>>(
      `/modules/tasks/records/${id}`,
      data,
      { params: { resource: "statuses" } }
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/modules/tasks/records/${id}`, {
      params: { resource: "statuses" },
    });
  },

  getDropdown: async (): Promise<DropdownItem[]> => {
    const response = await apiClient.get<ApiResponse<DropdownItem[]>>(
      "/modules/tasks/dropdown/statuses"
    );
    return response.data.data || [];
  },
};





