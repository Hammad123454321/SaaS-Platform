"use client";

import { useState } from "react";
import { Plus, Edit, Trash2, FileText } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { usePriorities, useCreatePriority, useUpdatePriority, useDeletePriority } from "@/hooks/tasks/usePriorities";
import { PriorityFormData } from "@/types/priority";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";
import { ConfirmDialog } from "@/components/shared/ConfirmDialog";
import { TaskPriorityBadge } from "./TaskPriorityBadge";

export function PrioritiesView() {
  const { data: priorities, isLoading } = usePriorities();
  const createPriority = useCreatePriority();
  const updatePriority = useUpdatePriority();
  const deletePriority = useDeletePriority();
  const { canCreateProject } = useTaskAccess();

  const [showForm, setShowForm] = useState(false);
  const [editingPriority, setEditingPriority] = useState<any>(null);
  const [deletingPriority, setDeletingPriority] = useState<any>(null);
  const [formData, setFormData] = useState<Partial<PriorityFormData>>({
    title: "",
    color: "#6b7280",
    level: 0,
    display_order: 0,
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (editingPriority) {
      await updatePriority.mutateAsync({ id: editingPriority.id, data: formData });
    } else {
      await createPriority.mutateAsync(formData as PriorityFormData);
    }
    setShowForm(false);
    setEditingPriority(null);
    resetForm();
  };

  const resetForm = () => {
    setFormData({
      title: "",
      color: "#6b7280",
      level: 0,
      display_order: 0,
    });
  };

  const handleEdit = (priority: any) => {
    setEditingPriority(priority);
    setFormData({
      title: priority.name || priority.title,
      color: priority.color,
      level: priority.level,
      display_order: priority.display_order,
    });
    setShowForm(true);
  };

  const handleDelete = async () => {
    if (deletingPriority) {
      await deletePriority.mutateAsync(deletingPriority.id);
      setDeletingPriority(null);
    }
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-500">Loading priorities...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-gray-900">Task Priorities</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingPriority(null); resetForm(); }} className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700">
            <Plus className="h-4 w-4 mr-2" />
            Create Priority
          </Button>
        )}
      </div>

      {priorities && priorities.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {priorities.map((priority) => (
            <div key={priority.id} className="bg-white rounded-lg p-4 space-y-2 border border-gray-200 shadow-sm">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <TaskPriorityBadge name={priority.name || priority.title} color={priority.color} />
                  <p className="text-xs text-gray-500 mt-2">Level: {priority.level}</p>
                </div>
                {canCreateProject && (
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleEdit(priority)}
                      className="h-8 w-8 text-gray-600 hover:text-purple-600 hover:bg-purple-50"
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeletingPriority(priority)}
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
            <FileText className="h-8 w-8 text-purple-600" />
          </div>
          <p className="text-gray-500">No priorities yet. Create your first priority to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="bg-white max-w-md">
          <DialogHeader>
            <DialogTitle className="text-gray-900">
              {editingPriority ? "Edit Priority" : "Create Priority"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-600 font-medium">Priority Name *</label>
              <Input
                className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                value={formData.title || ""}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                required
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-sm text-gray-600 font-medium">Level</label>
                <Input
                  type="number"
                  className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                  value={formData.level || 0}
                  onChange={(e) => setFormData({ ...formData, level: parseInt(e.target.value) || 0 })}
                />
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
              <Button type="submit" className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700" disabled={createPriority.isPending || updatePriority.isPending}>
                {editingPriority ? "Update" : "Create"}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deletingPriority}
        onOpenChange={(open) => !open && setDeletingPriority(null)}
        onConfirm={handleDelete}
        title="Delete Priority"
        description={`Are you sure you want to delete "${deletingPriority?.name || deletingPriority?.title}"?`}
      />
    </div>
  );
}

