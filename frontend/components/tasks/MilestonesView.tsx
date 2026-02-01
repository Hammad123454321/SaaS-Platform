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
    return <div className="text-center py-12 text-gray-500">Loading milestones...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-gray-900">Milestones</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingMilestone(null); resetForm(); }} className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700">
            <Plus className="h-4 w-4 mr-2" />
            Create Milestone
          </Button>
        )}
      </div>

      {milestones && milestones.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {milestones.map((milestone) => (
            <div key={milestone.id} className="bg-white rounded-lg p-4 space-y-2 border border-gray-200 shadow-sm">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <h3 className="font-semibold text-gray-900">{milestone.name}</h3>
                  {milestone.description && (
                    <p className="text-sm text-gray-500 mt-1">{milestone.description}</p>
                  )}
                  {milestone.due_date && (
                    <p className="text-xs text-gray-500 mt-2">
                      Due: {new Date(milestone.due_date).toLocaleDateString()}
                    </p>
                  )}
                  {milestone.completed_at && (
                    <p className="text-xs text-green-600 mt-1">
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
                      className="h-8 w-8 text-gray-600 hover:text-purple-600 hover:bg-purple-50"
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeletingMilestone(milestone)}
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
            <Target className="h-8 w-8 text-purple-600" />
          </div>
          <p className="text-gray-500">No milestones yet. Create your first milestone to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="bg-white max-w-md">
          <DialogHeader>
            <DialogTitle className="text-gray-900">
              {editingMilestone ? "Edit Milestone" : "Create Milestone"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-600 font-medium">Milestone Name *</label>
              <Input
                className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                value={formData.name || ""}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                required
              />
            </div>
            <div>
              <label className="text-sm text-gray-600 font-medium">Description</label>
              <Textarea
                className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                value={formData.description || ""}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                rows={3}
              />
            </div>
            <div>
              <label className="text-sm text-gray-600 font-medium">Project *</label>
              <Select
                value={formData.project_id || undefined}
                onValueChange={(value) => setFormData({ ...formData, project_id: value })}
                required
              >
                <SelectTrigger className="mt-1 bg-white border-gray-300 text-gray-900">
                  <SelectValue placeholder="Select project" />
                </SelectTrigger>
                <SelectContent>
                  {projects?.map((project) => (
                    <SelectItem key={project.id} value={project.id}>
                      {project.name || project.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="text-sm text-gray-600 font-medium">Due Date</label>
              <Input
                type="date"
                className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                value={formData.due_date || ""}
                onChange={(e) => setFormData({ ...formData, due_date: e.target.value })}
              />
            </div>
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700" disabled={createMilestone.isPending || updateMilestone.isPending}>
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

