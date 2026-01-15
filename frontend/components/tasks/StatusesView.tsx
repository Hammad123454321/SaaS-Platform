"use client";

import { useState } from "react";
import { Plus, Edit, Trash2, ListTodo } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useStatuses, useCreateStatus, useUpdateStatus, useDeleteStatus } from "@/hooks/tasks/useStatuses";
import { StatusFormData } from "@/types/status";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";
import { ConfirmDialog } from "@/components/shared/ConfirmDialog";
import { TaskStatusBadge } from "./TaskStatusBadge";

export function StatusesView() {
  const { data: statuses, isLoading } = useStatuses();
  const createStatus = useCreateStatus();
  const updateStatus = useUpdateStatus();
  const deleteStatus = useDeleteStatus();
  const { canCreateProject } = useTaskAccess();

  const [showForm, setShowForm] = useState(false);
  const [editingStatus, setEditingStatus] = useState<any>(null);
  const [deletingStatus, setDeletingStatus] = useState<any>(null);
  const [formData, setFormData] = useState<Partial<StatusFormData>>({
    title: "",
    color: "#6b7280",
    category: "todo",
    display_order: 0,
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (editingStatus) {
      await updateStatus.mutateAsync({ id: editingStatus.id, data: formData });
    } else {
      await createStatus.mutateAsync(formData as StatusFormData);
    }
    setShowForm(false);
    setEditingStatus(null);
    resetForm();
  };

  const resetForm = () => {
    setFormData({
      title: "",
      color: "#6b7280",
      category: "todo",
      display_order: 0,
    });
  };

  const handleEdit = (status: any) => {
    setEditingStatus(status);
    setFormData({
      title: status.name || status.title,
      color: status.color,
      category: status.category,
      display_order: status.display_order,
    });
    setShowForm(true);
  };

  const handleDelete = async () => {
    if (deletingStatus) {
      await deleteStatus.mutateAsync(deletingStatus.id);
      setDeletingStatus(null);
    }
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-400">Loading statuses...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-white">Task Statuses</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingStatus(null); resetForm(); }} className="glass">
            <Plus className="h-4 w-4 mr-2" />
            Create Status
          </Button>
        )}
      </div>

      {statuses && statuses.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {statuses.map((status) => (
            <div key={status.id} className="glass rounded-lg p-4 space-y-2">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <TaskStatusBadge name={status.name || status.title} color={status.color} />
                  <p className="text-xs text-gray-400 mt-2 capitalize">{status.category}</p>
                </div>
                {canCreateProject && (
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleEdit(status)}
                      className="h-8 w-8"
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeletingStatus(status)}
                      className="h-8 w-8 text-red-400"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="glass rounded-lg p-12 text-center">
          <ListTodo className="h-12 w-12 mx-auto mb-4 text-gray-400" />
          <p className="text-gray-400">No statuses yet. Create your first status to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="glass max-w-md">
          <DialogHeader>
            <DialogTitle className="text-white">
              {editingStatus ? "Edit Status" : "Create Status"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-200/80">Status Name *</label>
              <Input
                className="mt-1 bg-white/5 border-white/10 text-white"
                value={formData.title || ""}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                required
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-sm text-gray-200/80">Category *</label>
                <Select
                  value={formData.category || "todo"}
                  onValueChange={(value) => setFormData({ ...formData, category: value as any })}
                >
                  <SelectTrigger className="mt-1 bg-white/5 border-white/10 text-white">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="todo">To Do</SelectItem>
                    <SelectItem value="in_progress">In Progress</SelectItem>
                    <SelectItem value="done">Done</SelectItem>
                    <SelectItem value="cancelled">Cancelled</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Color</label>
                <Input
                  type="color"
                  className="mt-1 h-10 bg-white/5 border-white/10"
                  value={formData.color || "#6b7280"}
                  onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                />
              </div>
            </div>
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="glass" disabled={createStatus.isPending || updateStatus.isPending}>
                {editingStatus ? "Update" : "Create"}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deletingStatus}
        onOpenChange={(open) => !open && setDeletingStatus(null)}
        onConfirm={handleDelete}
        title="Delete Status"
        description={`Are you sure you want to delete "${deletingStatus?.name || deletingStatus?.title}"?`}
      />
    </div>
  );
}

