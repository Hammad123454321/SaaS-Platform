import { useQuery } from "@tanstack/react-query";
import { taskMetricsApi } from "@/lib/api/taskMetrics";

export function useTaskMetrics() {
  return useQuery({
    queryKey: ["task-metrics"],
    queryFn: () => taskMetricsApi.getDashboardMetrics(),
  });
}

export function useEmployeeProgress() {
  return useQuery({
    queryKey: ["employee-progress"],
    queryFn: () => taskMetricsApi.getEmployeeProgress(),
  });
}

