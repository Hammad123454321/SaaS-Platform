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
    return <div className="text-center py-12 text-gray-500">Loading tags...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-gray-900">Tags</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingTag(null); resetForm(); }} className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700">
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
              className="bg-white rounded-lg px-4 py-2 flex items-center gap-2 border border-gray-200 shadow-sm"
              style={{ borderLeft: `3px solid ${tag.color || "#6b7280"}` }}
            >
              <span className="text-gray-900">{tag.name}</span>
              {canCreateProject && (
                <div className="flex gap-1">
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => handleEdit(tag)}
                    className="h-6 w-6 text-gray-600 hover:text-purple-600 hover:bg-purple-50"
                  >
                    <Edit className="h-3 w-3" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setDeletingTag(tag)}
                    className="h-6 w-6 text-gray-600 hover:text-red-600 hover:bg-red-50"
                  >
                    <Trash2 className="h-3 w-3" />
                  </Button>
                </div>
              )}
            </div>
          ))}
        </div>
      ) : (
        <div className="bg-white rounded-lg p-12 text-center border border-gray-200">
          <div className="h-16 w-16 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-4">
            <Tag className="h-8 w-8 text-purple-600" />
          </div>
          <p className="text-gray-500">No tags yet. Create your first tag to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="bg-white max-w-md">
          <DialogHeader>
            <DialogTitle className="text-gray-900">
              {editingTag ? "Edit Tag" : "Create Tag"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-600 font-medium">Tag Name *</label>
              <Input
                className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                value={formData.name || ""}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                required
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
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700" disabled={createTag.isPending || updateTag.isPending}>
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

