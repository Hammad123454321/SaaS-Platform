"use client";

import { useEffect, useState, useCallback } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";
import { 
  CheckSquare, FolderKanban, Users, Calendar, ListTodo, 
  FileText, Settings, MessageSquare, Plus, Edit, Trash2,
  X, Save, RefreshCw, UserPlus, UserCheck, AlertCircle, ChevronRight,
  Star, Pin, Clock, Tag, Target, Upload, Download, Filter, Layers,
  History, FileSpreadsheet, Link2, Copy,
} from "lucide-react";

// Types for dropdown data (all from Taskify)
interface DropdownItem {
  id: number;
  name: string;
  color?: string;
  email?: string;
  status?: number;
  client_id?: number;
}

interface TaskRecord {
  id: number;
  title: string;
  description?: string;
  status?: string;
  status_id?: number;
  status_name?: string;
  priority?: string;
  priority_id?: number;
  priority_name?: string;
  project?: { id: number; title: string };
  project_id?: number;
  due_date?: string;
  start_date?: string;
  assignees?: { id: number; first_name: string; last_name: string }[];
  user_id?: number[];
  created_at?: string;
  is_favorite?: boolean;
  is_pinned?: boolean;
}

type Tab = "tasks" | "projects" | "clients" | "statuses" | "priorities" | "team" | "milestones" | "task-lists" | "time-tracker" | "tags";

export default function TasksModulePage() {
  const { user } = useSessionStore();

  // Tab state
  const [activeTab, setActiveTab] = useState<Tab>("tasks");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Records from Taskify
  const [records, setRecords] = useState<any[]>([]);

  // Dropdown data from Taskify
  const [clients, setClients] = useState<DropdownItem[]>([]);
  const [projects, setProjects] = useState<DropdownItem[]>([]);
  const [statuses, setStatuses] = useState<DropdownItem[]>([]);
  const [priorities, setPriorities] = useState<DropdownItem[]>([]);
  const [taskifyUsers, setTaskifyUsers] = useState<DropdownItem[]>([]);

  // Modal state
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [formData, setFormData] = useState<Record<string, any>>({});
  const [selectedAssignees, setSelectedAssignees] = useState<number[]>([]);

  // Loading states for dropdowns
  const [loadingDropdowns, setLoadingDropdowns] = useState(false);

  // Clear messages after timeout
  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(null), 3000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);

  useEffect(() => {
    if (error) {
      const timer = setTimeout(() => setError(null), 5000);
      return () => clearTimeout(timer);
    }
  }, [error]);

  // Load dropdown data from Taskify
  const loadDropdownData = useCallback(async () => {
    setLoadingDropdowns(true);
    try {
      const [clientsRes, projectsRes, statusesRes, prioritiesRes, usersRes] = await Promise.all([
        api.get("/modules/tasks/dropdown/clients").catch(() => ({ data: { data: [] } })),
        api.get("/modules/tasks/dropdown/projects").catch(() => ({ data: { data: [] } })),
        api.get("/modules/tasks/dropdown/statuses").catch(() => ({ data: { data: [] } })),
        api.get("/modules/tasks/dropdown/priorities").catch(() => ({ data: { data: [] } })),
        api.get("/modules/tasks/dropdown/users").catch(() => ({ data: { data: [] } })),
      ]);

      setClients(clientsRes.data?.data || []);
      setProjects(projectsRes.data?.data || []);
      setStatuses(statusesRes.data?.data || []);
      setPriorities(prioritiesRes.data?.data || []);
      setTaskifyUsers(usersRes.data?.data || []);
    } catch (err) {
      console.error("Failed to load dropdown data:", err);
    } finally {
      setLoadingDropdowns(false);
    }
  }, []);

  // Load records based on active tab
  const loadRecords = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      let resource: string | undefined = activeTab === "team" ? "users" : activeTab;
      let endpoint = "/modules/tasks/records";
      
      // Handle special endpoints
      if (activeTab === "milestones") {
        endpoint = "/modules/tasks/milestones";
        resource = undefined;
      } else if (activeTab === "task-lists") {
        endpoint = "/modules/tasks/task-lists";
        resource = undefined;
      } else if (activeTab === "time-tracker") {
        endpoint = "/modules/tasks/time-tracker";
        resource = undefined;
      } else if (activeTab === "tags") {
        endpoint = "/modules/tasks/tags";
        resource = undefined;
      }
      
      const params: Record<string, string> = resource ? { resource } : {};
      const res = await api.get(endpoint, { params });
      setRecords(res.data?.data || []);
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to load records from Taskify");
      setRecords([]);
    } finally {
      setLoading(false);
    }
  }, [activeTab]);

  // Initial load
  useEffect(() => {
    loadDropdownData();
  }, [loadDropdownData]);

  // Load records when tab changes
  useEffect(() => {
    loadRecords();
  }, [activeTab, loadRecords]);

  // Handle record creation - sends to Taskify via wrapper
  const handleCreate = async () => {
    setError(null);
    try {
      const resource = activeTab === "team" ? "users" : activeTab;
      const payload = { ...formData };
      
      // Task-specific handling
      if (resource === "tasks") {
        if (!payload.project) {
          setError("Please select a project. Create a project first if none exist.");
          return;
        }
        if (!payload.status_id) {
          setError("Please select a status.");
          return;
        }
        if (selectedAssignees.length > 0) {
          payload.user_id = selectedAssignees;
        }
        // Format dates for Taskify API
        if (payload.due_date) {
          payload.due_date = new Date(payload.due_date).toISOString().split("T")[0];
        }
        if (payload.start_date) {
          payload.start_date = new Date(payload.start_date).toISOString().split("T")[0];
        }
      }

      // Project-specific handling
      if (resource === "projects") {
        if (!payload.client_id) {
          setError("Please select a client. Create a client first if none exist.");
          return;
        }
        // Rename 'name' to 'title' if needed
        if (payload.name && !payload.title) {
          payload.title = payload.name;
        }
      }

      await api.post("/modules/tasks/records", payload, { params: { resource } });
      setShowCreateModal(false);
      setFormData({});
      setSelectedAssignees([]);
      setSuccessMessage(`${resource.slice(0, -1)} created successfully!`);
      loadRecords();
      // Reload dropdowns if we created a client or project
      if (resource === "clients" || resource === "projects") {
        loadDropdownData();
      }
    } catch (err: any) {
      setError(err?.response?.data?.detail || `Failed to create ${activeTab}`);
    }
  };

  // Handle record update - sends to Taskify via wrapper
  const handleUpdate = async () => {
    if (!editingId) return;
    setError(null);
    try {
      const resource = activeTab === "team" ? "users" : activeTab;
      const payload = { ...formData };
      
      // Task-specific handling
      if (resource === "tasks") {
        if (selectedAssignees.length > 0) {
          payload.user_id = selectedAssignees;
        }
        if (payload.due_date) {
          payload.due_date = new Date(payload.due_date).toISOString().split("T")[0];
        }
        if (payload.start_date) {
          payload.start_date = new Date(payload.start_date).toISOString().split("T")[0];
        }
      }
      
      await api.patch(`/modules/tasks/records/${editingId}`, payload, { params: { resource } });
      setEditingId(null);
      setShowCreateModal(false);
      setFormData({});
      setSelectedAssignees([]);
      setSuccessMessage(`${resource.slice(0, -1)} updated successfully!`);
      loadRecords();
    } catch (err: any) {
      setError(err?.response?.data?.detail || `Failed to update ${activeTab}`);
    }
  };

  // Handle record deletion - sends to Taskify via wrapper
  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure you want to delete this? This action cannot be undone.")) return;
    setError(null);
    try {
      const resource = activeTab === "team" ? "users" : activeTab;
      await api.delete(`/modules/tasks/records/${id}`, { params: { resource } });
      setSuccessMessage(`${resource.slice(0, -1)} deleted successfully!`);
      loadRecords();
      if (resource === "clients" || resource === "projects") {
        loadDropdownData();
      }
    } catch (err: any) {
      setError(err?.response?.data?.detail || `Failed to delete ${activeTab}`);
    }
  };

  // Task-specific handlers
  const handleToggleFavorite = async (taskId: number, isFavorite: boolean) => {
    try {
      await api.patch(`/modules/tasks/tasks/${taskId}/favorite`, null, { params: { is_favorite: !isFavorite } });
      setSuccessMessage(`Task ${!isFavorite ? "added to" : "removed from"} favorites!`);
      loadRecords();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to update favorite status");
    }
  };

  const handleTogglePinned = async (taskId: number, isPinned: boolean) => {
    try {
      await api.patch(`/modules/tasks/tasks/${taskId}/pinned`, null, { params: { is_pinned: !isPinned } });
      setSuccessMessage(`Task ${!isPinned ? "pinned" : "unpinned"}!`);
      loadRecords();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to update pinned status");
    }
  };

  const handleUploadMedia = async (taskId: number, file: File) => {
    try {
      const formData = new FormData();
      formData.append("file", file);
      await api.post(`/modules/tasks/tasks/${taskId}/media`, formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      setSuccessMessage("File uploaded successfully!");
      loadRecords();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to upload file");
    }
  };

  const handleDuplicateTask = async (taskId: number) => {
    try {
      await api.post(`/modules/tasks/tasks/${taskId}/duplicate`);
      setSuccessMessage("Task duplicated successfully!");
      loadRecords();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to duplicate task");
    }
  };

  const handleBulkDelete = async (taskIds: number[]) => {
    if (!confirm(`Are you sure you want to delete ${taskIds.length} tasks? This action cannot be undone.`)) return;
    try {
      await api.post("/modules/tasks/tasks/bulk-delete", { task_ids: taskIds });
      setSuccessMessage(`${taskIds.length} tasks deleted successfully!`);
      loadRecords();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to delete tasks");
    }
  };

  // Open create modal with validation
  const openCreateModal = () => {
    // Enforce flow: task requires project, project requires client
    if (activeTab === "tasks" && projects.length === 0) {
      setError("You must create at least one project before creating tasks. Go to Projects tab first.");
      return;
    }
    if (activeTab === "projects" && clients.length === 0) {
      setError("You must create at least one client before creating projects. Go to Clients tab first.");
      return;
    }
    setShowCreateModal(true);
    setEditingId(null);
    setFormData({});
    setSelectedAssignees([]);
  };

  // Open edit modal
  const openEditModal = (record: any) => {
    setEditingId(String(record.id));
    setFormData(record);
    if (activeTab === "tasks") {
      // Set project from record
      if (record.project?.id) {
        setFormData((prev) => ({ ...prev, project: record.project.id }));
      } else if (record.project_id) {
        setFormData((prev) => ({ ...prev, project: record.project_id }));
      }
      // Set assignees
      if (record.assignees) {
        setSelectedAssignees(record.assignees.map((a: any) => a.id));
      } else if (record.user_id) {
        setSelectedAssignees(Array.isArray(record.user_id) ? record.user_id : []);
      } else {
        setSelectedAssignees([]);
      }
    }
    setShowCreateModal(true);
  };

  // Get tab icon
  const getTabIcon = (tab: Tab) => {
    const icons: Record<Tab, JSX.Element> = {
      tasks: <CheckSquare className="h-4 w-4" />,
      projects: <FolderKanban className="h-4 w-4" />,
      clients: <Users className="h-4 w-4" />,
      statuses: <Settings className="h-4 w-4" />,
      priorities: <Settings className="h-4 w-4" />,
      team: <UserCheck className="h-4 w-4" />,
      milestones: <Target className="h-4 w-4" />,
      "task-lists": <ListTodo className="h-4 w-4" />,
      "time-tracker": <Clock className="h-4 w-4" />,
      tags: <Tag className="h-4 w-4" />,
    };
    return icons[tab];
  };

  // Get tab label
  const getTabLabel = (tab: Tab): string => {
    const labels: Record<Tab, string> = {
      tasks: "Tasks",
      projects: "Projects",
      clients: "Clients",
      statuses: "Statuses",
      priorities: "Priorities",
      team: "Team Members",
      milestones: "Milestones",
      "task-lists": "Task Lists",
      "time-tracker": "Time Tracker",
      tags: "Tags",
    };
    return labels[tab];
  };

  // Get display name for records
  const getRecordName = (record: any): string => {
    return record.title || record.name || `${record.first_name || ""} ${record.last_name || ""}`.trim() || `Record ${record.id}`;
  };

  // Get status/priority badge color
  const getBadgeColor = (color?: string): string => {
    if (color) {
      return color.startsWith("#") ? color : `#${color}`;
    }
    return "#6b7280";
  };

  const tabs: Tab[] = ["tasks", "projects", "clients", "milestones", "task-lists", "time-tracker", "tags", "statuses", "priorities", "team"];

  // Get form fields for modal
  const getFormFields = () => {
    switch (activeTab) {
      case "tasks":
        return [
          { key: "title", label: "Title", type: "text", required: true },
          { key: "description", label: "Description", type: "textarea" },
          { key: "due_date", label: "Due Date", type: "date" },
          { key: "start_date", label: "Start Date", type: "date" },
        ];
      case "projects":
        return [
          { key: "title", label: "Title", type: "text", required: true },
          { key: "description", label: "Description", type: "textarea" },
          { key: "budget", label: "Budget", type: "number" },
          { key: "end_date", label: "End Date", type: "date" },
        ];
      case "clients":
        return [
          { key: "first_name", label: "First Name", type: "text", required: true },
          { key: "last_name", label: "Last Name", type: "text", required: true },
          { key: "email", label: "Email", type: "email", required: true },
          { key: "phone", label: "Phone", type: "text" },
          { key: "company", label: "Company", type: "text" },
        ];
      case "milestones":
        return [
          { key: "title", label: "Title", type: "text", required: true },
          { key: "description", label: "Description", type: "textarea" },
          { key: "due_date", label: "Due Date", type: "date" },
        ];
      case "task-lists":
        return [
          { key: "title", label: "Title", type: "text", required: true },
          { key: "description", label: "Description", type: "textarea" },
        ];
      case "time-tracker":
        return [
          { key: "message", label: "Description", type: "textarea" },
        ];
      case "tags":
        return [
          { key: "title", label: "Tag Name", type: "text", required: true },
          { key: "color", label: "Color", type: "color" },
        ];
      case "statuses":
        return [
          { key: "title", label: "Status Name", type: "text", required: true },
          { key: "color", label: "Color", type: "color", required: true },
        ];
      case "priorities":
        return [
          { key: "title", label: "Priority Name", type: "text", required: true },
          { key: "color", label: "Color", type: "color", required: true },
        ];
      default:
        return [];
    }
  };

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

      {/* Messages */}
      {error && (
        <div className="glass rounded-xl border border-red-500/50 bg-red-500/10 px-4 py-3 text-red-200 flex items-center gap-2">
          <AlertCircle className="h-5 w-5 flex-shrink-0" />
          <span>{error}</span>
        </div>
      )}
      {successMessage && (
        <div className="glass rounded-xl border border-green-500/50 bg-green-500/10 px-4 py-3 text-green-200">
          {successMessage}
        </div>
      )}

      {/* Flow Guide */}
      <div className="glass rounded-xl px-4 py-3 border border-cyan-500/30">
        <div className="flex items-center gap-2 text-sm text-cyan-200">
          <span className="font-medium">Workflow:</span>
          <span className="flex items-center gap-1">
            <Users className="h-3 w-3" /> Clients
          </span>
          <ChevronRight className="h-3 w-3 text-gray-400" />
          <span className="flex items-center gap-1">
            <FolderKanban className="h-3 w-3" /> Projects
          </span>
          <ChevronRight className="h-3 w-3 text-gray-400" />
          <span className="flex items-center gap-1">
            <CheckSquare className="h-3 w-3" /> Tasks
          </span>
          <span className="ml-auto text-xs text-gray-400">
            {clients.length} clients | {projects.length} projects | {statuses.length} statuses | {priorities.length} priorities
          </span>
        </div>
      </div>

      {/* Records List */}
      <div className="glass rounded-2xl p-6 shadow-xl">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-xl font-semibold text-white">{getTabLabel(activeTab)}</h2>
          <div className="flex gap-2">
            <button
              onClick={loadRecords}
              disabled={loading}
              className="flex items-center gap-2 rounded-lg border border-white/20 px-3 py-2 text-sm font-medium text-white transition hover:border-cyan-400 disabled:opacity-50"
            >
              <RefreshCw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} />
              Refresh
            </button>
            <button
              onClick={openCreateModal}
              className="flex items-center gap-2 rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 text-sm font-semibold text-gray-900 shadow transition hover:-translate-y-0.5"
            >
              <Plus className="h-4 w-4" />
              Create {getTabLabel(activeTab).slice(0, -1)}
            </button>
          </div>
        </div>

        {/* Records List */}
        {loading ? (
          <div className="text-center py-12 text-gray-200">
            <RefreshCw className="h-8 w-8 animate-spin mx-auto mb-2" />
            Loading from Taskify...
          </div>
        ) : records.length === 0 ? (
          <div className="text-center py-12 text-gray-200/80">
            <div className="mb-2">No {activeTab} found in Taskify.</div>
            <button
              onClick={openCreateModal}
              className="text-cyan-400 hover:text-cyan-300 underline"
            >
              Create your first {getTabLabel(activeTab).slice(0, -1).toLowerCase()}
            </button>
          </div>
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
                      <h3 className="font-semibold text-white">{getRecordName(record)}</h3>
                      {/* Status badge for tasks */}
                      {activeTab === "tasks" && (record.status || record.status_name) && (
                        <span
                          className="rounded-full px-2 py-0.5 text-xs font-medium"
                          style={{
                            backgroundColor: `${getBadgeColor(record.status_color)}20`,
                            color: getBadgeColor(record.status_color),
                          }}
                        >
                          {record.status_name || record.status}
                        </span>
                      )}
                      {/* Priority badge for tasks */}
                      {activeTab === "tasks" && (record.priority || record.priority_name) && (
                        <span
                          className="rounded-full px-2 py-0.5 text-xs font-medium"
                          style={{
                            backgroundColor: `${getBadgeColor(record.priority_color)}20`,
                            color: getBadgeColor(record.priority_color),
                          }}
                        >
                          {record.priority_name || record.priority}
                        </span>
                      )}
                      {/* Color badge for statuses/priorities */}
                      {(activeTab === "statuses" || activeTab === "priorities") && record.color && (
                        <span
                          className="w-4 h-4 rounded-full"
                          style={{ backgroundColor: getBadgeColor(record.color) }}
                        />
                      )}
                    </div>
                    {record.description && (
                      <p className="text-sm text-gray-200/80 mb-2">{record.description}</p>
                    )}
                    {/* Task details */}
                    {activeTab === "tasks" && (
                      <div className="flex flex-wrap gap-4 text-xs text-gray-300/80">
                        {record.project?.title && <span>Project: {record.project.title}</span>}
                        {record.due_date && <span>Due: {new Date(record.due_date).toLocaleDateString()}</span>}
                        {record.assignees && record.assignees.length > 0 && (
                          <span>
                            Assigned: {record.assignees.map((a: any) => `${a.first_name} ${a.last_name}`).join(", ")}
                          </span>
                        )}
                        {record.is_favorite && (
                          <span className="flex items-center gap-1 text-yellow-400">
                            <Star className="h-3 w-3 fill-current" /> Favorite
                          </span>
                        )}
                        {record.is_pinned && (
                          <span className="flex items-center gap-1 text-blue-400">
                            <Pin className="h-3 w-3 fill-current" /> Pinned
                          </span>
                        )}
                      </div>
                    )}
                    
                    {/* Project details */}
                    {activeTab === "projects" && (
                      <div className="flex flex-wrap gap-4 text-xs text-gray-300/80">
                        {record.client && <span>Client: {record.client.first_name} {record.client.last_name}</span>}
                        {record.budget && <span>Budget: ${record.budget}</span>}
                        {record.deadline && <span>Deadline: {new Date(record.deadline).toLocaleDateString()}</span>}
                      </div>
                    )}

                    {/* Client details */}
                    {activeTab === "clients" && record.email && (
                      <div className="text-xs text-gray-300/80">{record.email}</div>
                    )}

                    {/* Team member details */}
                    {activeTab === "team" && record.email && (
                      <div className="text-xs text-gray-300/80">{record.email}</div>
                    )}
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-2 ml-4">
                    {/* Task-specific actions */}
                    {activeTab === "tasks" && (
                      <>
                        <button
                          onClick={() => handleToggleFavorite(record.id, record.is_favorite || false)}
                          className={`rounded-lg border px-2 py-1 text-xs font-semibold transition ${
                            record.is_favorite
                              ? "border-yellow-400/50 text-yellow-300 hover:border-yellow-400"
                              : "border-white/20 text-white hover:border-yellow-400"
                          }`}
                          title={record.is_favorite ? "Remove from favorites" : "Add to favorites"}
                        >
                          <Star className={`h-3 w-3 ${record.is_favorite ? "fill-current" : ""}`} />
                        </button>
                        <button
                          onClick={() => handleTogglePinned(record.id, record.is_pinned || false)}
                          className={`rounded-lg border px-2 py-1 text-xs font-semibold transition ${
                            record.is_pinned
                              ? "border-blue-400/50 text-blue-300 hover:border-blue-400"
                              : "border-white/20 text-white hover:border-blue-400"
                          }`}
                          title={record.is_pinned ? "Unpin" : "Pin"}
                        >
                          <Pin className={`h-3 w-3 ${record.is_pinned ? "fill-current" : ""}`} />
                        </button>
                        <button
                          onClick={() => handleDuplicateTask(record.id)}
                          className="rounded-lg border border-white/20 px-2 py-1 text-xs font-semibold text-white transition hover:border-cyan-400"
                          title="Duplicate"
                        >
                          <Copy className="h-3 w-3" />
                        </button>
                        <label className="rounded-lg border border-white/20 px-2 py-1 text-xs font-semibold text-white transition hover:border-cyan-400 cursor-pointer">
                          <Upload className="h-3 w-3" />
                          <input
                            type="file"
                            className="hidden"
                            onChange={(e) => {
                              const file = e.target.files?.[0];
                              if (file) handleUploadMedia(record.id, file);
                            }}
                          />
                        </label>
                      </>
                    )}
                    <button
                      onClick={() => openEditModal(record)}
                      className="rounded-lg border border-white/20 px-2 py-1 text-xs font-semibold text-white transition hover:border-cyan-400"
                      title="Edit"
                    >
                      <Edit className="h-3 w-3" />
                    </button>
                    <button
                      onClick={() => handleDelete(String(record.id))}
                      className="rounded-lg border border-red-400/50 px-2 py-1 text-xs font-semibold text-red-300 transition hover:border-red-400"
                      title="Delete"
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
