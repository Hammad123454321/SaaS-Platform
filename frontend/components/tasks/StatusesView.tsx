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
    });
  };

  const handleEdit = (status: any) => {
    setEditingStatus(status);
    setFormData({
      title: status.name || status.title,
      color: status.color,
      category: status.category,
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
    return <div className="text-center py-12 text-gray-500">Loading statuses...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-gray-900">Task Statuses</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingStatus(null); resetForm(); }} className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700">
            <Plus className="h-4 w-4 mr-2" />
            Create Status
          </Button>
        )}
      </div>

      {statuses && statuses.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {statuses.map((status) => (
            <div key={status.id} className="bg-white rounded-lg p-4 space-y-2 border border-gray-200 shadow-sm">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <TaskStatusBadge name={status.name || status.title} color={status.color} />
                  <p className="text-xs text-gray-500 mt-2 capitalize">{status.category}</p>
                </div>
                {canCreateProject && (
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleEdit(status)}
                      className="h-8 w-8 text-gray-600 hover:text-purple-600 hover:bg-purple-50"
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeletingStatus(status)}
                      className="h-8 w-8 text-gray-600 hover:text-red-600 hover:bg-red-50"
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
        <div className="bg-white rounded-lg p-12 text-center border border-gray-200">
          <div className="h-16 w-16 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-4">
            <ListTodo className="h-8 w-8 text-purple-600" />
          </div>
          <p className="text-gray-500">No statuses yet. Create your first status to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="bg-white max-w-md">
          <DialogHeader>
            <DialogTitle className="text-gray-900">
              {editingStatus ? "Edit Status" : "Create Status"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-600 font-medium">Status Name *</label>
              <Input
                className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                value={formData.title || ""}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                required
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-sm text-gray-600 font-medium">Category *</label>
                <Select
                  value={formData.category || "todo"}
                  onValueChange={(value) => setFormData({ ...formData, category: value as any })}
                >
                  <SelectTrigger className="mt-1 bg-white border-gray-300 text-gray-900">
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
                <label className="text-sm text-gray-600 font-medium">Color</label>
                <Input
                  type="color"
                  className="mt-1 h-10 bg-white border-gray-300"
                  value={formData.color || "#6b7280"}
                  onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                />
              </div>
            </div>
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700" disabled={createStatus.isPending || updateStatus.isPending}>
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

