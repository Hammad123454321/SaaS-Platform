import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  generateOnboardingTasks,
  getOnboardingTasks,
  createTaskFromOnboardingTask,
} from "@/lib/api/workflows";
import { toast } from "sonner";

export const useOnboardingTasks = () => {
  return useQuery({
    queryKey: ["onboarding-tasks"],
    queryFn: getOnboardingTasks,
  });
};

export const useGenerateOnboardingTasks = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (assignedToUserId?: number) => generateOnboardingTasks(assignedToUserId),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ["onboarding-tasks"] });
      toast.success(`Generated ${data.tasks_generated} onboarding tasks`);
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to generate onboarding tasks");
    },
  });
};

export const useCreateTaskFromOnboardingTask = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ onboardingTaskId, projectId }: { onboardingTaskId: number; projectId: number }) =>
      createTaskFromOnboardingTask(onboardingTaskId, projectId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["onboarding-tasks"] });
      queryClient.invalidateQueries({ queryKey: ["tasks"] });
      toast.success("Task created successfully");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.detail || "Failed to create task");
    },
  });
};

