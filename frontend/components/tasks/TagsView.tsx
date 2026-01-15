"use client";

import { useState } from "react";
import { Plus, Edit, Trash2, Tag } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { useTags, useCreateTag, useUpdateTag, useDeleteTag } from "@/hooks/tasks/useTags";
import { TagFormData } from "@/lib/api/tags";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";
import { ConfirmDialog } from "@/components/shared/ConfirmDialog";

export function TagsView() {
  const { data: tags, isLoading } = useTags();
  const createTag = useCreateTag();
  const updateTag = useUpdateTag();
  const deleteTag = useDeleteTag();
  const { canCreateProject } = useTaskAccess();

  const [showForm, setShowForm] = useState(false);
  const [editingTag, setEditingTag] = useState<any>(null);
  const [deletingTag, setDeletingTag] = useState<any>(null);
  const [formData, setFormData] = useState<Partial<TagFormData>>({
    name: "",
    color: "#6b7280",
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (editingTag) {
      await updateTag.mutateAsync({ id: editingTag.id, data: formData });
    } else {
      await createTag.mutateAsync(formData as TagFormData);
    }
    setShowForm(false);
    setEditingTag(null);
    resetForm();
  };

  const resetForm = () => {
    setFormData({
      name: "",
      color: "#6b7280",
    });
  };

  const handleEdit = (tag: any) => {
    setEditingTag(tag);
    setFormData({
      name: tag.name,
      color: tag.color || "#6b7280",
    });
    setShowForm(true);
  };

  const handleDelete = async () => {
    if (deletingTag) {
      await deleteTag.mutateAsync(deletingTag.id);
      setDeletingTag(null);
    }
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-400">Loading tags...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-white">Tags</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingTag(null); resetForm(); }} className="glass">
            <Plus className="h-4 w-4 mr-2" />
            Create Tag
          </Button>
        )}
      </div>

      {tags && tags.length > 0 ? (
        <div className="flex flex-wrap gap-2">
          {tags.map((tag) => (
            <div
              key={tag.id}
              className="glass rounded-lg px-4 py-2 flex items-center gap-2"
              style={{ borderLeft: `3px solid ${tag.color || "#6b7280"}` }}
            >
              <span className="text-white">{tag.name}</span>
              {canCreateProject && (
                <div className="flex gap-1">
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => handleEdit(tag)}
                    className="h-6 w-6"
                  >
                    <Edit className="h-3 w-3" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setDeletingTag(tag)}
                    className="h-6 w-6 text-red-400"
                  >
                    <Trash2 className="h-3 w-3" />
                  </Button>
                </div>
              )}
            </div>
          ))}
        </div>
      ) : (
        <div className="glass rounded-lg p-12 text-center">
          <Tag className="h-12 w-12 mx-auto mb-4 text-gray-400" />
          <p className="text-gray-400">No tags yet. Create your first tag to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="glass max-w-md">
          <DialogHeader>
            <DialogTitle className="text-white">
              {editingTag ? "Edit Tag" : "Create Tag"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-200/80">Tag Name *</label>
              <Input
                className="mt-1 bg-white/5 border-white/10 text-white"
                value={formData.name || ""}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                required
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
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="glass" disabled={createTag.isPending || updateTag.isPending}>
                {editingTag ? "Update" : "Create"}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deletingTag}
        onOpenChange={(open) => !open && setDeletingTag(null)}
        onConfirm={handleDelete}
        title="Delete Tag"
        description={`Are you sure you want to delete "${deletingTag?.name}"?`}
      />
    </div>
  );
}

