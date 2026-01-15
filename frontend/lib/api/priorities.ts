import apiClient from "./client";
import { Priority, PriorityFormData } from "@/types/priority";
import { ApiResponse, DropdownItem } from "@/types/api";

export const prioritiesApi = {
  list: async (): Promise<Priority[]> => {
    const response = await apiClient.get<ApiResponse<Priority[]>>(
      "/modules/tasks/records",
      { params: { resource: "priorities" } }
    );
    return response.data.data;
  },

  create: async (data: PriorityFormData): Promise<Priority> => {
    const response = await apiClient.post<ApiResponse<Priority>>(
      "/modules/tasks/records",
      { ...data, name: data.title },
      { params: { resource: "priorities" } }
    );
    return response.data.data;
  },

  update: async (id: number, data: Partial<PriorityFormData>): Promise<Priority> => {
    const response = await apiClient.patch<ApiResponse<Priority>>(
      `/modules/tasks/records/${id}`,
      data,
      { params: { resource: "priorities" } }
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/modules/tasks/records/${id}`, {
      params: { resource: "priorities" },
    });
  },

  getDropdown: async (): Promise<DropdownItem[]> => {
    const response = await apiClient.get<ApiResponse<DropdownItem[]>>(
      "/modules/tasks/dropdown/priorities"
    );
    return response.data.data || [];
  },
};





