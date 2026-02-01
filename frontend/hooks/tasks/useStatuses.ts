import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { statusesApi } from "@/lib/api/statuses";
import { StatusFormData } from "@/types/status";
import { toast } from "sonner";

export function useStatuses() {
  return useQuery({
    queryKey: ["statuses"],
    queryFn: () => statusesApi.list(),
  });
}

export function useCreateStatus() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: StatusFormData) => statusesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["statuses"] });
      toast.success("Status created successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create status");
    },
  });
}

export function useUpdateStatus() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<StatusFormData> }) =>
      statusesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["statuses"] });
      toast.success("Status updated successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to update status");
    },
  });
}

export function useDeleteStatus() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => statusesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["statuses"] });
      queryClient.invalidateQueries({ queryKey: ["tasks"] });
      toast.success("Status deleted successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to delete status");
    },
  });
}

