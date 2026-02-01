import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { tasksApi } from "@/lib/api/tasks";
import { toast } from "sonner";

export function useKanban(projectId?: string) {
  return useQuery({
    queryKey: ["kanban", projectId],
    queryFn: () => tasksApi.getKanban(projectId),
    staleTime: 30 * 1000,
  });
}

export function useMoveTask() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ taskId, statusId }: { taskId: string; statusId: string }) =>
      tasksApi.moveTask(taskId, statusId),
    onMutate: async ({ taskId, statusId }) => {
      // Cancel outgoing refetches
      await queryClient.cancelQueries({ queryKey: ["kanban"] });

      // Snapshot previous value
      const previousKanban = queryClient.getQueryData(["kanban"]);

      // Optimistically update
      queryClient.setQueryData(["kanban"], (old: any) => {
        if (!old) return old;
        // Move task logic here
        return old;
      });

      return { previousKanban };
    },
    onError: (err, variables, context) => {
      // Rollback on error
      if (context?.previousKanban) {
        queryClient.setQueryData(["kanban"], context.previousKanban);
      }
      toast.error("Failed to move task");
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ["kanban"] });
    },
  });
}





