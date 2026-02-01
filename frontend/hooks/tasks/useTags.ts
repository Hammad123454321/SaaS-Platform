import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { tagsApi, TagFormData } from "@/lib/api/tags";
import { toast } from "sonner";

export function useTags() {
  return useQuery({
    queryKey: ["tags"],
    queryFn: () => tagsApi.list(),
  });
}

export function useCreateTag() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: TagFormData) => tagsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["tags"] });
      toast.success("Tag created successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create tag");
    },
  });
}

export function useUpdateTag() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<TagFormData> }) =>
      tagsApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["tags"] });
      toast.success("Tag updated successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to update tag");
    },
  });
}

export function useDeleteTag() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => tagsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["tags"] });
      toast.success("Tag deleted successfully!");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to delete tag");
    },
  });
}

