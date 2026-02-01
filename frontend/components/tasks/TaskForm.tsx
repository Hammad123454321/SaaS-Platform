"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { taskFormSchema } from "@/lib/schemas/task";
import { TaskFormData } from "@/types/task";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { DropdownItem } from "@/types/api";
import { Task } from "@/types/task";
import { Sparkles, Loader2 } from "lucide-react";
import { generateTaskWithAI } from "@/hooks/useDashboard";

interface TaskFormProps {
  task?: Task;
  projects: DropdownItem[];
  statuses: DropdownItem[];
  priorities: DropdownItem[];
  users: DropdownItem[];
  onSubmit: (data: TaskFormData) => void;
  onCancel: () => void;
  isLoading?: boolean;
}

export function TaskForm({
  task,
  projects,
  statuses,
  priorities,
  users,
  onSubmit,
  onCancel,
  isLoading,
}: TaskFormProps) {
  const [isGeneratingAI, setIsGeneratingAI] = useState(false);
  const [aiError, setAiError] = useState<string | null>(null);

  const form = useForm<TaskFormData>({
    resolver: zodResolver(taskFormSchema),
    defaultValues: {
      title: task?.title || "",
      description: task?.description || "",
      project_id: task?.project_id || undefined,
      status_id: task?.status_id || undefined,
      priority_id: task?.priority_id,
      due_date: task?.due_date ? task.due_date.split("T")[0] : undefined,
      start_date: task?.start_date ? task.start_date.split("T")[0] : undefined,
      user_id: task?.user_id || task?.assignees?.map(a => a.id) || [],
      completion_percentage: task?.completion_percentage || 0,
    },
  });

  const handleGenerateWithAI = async () => {
    const title = form.getValues("title");
    if (!title || title.trim().length < 3) {
      setAiError("Please enter a task title first (at least 3 characters)");
      return;
    }

    setIsGeneratingAI(true);
    setAiError(null);

    try {
      // Get project context if selected
      const projectId = form.getValues("project_id");
      const project = projects.find(p => p.id === projectId);
      const context = project ? `Project: ${project.name || project.title}` : "";

      const suggestion = await generateTaskWithAI(title, context);

      // Update form with AI suggestions
      form.setValue("description", suggestion.description);

      // Set suggested priority if available
      if (suggestion.suggested_priority) {
        const priorityMatch = priorities.find(
          p => (p.name || p.title || "").toLowerCase().includes(suggestion.suggested_priority.toLowerCase())
        );
        if (priorityMatch) {
          form.setValue("priority_id", priorityMatch.id);
        }
      }

      // Set suggested due date
      if (suggestion.suggested_due_days > 0) {
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + suggestion.suggested_due_days);
        form.setValue("due_date", dueDate.toISOString().split("T")[0]);
      }
    } catch (err: any) {
      setAiError(err.response?.status === 403 
        ? "AI features not available in your plan"
        : "Failed to generate suggestions. Please try again."
      );
    } finally {
      setIsGeneratingAI(false);
    }
  };

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="title"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Title</FormLabel>
              <div className="flex gap-2">
                <FormControl>
                  <Input placeholder="Task title" {...field} className="flex-1" />
                </FormControl>
                <Button
                  type="button"
                  variant="outline"
                  onClick={handleGenerateWithAI}
                  disabled={isGeneratingAI}
                  className="shrink-0 bg-gradient-to-r from-purple-50 to-violet-50 border-purple-200 hover:border-purple-300 hover:from-purple-100 hover:to-violet-100"
                >
                  {isGeneratingAI ? (
                    <Loader2 className="h-4 w-4 animate-spin text-purple-600" />
                  ) : (
                    <Sparkles className="h-4 w-4 text-purple-600" />
                  )}
                  <span className="ml-2 text-purple-700">AI Fill</span>
                </Button>
              </div>
              <FormMessage />
              {aiError && (
                <p className="text-sm text-amber-600 mt-1">{aiError}</p>
              )}
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="description"
          render={({ field }) => (
            <FormItem>
              <div className="flex items-center justify-between">
                <FormLabel>Description</FormLabel>
                {isGeneratingAI && (
                  <span className="text-xs text-purple-600 flex items-center gap-1">
                    <Loader2 className="h-3 w-3 animate-spin" />
                    Generating...
                  </span>
                )}
              </div>
              <FormControl>
                <Textarea
                  placeholder="Task description (or click AI Fill to generate)"
                  rows={4}
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="grid grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="project_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Project *</FormLabel>
                <Select
                  onValueChange={(value) => field.onChange(value)}
                  value={field.value || undefined}
                >
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Select project" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {projects.filter(p => p.id).map((project) => (
                      <SelectItem key={project.id} value={project.id}>
                        {project.name || project.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="status_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Status *</FormLabel>
                <Select
                  onValueChange={(value) => field.onChange(value)}
                  value={field.value || undefined}
                >
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Select status" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {statuses.filter(s => s.id).map((status) => (
                      <SelectItem key={status.id} value={status.id}>
                        {status.name || status.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="priority_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Priority</FormLabel>
                <Select
                  onValueChange={(value) =>
                    field.onChange(value === "none" ? undefined : value)
                  }
                  value={field.value ?? "none"}
                >
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Select priority" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="none">None</SelectItem>
                    {priorities.filter(p => p.id).map((priority) => (
                      <SelectItem key={priority.id} value={priority.id}>
                        {priority.name || priority.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="completion_percentage"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Completion %</FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    min={0}
                    max={100}
                    {...field}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="start_date"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Start Date</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="due_date"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Due Date</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <FormField
          control={form.control}
          name="user_id"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Assignees</FormLabel>
              {users.length === 0 ? (
                <div className="text-sm text-gray-500 mt-1">
                  No team members available. Add users in User Management.
                </div>
              ) : (
                <div className="mt-2 space-y-2 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3">
                  {users.filter(u => u.id).map((user) => (
                    <label
                      key={user.id}
                      className="flex items-center gap-2 cursor-pointer hover:bg-gray-50 p-2 rounded"
                    >
                      <input
                        type="checkbox"
                        checked={field.value?.includes(user.id) || false}
                        onChange={(e) => {
                          const currentValue = field.value || [];
                          if (e.target.checked) {
                            field.onChange([...currentValue, user.id]);
                          } else {
                            field.onChange(currentValue.filter((id) => id !== user.id));
                          }
                        }}
                        className="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                      />
                      <span className="text-sm text-gray-700">
                        {user.name || user.title || user.email || `User ${user.id}`}
                      </span>
                    </label>
                  ))}
                </div>
              )}
              <FormMessage />
              <p className="text-xs text-gray-500 mt-1">
                Select one or more team members to assign this task to.
              </p>
            </FormItem>
          )}
        />

        <div className="flex justify-end gap-2 pt-4">
          <Button type="button" variant="outline" onClick={onCancel}>
            Cancel
          </Button>
          <Button type="submit" disabled={isLoading}>
            {isLoading ? "Saving..." : task ? "Update" : "Create"}
          </Button>
        </div>
      </form>
    </Form>
  );
}
