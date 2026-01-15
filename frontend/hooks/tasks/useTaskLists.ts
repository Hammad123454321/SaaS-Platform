import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { taskListsApi, TaskListFormData } from "@/lib/api/taskLists";
import { toast } from "sonner";

export function useTaskLists(projectId?: number) {
  return useQuery({
    queryKey: ["task-lists", projectId],
    queryFn: () => taskListsApi.list(projectId),
  });
}

export function useCreateTaskList() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: TaskListFormData) => taskListsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["task-lists"] });
      toast.success("Task list created successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create task list");
    },
  });
}

export function useUpdateTaskList() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<TaskListFormData> }) =>
      taskListsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["task-lists"] });
      toast.success("Task list updated successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to update task list");
    },
  });
}

export function useDeleteTaskList() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => taskListsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["task-lists"] });
      toast.success("Task list deleted successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to delete task list");
    },
  });
}

