import { useQuery } from "@tanstack/react-query";
import { tasksApi } from "@/lib/api/tasks";
import { TaskFilters } from "@/types/task";

export function useTasks(filters?: TaskFilters) {
  return useQuery({
    queryKey: ["tasks", filters],
    queryFn: () => tasksApi.list(filters),
    staleTime: 30 * 1000, // 30 seconds
  });
}





