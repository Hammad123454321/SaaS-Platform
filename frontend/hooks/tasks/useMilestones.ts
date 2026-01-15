import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { milestonesApi, MilestoneFormData } from "@/lib/api/milestones";
import { toast } from "sonner";

export function useMilestones(projectId?: number) {
  return useQuery({
    queryKey: ["milestones", projectId],
    queryFn: () => milestonesApi.list(projectId),
  });
}

export function useMilestone(id: number) {
  return useQuery({
    queryKey: ["milestone", id],
    queryFn: () => milestonesApi.get(id),
    enabled: !!id,
  });
}

export function useCreateMilestone() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: MilestoneFormData) => milestonesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["milestones"] });
      toast.success("Milestone created successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create milestone");
    },
  });
}

export function useUpdateMilestone() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<MilestoneFormData> }) =>
      milestonesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["milestones"] });
      toast.success("Milestone updated successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to update milestone");
    },
  });
}

export function useDeleteMilestone() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => milestonesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["milestones"] });
      toast.success("Milestone deleted successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to delete milestone");
    },
  });
}

