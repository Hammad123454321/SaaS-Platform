import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { taskDependenciesApi, TaskDependencyFormData } from "@/lib/api/taskDependencies";
import { toast } from "sonner";

export function useTaskDependencies(taskId: string) {
  return useQuery({
    queryKey: ["task-dependencies", taskId],
    queryFn: () => taskDependenciesApi.list(taskId),
    enabled: !!taskId,
  });
}

export function useBlockingTasks(taskId: string) {
  return useQuery({
    queryKey: ["blocking-tasks", taskId],
    queryFn: () => taskDependenciesApi.getBlocking(taskId),
    enabled: !!taskId,
  });
}

export function useCreateTaskDependency() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ taskId, data }: { taskId: string; data: TaskDependencyFormData }) =>
      taskDependenciesApi.create(taskId, data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["task-dependencies", variables.taskId] });
      queryClient.invalidateQueries({ queryKey: ["blocking-tasks", variables.taskId] });
      toast.success("Task dependency created successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create dependency");
    },
  });
}

export function useDeleteTaskDependency() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (dependencyId: string) => taskDependenciesApi.delete(dependencyId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["task-dependencies"] });
      queryClient.invalidateQueries({ queryKey: ["blocking-tasks"] });
      toast.success("Dependency removed successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to delete dependency");
    },
  });
}

