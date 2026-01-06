"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { 
  CheckSquare, FolderKanban, Users, Calendar, ListTodo, 
  FileText, Settings, MessageSquare, Image, Plus, Edit, Trash2,
  X, Save, Filter
} from "lucide-react";

type Record = Record<string, any>;

type Tab = "tasks" | "projects" | "clients" | "meetings" | "todos" | "notes" | "statuses" | "priorities";

export default function TasksModulePage() {
  const [activeTab, setActiveTab] = useState<Tab>("tasks");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [records, setRecords] = useState<Record[]>([]);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [formData, setFormData] = useState<Record>({});
  const [comments, setComments] = useState<Record<number, Record[]>>({});
  const [showComments, setShowComments] = useState<Record<number, boolean>>({});

  useEffect(() => {
    loadRecords();
  }, [activeTab]);

  const loadRecords = async () => {
    setLoading(true);
    setError(null);
    try {
      const resource = activeTab === "statuses" ? "statuses" : activeTab;
      const res = await api.get(`/modules/tasks/records`, { params: { resource } });
      setRecords(res.data?.data || []);
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to load records");
      setRecords([]);
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async () => {
    setError(null);
    try {
      const resource = activeTab === "statuses" ? "statuses" : activeTab;
      await api.post(`/modules/tasks/records`, formData, { params: { resource } });
      setShowCreateModal(false);
      setFormData({});
      loadRecords();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to create record");
    }
  };

  const handleUpdate = async () => {
    if (!editingId) return;
    setError(null);
    try {
      const resource = activeTab === "statuses" ? "statuses" : activeTab;
      await api.patch(`/modules/tasks/records/${editingId}`, formData, { params: { resource } });
      setEditingId(null);
      setFormData({});
      loadRecords();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to update record");
    }
  };

  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure you want to delete this?")) return;
    setError(null);
    try {
      const resource = activeTab === "statuses" ? "statuses" : activeTab;
      await api.delete(`/modules/tasks/records/${id}`, { params: { resource } });
      loadRecords();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to delete record");
    }
  };

  const loadComments = async (recordId: number) => {
    try {
      const res = await api.get(`/modules/tasks/records/${recordId}/comments`, { 
        params: { resource: activeTab } 
      });
      setComments({ ...comments, [recordId]: res.data?.data || [] });
    } catch (err) {
      console.error("Failed to load comments:", err);
    }
  };

  const handleAddComment = async (recordId: number, comment: string) => {
    if (!comment.trim()) return;
    try {
      await api.post(`/modules/tasks/records/${recordId}/comments`, { comment }, { 
        params: { resource: activeTab } 
      });
      loadComments(recordId);
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to add comment");
    }
  };

  const toggleComments = (recordId: number) => {
    const isOpen = showComments[recordId];
    setShowComments({ ...showComments, [recordId]: !isOpen });
    if (!isOpen) {
      loadComments(recordId);
    }
  };

  const getFormFields = () => {
    switch (activeTab) {
      case "tasks":
        return [
          { key: "title", label: "Title", type: "text", required: true },
          { key: "description", label: "Description", type: "textarea" },
          { key: "due_date", label: "Due Date", type: "date" },
          { key: "status_id", label: "Status ID", type: "number" },
          { key: "priority_id", label: "Priority ID", type: "number" },
          { key: "project_id", label: "Project ID", type: "number" },
          { key: "assignee_ids", label: "Assignee IDs (comma-separated)", type: "text" },
        ];
      case "projects":
        return [
          { key: "name", label: "Name", type: "text", required: true },
          { key: "description", label: "Description", type: "textarea" },
          { key: "client_id", label: "Client ID", type: "number" },
          { key: "budget", label: "Budget", type: "number" },
          { key: "deadline", label: "Deadline", type: "date" },
        ];
      case "clients":
        return [
          { key: "name", label: "Name", type: "text", required: true },
          { key: "email", label: "Email", type: "email" },
          { key: "phone", label: "Phone", type: "text" },
          { key: "address", label: "Address", type: "textarea" },
        ];
      case "meetings":
        return [
          { key: "title", label: "Title", type: "text", required: true },
          { key: "description", label: "Description", type: "textarea" },
          { key: "start_time", label: "Start Time", type: "datetime-local" },
          { key: "end_time", label: "End Time", type: "datetime-local" },
          { key: "location", label: "Location", type: "text" },
        ];
      case "todos":
        return [
          { key: "title", label: "Title", type: "text", required: true },
          { key: "description", label: "Description", type: "textarea" },
          { key: "due_date", label: "Due Date", type: "date" },
          { key: "status", label: "Status", type: "text" },
          { key: "priority_id", label: "Priority ID", type: "number" },
        ];
      case "notes":
        return [
          { key: "title", label: "Title", type: "text", required: true },
          { key: "note", label: "Note", type: "textarea", required: true },
        ];
      case "statuses":
        return [
          { key: "name", label: "Name", type: "text", required: true },
          { key: "color", label: "Color", type: "color" },
        ];
      case "priorities":
        return [
          { key: "name", label: "Name", type: "text", required: true },
          { key: "color", label: "Color", type: "color" },
        ];
      default:
        return [];
    }
  };

  const getTabIcon = (tab: Tab) => {
    switch (tab) {
      case "tasks": return <CheckSquare className="h-4 w-4" />;
      case "projects": return <FolderKanban className="h-4 w-4" />;
      case "clients": return <Users className="h-4 w-4" />;
      case "meetings": return <Calendar className="h-4 w-4" />;
      case "todos": return <ListTodo className="h-4 w-4" />;
      case "notes": return <FileText className="h-4 w-4" />;
      case "statuses": return <Settings className="h-4 w-4" />;
      case "priorities": return <Settings className="h-4 w-4" />;
    }
  };

  const getTabLabel = (tab: Tab) => {
    return tab.charAt(0).toUpperCase() + tab.slice(1);
  };

  const tabs: Tab[] = ["tasks", "projects", "clients", "meetings", "todos", "notes", "statuses", "priorities"];

  return (
    <div className="mx-auto max-w-7xl space-y-4">
      <header className="glass rounded-2xl px-6 py-5 shadow-xl">
        <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Task Management Module</p>
        <h1 className="text-3xl font-semibold text-white">Taskify Integration</h1>
        <p className="text-sm text-gray-200/80">
          Complete Taskify functionality integrated. All features available.
        </p>
      </header>

      {/* Tabs */}
      <div className="glass rounded-2xl p-2 shadow-xl">
        <div className="flex flex-wrap gap-2">
          {tabs.map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition ${
                activeTab === tab
                  ? "bg-gradient-to-r from-cyan-400 to-blue-600 text-gray-900"
                  : "text-gray-200 hover:bg-white/10"
              }`}
            >
              {getTabIcon(tab)}
              {getTabLabel(tab)}
            </button>
          ))}
        </div>
      </div>

      {error && (
        <div className="glass rounded-xl border border-red-400/50 bg-red-500/10 px-4 py-3 text-sm text-red-200">
          {error}
        </div>
      )}

      {/* Records List */}
      <div className="glass rounded-2xl p-6 shadow-xl">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-semibold text-white">{getTabLabel(activeTab)}</h2>
          <div className="flex gap-2">
            <button
              onClick={loadRecords}
              className="rounded-lg border border-white/20 px-3 py-1.5 text-sm font-semibold text-white transition hover:border-cyan-400"
            >
              <Filter className="h-4 w-4 inline mr-1" />
              Refresh
            </button>
            <button
              onClick={() => {
                setShowCreateModal(true);
                setFormData({});
                setEditingId(null);
              }}
              className="flex items-center gap-2 rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 text-sm font-semibold text-gray-900 shadow transition hover:-translate-y-0.5"
            >
              <Plus className="h-4 w-4" />
              Create {getTabLabel(activeTab).slice(0, -1)}
            </button>
          </div>
        </div>

        {loading ? (
          <div className="text-center py-8 text-gray-200">Loading...</div>
        ) : records.length === 0 ? (
          <div className="text-center py-8 text-gray-200/80">No {activeTab} found. Create your first one!</div>
        ) : (
          <div className="space-y-3">
            {records.map((record) => (
              <div
                key={record.id}
                className="rounded-lg border border-white/10 bg-white/5 p-4"
              >
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2">
                      <h3 className="font-semibold text-white">
                        {record.title || record.name || `Record ${record.id}`}
                      </h3>
                      {record.status && (
                        <span className="rounded-full bg-cyan-500/20 px-2 py-0.5 text-xs text-cyan-300">
                          {record.status}
                        </span>
                      )}
                    </div>
                    {record.description && (
                      <p className="text-sm text-gray-200/80 mb-2">{record.description}</p>
                    )}
                    <div className="flex flex-wrap gap-4 text-xs text-gray-300/80">
                      {record.due_date && <span>Due: {new Date(record.due_date).toLocaleDateString()}</span>}
                      {record.created_at && <span>Created: {new Date(record.created_at).toLocaleDateString()}</span>}
                      {record.status_name && <span>Status: {record.status_name}</span>}
                      {record.priority_name && <span>Priority: {record.priority_name}</span>}
                    </div>
                    
                    {/* Comments Section */}
                    {activeTab === "tasks" && (
                      <div className="mt-3">
                        <button
                          onClick={() => toggleComments(record.id)}
                          className="flex items-center gap-1 text-xs text-cyan-300 hover:text-cyan-200"
                        >
                          <MessageSquare className="h-3 w-3" />
                          {showComments[record.id] ? "Hide" : "Show"} Comments
                          {comments[record.id] && ` (${comments[record.id].length})`}
                        </button>
                        {showComments[record.id] && (
                          <div className="mt-2 space-y-2">
                            {comments[record.id]?.map((comment: any, idx: number) => (
                              <div key={idx} className="rounded bg-white/5 p-2 text-xs text-gray-200">
                                {comment.comment || comment.content}
                              </div>
                            ))}
                            <div className="flex gap-2 mt-2">
                              <input
                                type="text"
                                placeholder="Add a comment..."
                                className="flex-1 rounded border border-white/10 bg-white/5 px-2 py-1 text-xs text-white outline-none focus:border-cyan-400"
                                onKeyDown={async (e) => {
                                  if (e.key === "Enter" && e.currentTarget.value.trim()) {
                                    const commentText = e.currentTarget.value.trim();
                                    e.currentTarget.value = "";
                                    await handleAddComment(record.id, commentText);
                                  }
                                }}
                              />
                            </div>
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                  <div className="flex items-center gap-2 ml-4">
                    <button
                      onClick={() => {
                        setEditingId(String(record.id));
                        setFormData(record);
                        setShowCreateModal(true);
                      }}
                      className="rounded-lg border border-white/20 px-2 py-1 text-xs font-semibold text-white transition hover:border-cyan-400"
                    >
                      <Edit className="h-3 w-3" />
                    </button>
                    <button
                      onClick={() => handleDelete(String(record.id))}
                      className="rounded-lg border border-red-400/50 px-2 py-1 text-xs font-semibold text-red-300 transition hover:border-red-400"
                    >
                      <Trash2 className="h-3 w-3" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Create/Edit Modal */}
      {(showCreateModal || editingId) && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="glass w-full max-w-2xl rounded-2xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold text-white">
                {editingId ? `Edit ${getTabLabel(activeTab).slice(0, -1)}` : `Create ${getTabLabel(activeTab).slice(0, -1)}`}
              </h2>
              <button
                onClick={() => {
                  setShowCreateModal(false);
                  setEditingId(null);
                  setFormData({});
                  setError(null);
                }}
                className="text-gray-400 hover:text-white"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="space-y-4">
              {getFormFields().map((field) => (
                <div key={field.key}>
                  <label className="text-sm text-gray-200/80">{field.label}</label>
                  {field.type === "textarea" ? (
                    <textarea
                      className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                      value={formData[field.key] || ""}
                      onChange={(e) => setFormData({ ...formData, [field.key]: e.target.value })}
                      rows={3}
                    />
                  ) : (
                    <input
                      type={field.type}
                      required={field.required}
                      className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                      value={formData[field.key] || ""}
                      onChange={(e) => setFormData({ ...formData, [field.key]: e.target.value })}
                    />
                  )}
                </div>
              ))}
              <div className="flex gap-3 pt-4">
                <button
                  onClick={editingId ? handleUpdate : handleCreate}
                  className="flex-1 rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 font-semibold text-gray-900 shadow transition hover:-translate-y-0.5"
                >
                  <Save className="h-4 w-4 inline mr-2" />
                  {editingId ? "Update" : "Create"}
                </button>
                <button
                  onClick={() => {
                    setShowCreateModal(false);
                    setEditingId(null);
                    setFormData({});
                    setError(null);
                  }}
                  className="rounded-lg border border-white/20 px-4 py-2 font-semibold text-white transition hover:border-cyan-400"
                >
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
