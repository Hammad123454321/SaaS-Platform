"use client";

import { useState } from "react";
import { useTaskTemplates, useCreateTaskTemplate, useUpdateTaskTemplate, useDeleteTaskTemplate } from "@/hooks/workflows/useTaskTemplates";
import { Button } from "@/components/ui/button";
import { Plus, Edit, Trash2 } from "lucide-react";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";
import { toast } from "sonner";

export default function TaskTemplatesPage() {
  const { isOwner } = useTaskAccess();
  const { data: templates, isLoading } = useTaskTemplates();
  const createTemplate = useCreateTaskTemplate();
  const updateTemplate = useUpdateTaskTemplate();
  const deleteTemplate = useDeleteTaskTemplate();
  
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [editingTemplate, setEditingTemplate] = useState<any>(null);
  const [formData, setFormData] = useState({
    template_name: "",
    template_type: "custom" as "incident" | "safety" | "custom",
    title: "",
    description: "",
    priority: "",
    status: "",
    is_locked: false,
  });

  if (!isOwner) {
    return (
      <div className="mx-auto max-w-4xl p-6">
        <div className="glass rounded-2xl p-6 shadow-xl">
          <h1 className="text-2xl font-semibold text-white mb-4">Access Denied</h1>
          <p className="text-gray-200/80">Only the owner can manage task templates.</p>
        </div>
      </div>
    );
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (editingTemplate) {
      updateTemplate.mutate(
        { templateId: editingTemplate.id, data: formData },
        {
          onSuccess: () => {
            setEditingTemplate(null);
            setShowCreateForm(false);
            resetForm();
          },
        }
      );
    } else {
      createTemplate.mutate(formData, {
        onSuccess: () => {
          setShowCreateForm(false);
          resetForm();
        },
      });
    }
  };

  const resetForm = () => {
    setFormData({
      template_name: "",
      template_type: "custom",
      title: "",
      description: "",
      priority: "",
      status: "",
      is_locked: false,
    });
  };

  const handleEdit = (template: any) => {
    if (template.is_locked) {
      toast.error("Cannot edit locked template");
      return;
    }
    setEditingTemplate(template);
    setFormData({
      template_name: template.template_name,
      template_type: template.template_type,
      title: template.title,
      description: template.description || "",
      priority: template.priority || "",
      status: template.status || "",
      is_locked: template.is_locked,
    });
    setShowCreateForm(true);
  };

  const handleDelete = (templateId: number) => {
    if (confirm("Are you sure you want to delete this template?")) {
      deleteTemplate.mutate(templateId);
    }
  };

  return (
    <div className="mx-auto max-w-6xl p-6">
      <div className="glass rounded-2xl p-6 shadow-xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-semibold text-white">Task Templates</h1>
            <p className="text-sm text-gray-200/80">Manage tenant-configurable task templates</p>
          </div>
          <Button
            onClick={() => {
              setShowCreateForm(!showCreateForm);
              setEditingTemplate(null);
              resetForm();
            }}
            className="glass"
          >
            <Plus className="h-4 w-4 mr-2" />
            {showCreateForm ? "Cancel" : "Create Template"}
          </Button>
        </div>

        {showCreateForm && (
          <form onSubmit={handleSubmit} className="mb-6 space-y-4 rounded-lg border border-white/10 bg-white/5 p-4">
            <h2 className="text-lg font-semibold text-white">
              {editingTemplate ? "Edit Template" : "Create Template"}
            </h2>
            
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-sm text-gray-200/80">Template Name *</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={formData.template_name}
                  onChange={(e) => setFormData({ ...formData, template_name: e.target.value })}
                  required
                />
              </div>
              
              <div>
                <label className="text-sm text-gray-200/80">Template Type *</label>
                <select
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={formData.template_type}
                  onChange={(e) => setFormData({ ...formData, template_type: e.target.value as any })}
                  required
                >
                  <option value="incident" className="bg-gray-800">Incident</option>
                  <option value="safety" className="bg-gray-800">Safety</option>
                  <option value="custom" className="bg-gray-800">Custom</option>
                </select>
              </div>
              
              <div className="sm:col-span-2">
                <label className="text-sm text-gray-200/80">Title *</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={formData.title}
                  onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                  required
                />
              </div>
              
              <div className="sm:col-span-2">
                <label className="text-sm text-gray-200/80">Description</label>
                <textarea
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  rows={3}
                />
              </div>
              
              <div>
                <label className="text-sm text-gray-200/80">Priority</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={formData.priority}
                  onChange={(e) => setFormData({ ...formData, priority: e.target.value })}
                />
              </div>
              
              <div>
                <label className="text-sm text-gray-200/80">Status</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={formData.status}
                  onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                />
              </div>
              
              <div className="sm:col-span-2">
                <label className="flex items-center gap-2 text-sm text-gray-200/80">
                  <input
                    type="checkbox"
                    checked={formData.is_locked}
                    onChange={(e) => setFormData({ ...formData, is_locked: e.target.checked })}
                    className="h-4 w-4 rounded border-white/20 bg-white/5"
                  />
                  <span>Lock template (cannot be modified after creation)</span>
                </label>
              </div>
            </div>
            
            <div className="flex gap-2">
              <Button type="submit" className="glass">
                {editingTemplate ? "Update" : "Create"}
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  setShowCreateForm(false);
                  setEditingTemplate(null);
                  resetForm();
                }}
              >
                Cancel
              </Button>
            </div>
          </form>
        )}

        {isLoading ? (
          <div className="text-center text-gray-200/80">Loading templates...</div>
        ) : templates && templates.length > 0 ? (
          <div className="space-y-2">
            {templates.map((template) => (
              <div
                key={template.id}
                className="flex items-center justify-between rounded-lg border border-white/10 bg-white/5 p-4"
              >
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <h3 className="font-semibold text-white">{template.title}</h3>
                    <span className="rounded-full bg-cyan-400/20 px-2 py-1 text-xs text-cyan-400">
                      {template.template_type}
                    </span>
                    {template.is_locked && (
                      <span className="rounded-full bg-red-400/20 px-2 py-1 text-xs text-red-400">
                        Locked
                      </span>
                    )}
                  </div>
                  <p className="mt-1 text-sm text-gray-200/80">{template.template_name}</p>
                  {template.description && (
                    <p className="mt-1 text-xs text-gray-300/80">{template.description}</p>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => handleEdit(template)}
                    disabled={template.is_locked}
                  >
                    <Edit className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => handleDelete(template.id)}
                    disabled={template.is_locked}
                  >
                    <Trash2 className="h-4 w-4 text-red-400" />
                  </Button>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="rounded-lg border border-white/10 bg-white/5 p-6 text-center">
            <p className="text-sm text-gray-200/80">No task templates yet. Create one to get started.</p>
          </div>
        )}
      </div>
    </div>
  );
}

