import apiClient from "./client";
import { ApiResponse, DropdownItem } from "@/types/api";

export const usersApi = {
  getDropdown: async (): Promise<DropdownItem[]> => {
    const response = await apiClient.get<ApiResponse<DropdownItem[]>>(
      "/modules/tasks/dropdown/users"
    );
    return response.data.data || [];
  },
};





