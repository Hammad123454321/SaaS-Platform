import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { prioritiesApi } from "@/lib/api/priorities";
import { PriorityFormData } from "@/types/priority";
import { toast } from "sonner";

export function usePriorities() {
  return useQuery({
    queryKey: ["priorities"],
    queryFn: () => prioritiesApi.list(),
  });
}

export function useCreatePriority() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: PriorityFormData) => prioritiesApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["priorities"] });
      toast.success("Priority created successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create priority");
    },
  });
}

export function useUpdatePriority() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<PriorityFormData> }) =>
      prioritiesApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["priorities"] });
      toast.success("Priority updated successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to update priority");
    },
  });
}

export function useDeletePriority() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => prioritiesApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["priorities"] });
      queryClient.invalidateQueries({ queryKey: ["tasks"] });
      toast.success("Priority deleted successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to delete priority");
    },
  });
}

