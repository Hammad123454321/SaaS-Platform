import { z } from "zod";

export const projectFormSchema = z.object({
  title: z.string().min(1, "Title is required").max(200, "Title is too long"),
  name: z.string().max(200).optional(),
  description: z.string().max(5000, "Description is too long").optional(),
  client_id: z.string().min(1, "Client is required"),
  budget: z.number().positive().optional(),
  start_date: z.string().optional(),
  end_date: z.string().optional(),
  deadline: z.string().optional(),
  status: z.enum(["active", "completed", "on_hold", "cancelled"]).optional(),
});

export type ProjectFormData = z.infer<typeof projectFormSchema>;





