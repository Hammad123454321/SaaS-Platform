"use client";

import { useState } from "react";
import { Plus, Edit, Trash2, Layers } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useTaskLists, useCreateTaskList, useUpdateTaskList, useDeleteTaskList } from "@/hooks/tasks/useTaskLists";
import { useProjects } from "@/hooks/tasks/useProjects";
import { TaskListFormData } from "@/lib/api/taskLists";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";
import { ConfirmDialog } from "@/components/shared/ConfirmDialog";

export function TaskListsView() {
  const { data: projects } = useProjects();
  const { data: taskLists, isLoading } = useTaskLists();
  const createTaskList = useCreateTaskList();
  const updateTaskList = useUpdateTaskList();
  const deleteTaskList = useDeleteTaskList();
  const { canCreateProject } = useTaskAccess();

  const [showForm, setShowForm] = useState(false);
  const [editingList, setEditingList] = useState<any>(null);
  const [deletingList, setDeletingList] = useState<any>(null);
  const [formData, setFormData] = useState<Partial<TaskListFormData>>({
    name: "",
    description: "",
    project_id: undefined,
    display_order: 0,
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.project_id) {
      alert("Please select a project");
      return;
    }
    if (editingList) {
      await updateTaskList.mutateAsync({ id: editingList.id, data: formData });
    } else {
      await createTaskList.mutateAsync(formData as TaskListFormData);
    }
    setShowForm(false);
    setEditingList(null);
    resetForm();
  };

  const resetForm = () => {
    setFormData({
      name: "",
      description: "",
      project_id: undefined,
      display_order: 0,
    });
  };

  const handleEdit = (list: any) => {
    setEditingList(list);
    setFormData({
      name: list.name,
      description: list.description,
      project_id: list.project_id,
      display_order: list.display_order,
    });
    setShowForm(true);
  };

  const handleDelete = async () => {
    if (deletingList) {
      await deleteTaskList.mutateAsync(deletingList.id);
      setDeletingList(null);
    }
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-400">Loading task lists...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-white">Task Lists</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingList(null); resetForm(); }} className="glass">
            <Plus className="h-4 w-4 mr-2" />
            Create Task List
          </Button>
        )}
      </div>

      {taskLists && taskLists.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {taskLists.map((list) => (
            <div key={list.id} className="glass rounded-lg p-4 space-y-2">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <h3 className="font-semibold text-white">{list.name}</h3>
                  {list.description && (
                    <p className="text-sm text-gray-300/80 mt-1">{list.description}</p>
                  )}
                </div>
                {canCreateProject && (
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleEdit(list)}
                      className="h-8 w-8"
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeletingList(list)}
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
          <Layers className="h-12 w-12 mx-auto mb-4 text-gray-400" />
          <p className="text-gray-400">No task lists yet. Create your first task list to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="glass max-w-md">
          <DialogHeader>
            <DialogTitle className="text-white">
              {editingList ? "Edit Task List" : "Create Task List"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-200/80">List Name *</label>
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
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="glass" disabled={createTaskList.isPending || updateTaskList.isPending}>
                {editingList ? "Update" : "Create"}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deletingList}
        onOpenChange={(open) => !open && setDeletingList(null)}
        onConfirm={handleDelete}
        title="Delete Task List"
        description={`Are you sure you want to delete "${deletingList?.name}"?`}
      />
    </div>
  );
}

