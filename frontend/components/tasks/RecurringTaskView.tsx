"use client";

import { useState } from "react";
import { Plus, Edit, Trash2, Repeat } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { useRecurringTask, useCreateRecurringTask, useUpdateRecurringTask, useDeleteRecurringTask } from "@/hooks/tasks/useRecurringTasks";
import { RecurringTaskFormData } from "@/lib/api/recurringTasks";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";
import { ConfirmDialog } from "@/components/shared/ConfirmDialog";

interface RecurringTaskViewProps {
  taskId: number;
}

export function RecurringTaskView({ taskId }: RecurringTaskViewProps) {
  const { data: recurringTask, isLoading } = useRecurringTask(taskId);
  const createRecurring = useCreateRecurringTask();
  const updateRecurring = useUpdateRecurringTask();
  const deleteRecurring = useDeleteRecurringTask();
  const { canUpdateTask } = useTaskAccess();

  const [showForm, setShowForm] = useState(false);
  const [deletingRecurring, setDeletingRecurring] = useState(false);
  const [formData, setFormData] = useState<Partial<RecurringTaskFormData>>({
    recurrence_pattern: "weekly",
    recurrence_interval: 1,
    recurrence_days: [],
    recurrence_end_date: "",
    recurrence_count: undefined,
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (recurringTask) {
      await updateRecurring.mutateAsync({ taskId, data: formData });
    } else {
      await createRecurring.mutateAsync({ taskId, data: formData as RecurringTaskFormData });
    }
    setShowForm(false);
    resetForm();
  };

  const resetForm = () => {
    if (recurringTask) {
      setFormData({
        recurrence_pattern: recurringTask.recurrence_pattern,
        recurrence_interval: recurringTask.recurrence_interval,
        recurrence_days: recurringTask.recurrence_days,
        recurrence_end_date: recurringTask.recurrence_end_date,
        recurrence_count: recurringTask.recurrence_count,
      });
    } else {
      setFormData({
        recurrence_pattern: "weekly",
        recurrence_interval: 1,
        recurrence_days: [],
        recurrence_end_date: "",
        recurrence_count: undefined,
      });
    }
  };

  const handleEdit = () => {
    if (recurringTask) {
      setFormData({
        recurrence_pattern: recurringTask.recurrence_pattern,
        recurrence_interval: recurringTask.recurrence_interval,
        recurrence_days: recurringTask.recurrence_days,
        recurrence_end_date: recurringTask.recurrence_end_date,
        recurrence_count: recurringTask.recurrence_count,
      });
    }
    setShowForm(true);
  };

  const handleDelete = async () => {
    await deleteRecurring.mutateAsync(taskId);
    setDeletingRecurring(false);
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-400">Loading...</div>;
  }

  return (
    <div className="space-y-4">
      {recurringTask ? (
        <div className="glass rounded-lg p-4 space-y-3">
          <div className="flex items-start justify-between">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-2">
                <Repeat className="h-5 w-5 text-gray-400" />
                <h3 className="font-semibold text-white">Recurring Task</h3>
              </div>
              <div className="space-y-2 text-sm">
                <div>
                  <span className="text-gray-400">Pattern: </span>
                  <span className="text-white capitalize">{recurringTask.recurrence_pattern}</span>
                </div>
                <div>
                  <span className="text-gray-400">Interval: </span>
                  <span className="text-white">
                    Every {recurringTask.recurrence_interval}{" "}
                    {recurringTask.recurrence_pattern === "weekly"
                      ? "week(s)"
                      : recurringTask.recurrence_pattern === "monthly"
                      ? "month(s)"
                      : "day(s)"}
                  </span>
                </div>
                {recurringTask.next_occurrence && (
                  <div>
                    <span className="text-gray-400">Next Occurrence: </span>
                    <span className="text-white">
                      {new Date(recurringTask.next_occurrence).toLocaleDateString()}
                    </span>
                  </div>
                )}
                {recurringTask.recurrence_end_date && (
                  <div>
                    <span className="text-gray-400">Ends: </span>
                    <span className="text-white">
                      {new Date(recurringTask.recurrence_end_date).toLocaleDateString()}
                    </span>
                  </div>
                )}
                {!recurringTask.is_active && (
                  <span className="text-xs text-red-400">Inactive</span>
                )}
              </div>
            </div>
            {canUpdateTask && (
              <div className="flex gap-1">
                <Button variant="ghost" size="icon" onClick={handleEdit} className="h-8 w-8">
                  <Edit className="h-4 w-4" />
                </Button>
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={() => setDeletingRecurring(true)}
                  className="h-8 w-8 text-red-400"
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            )}
          </div>
        </div>
      ) : (
        <div className="glass rounded-lg p-12 text-center">
          <Repeat className="h-12 w-12 mx-auto mb-4 text-gray-400" />
          <p className="text-gray-400 mb-4">This task is not recurring.</p>
          {canUpdateTask && (
            <Button onClick={() => { setShowForm(true); resetForm(); }} className="glass">
              <Plus className="h-4 w-4 mr-2" />
              Make Recurring
            </Button>
          )}
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="glass max-w-md">
          <DialogHeader>
            <DialogTitle className="text-white">
              {recurringTask ? "Edit Recurring Task" : "Create Recurring Task"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-200/80">Pattern *</label>
              <Select
                value={formData.recurrence_pattern || "weekly"}
                onValueChange={(value) => setFormData({ ...formData, recurrence_pattern: value as any })}
                required
              >
                <SelectTrigger className="mt-1 bg-white/5 border-white/10 text-white">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="daily">Daily</SelectItem>
                  <SelectItem value="weekly">Weekly</SelectItem>
                  <SelectItem value="monthly">Monthly</SelectItem>
                  <SelectItem value="yearly">Yearly</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="text-sm text-gray-200/80">Interval *</label>
              <Input
                type="number"
                min="1"
                className="mt-1 bg-white/5 border-white/10 text-white"
                value={formData.recurrence_interval || 1}
                onChange={(e) => setFormData({ ...formData, recurrence_interval: parseInt(e.target.value) || 1 })}
                required
              />
            </div>
            <div>
              <label className="text-sm text-gray-200/80">End Date (Optional)</label>
              <Input
                type="date"
                className="mt-1 bg-white/5 border-white/10 text-white"
                value={formData.recurrence_end_date || ""}
                onChange={(e) => setFormData({ ...formData, recurrence_end_date: e.target.value })}
              />
            </div>
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="glass" disabled={createRecurring.isPending || updateRecurring.isPending}>
                {recurringTask ? "Update" : "Create"}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={deletingRecurring}
        onOpenChange={setDeletingRecurring}
        onConfirm={handleDelete}
        title="Delete Recurring Task"
        description="Are you sure you want to remove the recurring pattern from this task?"
      />
    </div>
  );
}

