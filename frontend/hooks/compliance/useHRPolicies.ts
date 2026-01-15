import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { getHRPolicies, acknowledgeHRPolicies, getAcknowledgementStatus, getHRPoliciesForInvitation } from "@/lib/api/compliance";
import { toast } from "sonner";

export const useHRPolicies = () => {
  return useQuery({
    queryKey: ["hr-policies"],
    queryFn: getHRPolicies,
  });
};

export const useHRPoliciesForInvitation = (token: string) => {
  return useQuery({
    queryKey: ["hr-policies-invitation", token],
    queryFn: () => getHRPoliciesForInvitation(token),
    enabled: !!token,
  });
};

export const useAcknowledgeHRPolicies = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: acknowledgeHRPolicies,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["hr-policies"] });
      queryClient.invalidateQueries({ queryKey: ["acknowledgement-status"] });
      toast.success("HR policies acknowledged successfully");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to acknowledge HR policies");
    },
  });
};

export const useAcknowledgementStatus = () => {
  return useQuery({
    queryKey: ["acknowledgement-status"],
    queryFn: getAcknowledgementStatus,
  });
};

