import { z } from "zod";

export const taskFormSchema = z.object({
  title: z.string().min(1, "Title is required").max(200, "Title is too long"),
  description: z.string().max(5000, "Description is too long").optional(),
  project_id: z.string().min(1, "Project is required"),
  status_id: z.string().min(1, "Status is required"),
  priority_id: z.string().optional(),
  due_date: z.string().optional(),
  start_date: z.string().optional(),
  user_id: z.array(z.string()).optional(),
  completion_percentage: z.number().min(0).max(100).optional(),
});

export type TaskFormData = z.infer<typeof taskFormSchema>;





