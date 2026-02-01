import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { taskFormSchema, TaskFormData } from "@/lib/schemas/task";
import { useCreateTask } from "./useCreateTask";
import { useUpdateTask } from "./useUpdateTask";

export function useTaskForm(taskId?: string) {
  const createTask = useCreateTask();
  const updateTask = useUpdateTask();

  const form = useForm<TaskFormData>({
    resolver: zodResolver(taskFormSchema),
    defaultValues: {
      title: "",
      description: "",
      project_id: "",
      status_id: "",
      priority_id: undefined,
      due_date: undefined,
      start_date: undefined,
      user_id: [],
      completion_percentage: 0,
    },
  });

  const onSubmit = async (data: TaskFormData) => {
    if (taskId) {
      await updateTask.mutateAsync({ id: taskId, data });
    } else {
      await createTask.mutateAsync(data);
    }
  };

  return {
    form,
    onSubmit: form.handleSubmit(onSubmit),
    isLoading: createTask.isPending || updateTask.isPending,
  };
}





