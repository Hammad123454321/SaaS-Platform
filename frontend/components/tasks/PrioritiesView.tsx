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
    return <div className="text-center py-12 text-gray-400">Loading priorities...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-white">Task Priorities</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingPriority(null); resetForm(); }} className="glass">
            <Plus className="h-4 w-4 mr-2" />
            Create Priority
          </Button>
        )}
      </div>

      {priorities && priorities.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {priorities.map((priority) => (
            <div key={priority.id} className="glass rounded-lg p-4 space-y-2">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <TaskPriorityBadge name={priority.name || priority.title} color={priority.color} />
                  <p className="text-xs text-gray-400 mt-2">Level: {priority.level}</p>
                </div>
                {canCreateProject && (
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleEdit(priority)}
                      className="h-8 w-8"
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeletingPriority(priority)}
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
          <FileText className="h-12 w-12 mx-auto mb-4 text-gray-400" />
          <p className="text-gray-400">No priorities yet. Create your first priority to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="glass max-w-md">
          <DialogHeader>
            <DialogTitle className="text-white">
              {editingPriority ? "Edit Priority" : "Create Priority"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-200/80">Priority Name *</label>
              <Input
                className="mt-1 bg-white/5 border-white/10 text-white"
                value={formData.title || ""}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                required
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-sm text-gray-200/80">Level</label>
                <Input
                  type="number"
                  className="mt-1 bg-white/5 border-white/10 text-white"
                  value={formData.level || 0}
                  onChange={(e) => setFormData({ ...formData, level: parseInt(e.target.value) || 0 })}
                />
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
              <Button type="submit" className="glass" disabled={createPriority.isPending || updatePriority.isPending}>
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

