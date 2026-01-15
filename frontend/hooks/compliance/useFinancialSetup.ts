import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { createFinancialSetup, getFinancialSetup, confirmFinancialSetup, FinancialSetupCreate } from "@/lib/api/compliance";
import { toast } from "sonner";

export const useFinancialSetup = () => {
  return useQuery({
    queryKey: ["financial-setup"],
    queryFn: getFinancialSetup,
  });
};

export const useCreateFinancialSetup = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: createFinancialSetup,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["financial-setup"] });
      toast.success("Financial setup saved successfully");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to save financial setup");
    },
  });
};

export const useConfirmFinancialSetup = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: confirmFinancialSetup,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["financial-setup"] });
      toast.success("Financial setup confirmed successfully");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to confirm financial setup");
    },
  });
};

