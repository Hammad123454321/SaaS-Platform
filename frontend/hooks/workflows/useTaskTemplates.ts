import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  createTaskTemplate,
  getTaskTemplates,
  updateTaskTemplate,
  deleteTaskTemplate,
  createTaskFromTemplate,
  TaskTemplateCreate,
} from "@/lib/api/workflows";
import { toast } from "sonner";

export const useTaskTemplates = (templateType?: string) => {
  return useQuery({
    queryKey: ["task-templates", templateType],
    queryFn: () => getTaskTemplates(templateType),
  });
};

export const useCreateTaskTemplate = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: createTaskTemplate,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["task-templates"] });
      toast.success("Task template created successfully");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create task template");
    },
  });
};

export const useUpdateTaskTemplate = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ templateId, data }: { templateId: number; data: TaskTemplateCreate }) =>
      updateTaskTemplate(templateId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["task-templates"] });
      toast.success("Task template updated successfully");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to update task template");
    },
  });
};

export const useDeleteTaskTemplate = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: deleteTaskTemplate,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["task-templates"] });
      toast.success("Task template deleted successfully");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to delete task template");
    },
  });
};

export const useCreateTaskFromTemplate = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ templateId, projectId, title, description }: {
      templateId: number;
      projectId: number;
      title?: string;
      description?: string;
    }) => createTaskFromTemplate(templateId, projectId, title, description),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["tasks"] });
      toast.success("Task created from template successfully");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create task from template");
    },
  });
};

