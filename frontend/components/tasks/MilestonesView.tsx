"use client";

import { useState } from "react";
import { Plus, Edit, Trash2, Target } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useMilestones, useCreateMilestone, useUpdateMilestone, useDeleteMilestone } from "@/hooks/tasks/useMilestones";
import { useProjects } from "@/hooks/tasks/useProjects";
import { MilestoneFormData } from "@/lib/api/milestones";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";
import { ConfirmDialog } from "@/components/shared/ConfirmDialog";

export function MilestonesView() {
  const { data: projects } = useProjects();
  const { data: milestones, isLoading } = useMilestones();
  const createMilestone = useCreateMilestone();
  const updateMilestone = useUpdateMilestone();
  const deleteMilestone = useDeleteMilestone();
  const { canCreateProject } = useTaskAccess();

  const [showForm, setShowForm] = useState(false);
  const [editingMilestone, setEditingMilestone] = useState<any>(null);
  const [deletingMilestone, setDeletingMilestone] = useState<any>(null);
  const [formData, setFormData] = useState<Partial<MilestoneFormData>>({
    name: "",
    description: "",
    project_id: undefined,
    due_date: "",
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.project_id) {
      alert("Please select a project");
      return;
    }
    if (editingMilestone) {
      await updateMilestone.mutateAsync({ id: editingMilestone.id, data: formData });
    } else {
      await createMilestone.mutateAsync(formData as MilestoneFormData);
    }
    setShowForm(false);
    setEditingMilestone(null);
    resetForm();
  };

  const resetForm = () => {
    setFormData({
      name: "",
      description: "",
      project_id: undefined,
      due_date: "",
    });
  };

  const handleEdit = (milestone: any) => {
    setEditingMilestone(milestone);
    setFormData({
      name: milestone.name,
      description: milestone.description,
      project_id: milestone.project_id,
      due_date: milestone.due_date,
    });
    setShowForm(true);
  };

  const handleDelete = async () => {
    if (deletingMilestone) {
      await deleteMilestone.mutateAsync(deletingMilestone.id);
      setDeletingMilestone(null);
    }
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-400">Loading milestones...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-white">Milestones</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingMilestone(null); resetForm(); }} className="glass">
            <Plus className="h-4 w-4 mr-2" />
            Create Milestone
          </Button>
        )}
      </div>

      {milestones && milestones.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {milestones.map((milestone) => (
            <div key={milestone.id} className="glass rounded-lg p-4 space-y-2">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <h3 className="font-semibold text-white">{milestone.name}</h3>
                  {milestone.description && (
                    <p className="text-sm text-gray-300/80 mt-1">{milestone.description}</p>
                  )}
                  {milestone.due_date && (
                    <p className="text-xs text-gray-400 mt-2">
                      Due: {new Date(milestone.due_date).toLocaleDateString()}
                    </p>
                  )}
                  {milestone.completed_at && (
                    <p className="text-xs text-green-400 mt-1">
                      Completed: {new Date(milestone.completed_at).toLocaleDateString()}
                    </p>
                  )}
                </div>
                {canCreateProject && (
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleEdit(milestone)}
                      className="h-8 w-8"
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeletingMilestone(milestone)}
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
          <Target className="h-12 w-12 mx-auto mb-4 text-gray-400" />
          <p className="text-gray-400">No milestones yet. Create your first milestone to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="glass max-w-md">
          <DialogHeader>
            <DialogTitle className="text-white">
              {editingMilestone ? "Edit Milestone" : "Create Milestone"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-200/80">Milestone Name *</label>
              <Input
                className="mt-1 bg-white/5 border-white/10 text-white"
                value={formData.name || ""}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                required
              />
            </div>
            <div>
              <label className="text-sm text-gray-200/80">Description</label>
              <Textarea
                className="mt-1 bg-white/5 border-white/10 text-white"
                value={formData.description || ""}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                rows={3}
              />
            </div>
            <div>
              <label className="text-sm text-gray-200/80">Project *</label>
              <Select
                value={formData.project_id?.toString() || ""}
                onValueChange={(value) => setFormData({ ...formData, project_id: parseInt(value) })}
                required
              >
                <SelectTrigger className="mt-1 bg-white/5 border-white/10 text-white">
                  <SelectValue placeholder="Select project" />
                </SelectTrigger>
                <SelectContent>
                  {projects?.map((project) => (
                    <SelectItem key={project.id} value={project.id.toString()}>
                      {project.name || project.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="text-sm text-gray-200/80">Due Date</label>
              <Input
                type="date"
                className="mt-1 bg-white/5 border-white/10 text-white"
                value={formData.due_date || ""}
                onChange={(e) => setFormData({ ...formData, due_date: e.target.value })}
              />
            </div>
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="glass" disabled={createMilestone.isPending || updateMilestone.isPending}>
                {editingMilestone ? "Update" : "Create"}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deletingMilestone}
        onOpenChange={(open) => !open && setDeletingMilestone(null)}
        onConfirm={handleDelete}
        title="Delete Milestone"
        description={`Are you sure you want to delete "${deletingMilestone?.name}"?`}
      />
    </div>
  );
}

