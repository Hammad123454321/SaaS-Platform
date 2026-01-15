import apiClient from "./client";
import { Project, ProjectFormData } from "@/types/project";
import { ApiResponse, DropdownItem } from "@/types/api";

export const projectsApi = {
  list: async (): Promise<Project[]> => {
    const response = await apiClient.get<ApiResponse<Project[]>>(
      "/modules/tasks/records",
      { params: { resource: "projects" } }
    );
    return response.data.data;
  },

  get: async (id: number): Promise<Project> => {
    const response = await apiClient.get<ApiResponse<Project>>(
      `/modules/tasks/records/${id}`,
      { params: { resource: "projects" } }
    );
    return response.data.data;
  },

  create: async (data: ProjectFormData): Promise<Project> => {
    const response = await apiClient.post<ApiResponse<Project>>(
      "/modules/tasks/records",
      { ...data, name: data.title },
      { params: { resource: "projects" } }
    );
    return response.data.data;
  },

  update: async (id: number, data: Partial<ProjectFormData>): Promise<Project> => {
    const response = await apiClient.patch<ApiResponse<Project>>(
      `/modules/tasks/records/${id}`,
      data,
      { params: { resource: "projects" } }
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/modules/tasks/records/${id}`, {
      params: { resource: "projects" },
    });
  },

  getDropdown: async (): Promise<DropdownItem[]> => {
    const response = await apiClient.get<ApiResponse<DropdownItem[]>>(
      "/modules/tasks/dropdown/projects"
    );
    return response.data.data || [];
  },
};





