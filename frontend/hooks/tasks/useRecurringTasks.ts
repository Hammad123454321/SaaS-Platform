import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { recurringTasksApi, RecurringTaskFormData } from "@/lib/api/recurringTasks";
import { toast } from "sonner";

export function useRecurringTask(taskId: string) {
  return useQuery({
    queryKey: ["recurring-task", taskId],
    queryFn: () => recurringTasksApi.get(taskId),
    enabled: !!taskId,
  });
}

export function useRecurringTasks() {
  return useQuery({
    queryKey: ["recurring-tasks"],
    queryFn: () => recurringTasksApi.list(),
  });
}

export function useCreateRecurringTask() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ taskId, data }: { taskId: string; data: RecurringTaskFormData }) =>
      recurringTasksApi.create(taskId, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["recurring-task", variables.taskId] });
      queryClient.invalidateQueries({ queryKey: ["recurring-tasks"] });
      toast.success("Recurring task created successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create recurring task");
    },
  });
}

export function useUpdateRecurringTask() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ taskId, data }: { taskId: string; data: Partial<RecurringTaskFormData> }) =>
      recurringTasksApi.update(taskId, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["recurring-task", variables.taskId] });
      queryClient.invalidateQueries({ queryKey: ["recurring-tasks"] });
      toast.success("Recurring task updated successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to update recurring task");
    },
  });
}

export function useDeleteRecurringTask() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (taskId: string) => recurringTasksApi.delete(taskId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["recurring-tasks"] });
      toast.success("Recurring task deleted successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to delete recurring task");
    },
  });
}

