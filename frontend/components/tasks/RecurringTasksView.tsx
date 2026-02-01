"use client";

import { useState } from "react";
import { Plus, Edit, Trash2, Repeat } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { useRecurringTasks, useCreateRecurringTask, useUpdateRecurringTask, useDeleteRecurringTask } from "@/hooks/tasks/useRecurringTasks";
import { useTasks } from "@/hooks/tasks/useTasks";
import { RecurringTaskFormData } from "@/lib/api/recurringTasks";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";
import { ConfirmDialog } from "@/components/shared/ConfirmDialog";

export function RecurringTasksView() {
  const { data: recurringTasks, isLoading } = useRecurringTasks();
  const { data: tasks } = useTasks();
  const createRecurring = useCreateRecurringTask();
  const updateRecurring = useUpdateRecurringTask();
  const deleteRecurring = useDeleteRecurringTask();
  const { canUpdateTask } = useTaskAccess();

  const [showForm, setShowForm] = useState(false);
  const [editingRecurring, setEditingRecurring] = useState<any>(null);
  const [deletingRecurring, setDeletingRecurring] = useState<any>(null);
  const [formData, setFormData] = useState<Partial<RecurringTaskFormData & { task_id: string }>>({
    task_id: undefined,
    recurrence_pattern: "weekly",
    recurrence_interval: 1,
    recurrence_days: [],
    recurrence_end_date: "",
    recurrence_count: undefined,
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.task_id) {
      alert("Please select a task");
      return;
    }
    const { task_id, ...recurringData } = formData;
    if (editingRecurring) {
      await updateRecurring.mutateAsync({ taskId: editingRecurring.task_id, data: recurringData });
    } else {
      await createRecurring.mutateAsync({ taskId: task_id!, data: recurringData as RecurringTaskFormData });
    }
    setShowForm(false);
    setEditingRecurring(null);
    resetForm();
  };

  const resetForm = () => {
    setFormData({
      task_id: undefined,
      recurrence_pattern: "weekly",
      recurrence_interval: 1,
      recurrence_days: [],
      recurrence_end_date: "",
      recurrence_count: undefined,
    });
  };

  const handleEdit = (recurring: any) => {
    setEditingRecurring(recurring);
    setFormData({
      task_id: recurring.task_id,
      recurrence_pattern: recurring.recurrence_pattern,
      recurrence_interval: recurring.recurrence_interval,
      recurrence_days: recurring.recurrence_days,
      recurrence_end_date: recurring.recurrence_end_date,
      recurrence_count: recurring.recurrence_count,
    });
    setShowForm(true);
  };

  const handleDelete = async () => {
    if (deletingRecurring) {
      await deleteRecurring.mutateAsync(deletingRecurring.task_id);
      setDeletingRecurring(null);
    }
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-400">Loading recurring tasks...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-white">Recurring Tasks</h2>
        {canUpdateTask && (
          <Button onClick={() => { setShowForm(true); setEditingRecurring(null); resetForm(); }} className="glass">
            <Plus className="h-4 w-4 mr-2" />
            Create Recurring Task
          </Button>
        )}
      </div>

      {recurringTasks && recurringTasks.length > 0 ? (
        <div className="space-y-2">
          {recurringTasks.map((recurring) => {
            const task = tasks?.find(t => t.id === recurring.task_id);
            return (
              <div key={recurring.id} className="glass rounded-lg p-4 space-y-2">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <Repeat className="h-4 w-4 text-gray-400" />
                      <h3 className="font-semibold text-white">{task?.title || `Task #${recurring.task_id}`}</h3>
                    </div>
                    <p className="text-sm text-gray-300/80 mt-1">
                      {recurring.recurrence_pattern} (every {recurring.recurrence_interval} {recurring.recurrence_pattern === "weekly" ? "week(s)" : recurring.recurrence_pattern === "monthly" ? "month(s)" : "day(s)"})
                    </p>
                    {recurring.next_occurrence && (
                      <p className="text-xs text-gray-400 mt-1">
                        Next: {new Date(recurring.next_occurrence).toLocaleDateString()}
                      </p>
                    )}
                    {!recurring.is_active && (
                      <span className="text-xs text-red-400">Inactive</span>
                    )}
                  </div>
                  {canUpdateTask && (
                    <div className="flex gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleEdit(recurring)}
                        className="h-8 w-8"
                      >
                        <Edit className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setDeletingRecurring(recurring)}
                        className="h-8 w-8 text-red-400"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      ) : (
        <div className="glass rounded-lg p-12 text-center">
          <Repeat className="h-12 w-12 mx-auto mb-4 text-gray-400" />
          <p className="text-gray-400">No recurring tasks yet. Create a recurring task to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="glass max-w-md">
          <DialogHeader>
            <DialogTitle className="text-white">
              {editingRecurring ? "Edit Recurring Task" : "Create Recurring Task"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            {!editingRecurring && (
              <div>
                <label className="text-sm text-gray-200/80">Task *</label>
                <Select
                  value={formData.task_id || undefined}
                  onValueChange={(value) => setFormData({ ...formData, task_id: value })}
                  required
                >
                  <SelectTrigger className="mt-1 bg-white/5 border-white/10 text-white">
                    <SelectValue placeholder="Select task" />
                  </SelectTrigger>
                  <SelectContent>
                    {tasks?.map((task) => (
                      <SelectItem key={task.id} value={task.id}>
                        {task.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
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
                {editingRecurring ? "Update" : "Create"}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deletingRecurring}
        onOpenChange={(open) => !open && setDeletingRecurring(null)}
        onConfirm={handleDelete}
        title="Delete Recurring Task"
        description="Are you sure you want to delete this recurring task?"
      />
    </div>
  );
}

