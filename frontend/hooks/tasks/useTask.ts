import { useQuery } from "@tanstack/react-query";
import { tasksApi } from "@/lib/api/tasks";

export function useTask(id: number | null) {
  return useQuery({
    queryKey: ["task", id],
    queryFn: () => (id ? tasksApi.get(id) : null),
    enabled: !!id,
  });
}





