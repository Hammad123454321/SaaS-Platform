"use client";

import { useState } from "react";
import { Plus, Edit, Trash2, FolderKanban } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useProjects, useCreateProject, useUpdateProject, useDeleteProject } from "@/hooks/tasks/useProjects";
import { useClients } from "@/hooks/tasks/useClients";
import { ProjectFormData } from "@/types/project";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";
import { ConfirmDialog } from "@/components/shared/ConfirmDialog";

export function ProjectsView() {
  const { data: projects, isLoading } = useProjects();
  const { data: clients } = useClients();
  const createProject = useCreateProject();
  const updateProject = useUpdateProject();
  const deleteProject = useDeleteProject();
  const { canCreateProject, canUpdateProject, canDeleteProject } = useTaskAccess();

  const [showForm, setShowForm] = useState(false);
  const [editingProject, setEditingProject] = useState<any>(null);
  const [deletingProject, setDeletingProject] = useState<any>(null);
  const [formData, setFormData] = useState<Partial<ProjectFormData>>({
    title: "",
    description: "",
    client_id: undefined,
    budget: undefined,
    start_date: "",
    deadline: "",
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingProject && !formData.client_id) {
      alert("Please select a client for this project");
      return;
    }
    if (editingProject) {
      await updateProject.mutateAsync({ id: editingProject.id, data: formData });
    } else {
      await createProject.mutateAsync(formData as ProjectFormData);
    }
    setShowForm(false);
    setEditingProject(null);
    resetForm();
  };

  const resetForm = () => {
    setFormData({
      title: "",
      description: "",
      client_id: undefined,
      budget: undefined,
      start_date: "",
      deadline: "",
    });
  };

  const handleEdit = (project: any) => {
    setEditingProject(project);
    setFormData({
      title: project.name || project.title,
      description: project.description,
      client_id: project.client_id,
      budget: project.budget,
      start_date: project.start_date,
      deadline: project.deadline,
    });
    setShowForm(true);
  };

  const handleDelete = async () => {
    if (deletingProject) {
      await deleteProject.mutateAsync(deletingProject.id);
      setDeletingProject(null);
    }
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-500">Loading projects...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-gray-900">Projects</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingProject(null); resetForm(); }} className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700">
            <Plus className="h-4 w-4 mr-2" />
            Create Project
          </Button>
        )}
      </div>

      {projects && projects.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {projects.map((project) => (
            <div key={project.id} className="bg-white rounded-lg p-4 space-y-2 border border-gray-200 shadow-sm">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <h3 className="font-semibold text-gray-900">{project.name || project.title}</h3>
                  {project.description && (
                    <p className="text-sm text-gray-500 mt-1">{project.description}</p>
                  )}
                </div>
                {(canUpdateProject || canDeleteProject) && (
                  <div className="flex gap-1">
                    {canUpdateProject && (
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleEdit(project)}
                        className="h-8 w-8 text-gray-600 hover:text-purple-600 hover:bg-purple-50"
                      >
                        <Edit className="h-4 w-4" />
                      </Button>
                    )}
                    {canDeleteProject && (
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setDeletingProject(project)}
                        className="h-8 w-8 text-gray-600 hover:text-red-600 hover:bg-red-50"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                  </div>
                )}
              </div>
              <div className="flex items-center gap-4 text-xs text-gray-500">
                {project.client && (
                  <span>Client: {project.client.first_name} {project.client.last_name}</span>
                )}
                {project.status && (
                  <span className="capitalize">{project.status}</span>
                )}
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="bg-white rounded-lg p-12 text-center border border-gray-200">
          <div className="h-16 w-16 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-4">
            <FolderKanban className="h-8 w-8 text-purple-600" />
          </div>
          <p className="text-gray-500">No projects yet. Create your first project to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="bg-white max-w-2xl">
          <DialogHeader>
            <DialogTitle className="text-gray-900">
              {editingProject ? "Edit Project" : "Create Project"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-600 font-medium">Project Name *</label>
              <Input
                className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                value={formData.title || ""}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
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
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-sm text-gray-600 font-medium">Client *</label>
                <Select
                  value={formData.client_id || undefined}
                  onValueChange={(value) => {
                    setFormData(prev => ({ ...prev, client_id: value || undefined }));
                  }}
                  required
                >
                  <SelectTrigger className="mt-1 bg-white border-gray-300 text-gray-900">
                    <SelectValue placeholder="Select client (required)" />
                  </SelectTrigger>
                  <SelectContent>
                    {clients?.map((client) => {
                      const clientIdStr = String(client.id);
                      return (
                        <SelectItem key={client.id} value={clientIdStr}>
                          {client.first_name} {client.last_name}
                        </SelectItem>
                      );
                    })}
                  </SelectContent>
                </Select>
                {clients?.length === 0 && (
                  <p className="text-xs text-amber-600 mt-1">Create a client first to add projects</p>
                )}
              </div>
              <div>
                <label className="text-sm text-gray-600 font-medium">Budget</label>
                <Input
                  type="number"
                  className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                  value={formData.budget || ""}
                  onChange={(e) => setFormData({ ...formData, budget: e.target.value ? parseFloat(e.target.value) : undefined })}
                />
              </div>
              <div>
                <label className="text-sm text-gray-600 font-medium">Start Date</label>
                <Input
                  type="date"
                  className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                  value={formData.start_date || ""}
                  onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                />
              </div>
              <div>
                <label className="text-sm text-gray-600 font-medium">Deadline</label>
                <Input
                  type="date"
                  className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                  value={formData.deadline || ""}
                  onChange={(e) => setFormData({ ...formData, deadline: e.target.value })}
                />
              </div>
            </div>
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700" disabled={createProject.isPending || updateProject.isPending}>
                {editingProject ? "Update" : "Create"}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deletingProject}
        onOpenChange={(open) => !open && setDeletingProject(null)}
        onConfirm={handleDelete}
        title="Delete Project"
        description={`Are you sure you want to delete "${deletingProject?.name || deletingProject?.title}"? This action cannot be undone.`}
      />
    </div>
  );
}
