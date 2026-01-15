import apiClient from "./client";
import { Client, ClientFormData } from "@/types/client";
import { ApiResponse, DropdownItem } from "@/types/api";

export const clientsApi = {
  list: async (): Promise<Client[]> => {
    const response = await apiClient.get<ApiResponse<Client[]>>(
      "/modules/tasks/records",
      { params: { resource: "clients" } }
    );
    return response.data.data;
  },

  get: async (id: number): Promise<Client> => {
    const response = await apiClient.get<ApiResponse<Client>>(
      `/modules/tasks/records/${id}`,
      { params: { resource: "clients" } }
    );
    return response.data.data;
  },

  create: async (data: ClientFormData): Promise<Client> => {
    const response = await apiClient.post<ApiResponse<Client>>(
      "/modules/tasks/records",
      data,
      { params: { resource: "clients" } }
    );
    return response.data.data;
  },

  update: async (id: number, data: Partial<ClientFormData>): Promise<Client> => {
    const response = await apiClient.patch<ApiResponse<Client>>(
      `/modules/tasks/records/${id}`,
      data,
      { params: { resource: "clients" } }
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/modules/tasks/records/${id}`, {
      params: { resource: "clients" },
    });
  },

  getDropdown: async (): Promise<DropdownItem[]> => {
    const response = await apiClient.get<ApiResponse<DropdownItem[]>>(
      "/modules/tasks/dropdown/clients"
    );
    return response.data.data || [];
  },
};





