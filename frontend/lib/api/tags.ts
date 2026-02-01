import apiClient from "./client";
import { ApiResponse } from "@/types/api";

export interface Tag {
  id: string;
  name: string;
  color?: string;
  created_at: string;
  updated_at: string;
}

export interface TagFormData {
  name: string;
  color?: string;
}

export const tagsApi = {
  list: async (): Promise<Tag[]> => {
    const response = await apiClient.get<ApiResponse<Tag[]>>(
      "/modules/tasks/tags"
    );
    return response.data.data;
  },

  create: async (data: TagFormData): Promise<Tag> => {
    const response = await apiClient.post<ApiResponse<Tag>>(
      "/modules/tasks/tags",
      data
    );
    return response.data.data;
  },

  update: async (id: string, data: Partial<TagFormData>): Promise<Tag> => {
    const response = await apiClient.patch<ApiResponse<Tag>>(
      `/modules/tasks/tags/${id}`,
      data
    );
    return response.data.data;
  },

  delete: async (id: string): Promise<void> => {
    await apiClient.delete(`/modules/tasks/tags/${id}`);
  },

  addToTask: async (taskId: string, tagId: string): Promise<void> => {
    await apiClient.post(`/modules/tasks/tasks/${taskId}/tags`, { tag_id: tagId });
  },

  removeFromTask: async (taskId: string, tagId: string): Promise<void> => {
    await apiClient.delete(`/modules/tasks/tasks/${taskId}/tags/${tagId}`);
  },

  getTaskTags: async (taskId: string): Promise<Tag[]> => {
    const response = await apiClient.get<ApiResponse<Tag[]>>(
      `/modules/tasks/tasks/${taskId}/tags`
    );
    return response.data.data;
  },
};

