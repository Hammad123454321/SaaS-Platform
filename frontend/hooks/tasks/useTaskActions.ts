import { useMutation, useQueryClient } from "@tanstack/react-query";
import { tasksApi } from "@/lib/api/tasks";
import { toast } from "sonner";

export function useTaskActions() {
  const queryClient = useQueryClient();

  const toggleFavorite = useMutation({
    mutationFn: ({ taskId, isFavorite }: { taskId: number; isFavorite: boolean }) =>
      tasksApi.toggleFavorite(taskId, isFavorite),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["tasks"] });
      queryClient.invalidateQueries({ queryKey: ["task", variables.taskId] });
      queryClient.invalidateQueries({ queryKey: ["kanban"] });
      toast.success(
        variables.isFavorite ? "Removed from favorites" : "Added to favorites"
      );
    },
  });

  const togglePinned = useMutation({
    mutationFn: ({ taskId, isPinned }: { taskId: number; isPinned: boolean }) =>
      tasksApi.togglePinned(taskId, isPinned),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["tasks"] });
      queryClient.invalidateQueries({ queryKey: ["task", variables.taskId] });
      queryClient.invalidateQueries({ queryKey: ["kanban"] });
      toast.success(variables.isPinned ? "Unpinned" : "Pinned");
    },
  });

  const duplicate = useMutation({
    mutationFn: (taskId: number) => tasksApi.duplicate(taskId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["tasks"] });
      queryClient.invalidateQueries({ queryKey: ["kanban"] });
      toast.success("Task duplicated successfully!");
    },
  });

  return {
    toggleFavorite,
    togglePinned,
    duplicate,
  };
}





