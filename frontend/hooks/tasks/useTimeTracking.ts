import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { timeTrackingApi, TimeEntryFormData } from "@/lib/api/timeTracking";
import { toast } from "sonner";

export function useTimeEntries(taskId?: string, userId?: string) {
  return useQuery({
    queryKey: ["time-entries", taskId, userId],
    queryFn: () => timeTrackingApi.listEntries(taskId, userId),
  });
}

export function useActiveTracker() {
  return useQuery({
    queryKey: ["time-tracker", "active"],
    queryFn: () => timeTrackingApi.getActiveTracker(),
    refetchInterval: 1000, // Poll every second for active tracker
  });
}

export function useStartTracker() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ taskId, message }: { taskId: string; message?: string }) =>
      timeTrackingApi.startTracker(taskId, message),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["time-tracker"] });
      queryClient.invalidateQueries({ queryKey: ["time-entries"] });
      toast.success("Time tracker started!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to start tracker");
    },
  });
}

export function useStopTracker() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (trackerId: string) => timeTrackingApi.stopTracker(trackerId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["time-tracker"] });
      queryClient.invalidateQueries({ queryKey: ["time-entries"] });
      toast.success("Time tracker stopped!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to stop tracker");
    },
  });
}

export function useCreateTimeEntry() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: TimeEntryFormData) => timeTrackingApi.createEntry(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["time-entries"] });
      toast.success("Time entry created successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create time entry");
    },
  });
}

export function useTimeReports(startDate?: string, endDate?: string) {
  return useQuery({
    queryKey: ["time-reports", startDate, endDate],
    queryFn: () => timeTrackingApi.getReports(startDate, endDate),
    enabled: !!startDate && !!endDate,
  });
}

