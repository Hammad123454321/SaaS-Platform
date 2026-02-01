"use client";

import { useState, useEffect } from "react";
import { api } from "@/lib/api";
import {
  X,
  Clock,
  FileText,
  MessageSquare,
  User,
  CheckSquare,
  Plus,
  Edit,
  Trash2,
  Star,
  Pin,
  Upload,
  Play,
  Square,
  Link2,
  Repeat,
  Download,
} from "lucide-react";
import { TaskDependenciesView } from "./TaskDependenciesView";
import { RecurringTaskView } from "./RecurringTaskView";

interface TaskDetailModalProps {
  taskId: string;
  isOpen: boolean;
  onClose: () => void;
  onUpdate?: () => void;
}

export function TaskDetailModal({
  taskId,
  isOpen,
  onClose,
  onUpdate,
}: TaskDetailModalProps) {
  const [task, setTask] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<"details" | "subtasks" | "dependencies" | "recurring" | "time" | "documents" | "threads">("details");
  const [subtasks, setSubtasks] = useState<any[]>([]);
  const [timeEntries, setTimeEntries] = useState<any[]>([]);
  const [documents, setDocuments] = useState<any[]>([]);
  const [threads, setThreads] = useState<any[]>([]);
  const [activeTracker, setActiveTracker] = useState<any>(null);

  useEffect(() => {
    if (isOpen && taskId) {
      loadTaskData();
      loadActiveTracker();
    }
  }, [isOpen, taskId]);

  const loadTaskData = async () => {
    setLoading(true);
    try {
      const [taskRes, subtasksRes, timeRes, docsRes, threadsRes] = await Promise.all([
        api.get(`/modules/tasks/records/${taskId}`, { params: { resource: "tasks" } }).catch(() => ({ data: { data: null } })),
        api.get(`/modules/tasks/tasks/${taskId}/subtasks`).catch(() => ({ data: { data: [] } })),
        api.get("/modules/tasks/time-entries", { params: { task_id: taskId } }).catch(() => ({ data: { data: [] } })),
        api.get("/modules/tasks/documents", { params: { task_id: taskId } }).catch(() => ({ data: { data: [] } })),
        api.get("/modules/tasks/threads", { params: { task_id: taskId } }).catch(() => ({ data: { data: [] } })),
      ]);

      setTask(taskRes.data?.data);
      setSubtasks(subtasksRes.data?.data || []);
      setTimeEntries(timeRes.data?.data || []);
      setDocuments(docsRes.data?.data || []);
      setThreads(threadsRes.data?.data || []);
    } catch (err) {
      console.error("Failed to load task data:", err);
    } finally {
      setLoading(false);
    }
  };

  const loadActiveTracker = async () => {
    try {
      const res = await api.get("/modules/tasks/time-tracker/active");
      setActiveTracker(res.data?.data);
    } catch (err) {
      setActiveTracker(null);
    }
  };

  const handleStartTracker = async () => {
    try {
      await api.post("/modules/tasks/time-tracker/start", {
        task_id: taskId,
        message: `Working on: ${task?.title}`,
      });
      await loadActiveTracker();
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to start tracker");
    }
  };

  const handleStopTracker = async () => {
    if (!activeTracker) return;
    try {
      await api.post(`/modules/tasks/time-tracker/${activeTracker.id}/stop`);
      await loadActiveTracker();
      await loadTaskData();
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to stop tracker");
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
      <div className="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-purple-600 to-blue-600">
          <h2 className="text-2xl font-semibold text-white">
            {loading ? "Loading..." : task?.title || "Task Details"}
          </h2>
          <button
            onClick={onClose}
            className="p-2 hover:bg-white/20 rounded-lg text-white/80 hover:text-white"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex border-b border-gray-200 px-6 overflow-x-auto bg-gray-50">
          {[
            { id: "details", label: "Details", icon: FileText },
            { id: "subtasks", label: "Subtasks", icon: CheckSquare },
            { id: "dependencies", label: "Dependencies", icon: Link2 },
            { id: "recurring", label: "Recurring", icon: Repeat },
            { id: "time", label: "Time", icon: Clock },
            { id: "documents", label: "Documents", icon: FileText },
            { id: "threads", label: "Threads", icon: MessageSquare },
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`px-4 py-3 text-sm font-medium border-b-2 transition ${
                activeTab === tab.id
                  ? "border-purple-600 text-purple-600"
                  : "border-transparent text-gray-500 hover:text-gray-900"
              }`}
            >
              <tab.icon className="h-4 w-4 inline mr-2" />
              {tab.label}
            </button>
          ))}
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-6 bg-white">
          {loading ? (
            <div className="text-center py-12 text-gray-500">Loading...</div>
          ) : activeTab === "details" ? (
            <TaskDetailsView task={task} />
          ) : activeTab === "subtasks" ? (
            <SubtasksView
              taskId={taskId}
              subtasks={subtasks}
              onUpdate={loadTaskData}
            />
          ) : activeTab === "time" ? (
            <TimeTrackingView
              taskId={taskId}
              timeEntries={timeEntries}
              activeTracker={activeTracker}
              onStartTracker={handleStartTracker}
              onStopTracker={handleStopTracker}
              onUpdate={loadTaskData}
            />
          ) : activeTab === "documents" ? (
            <DocumentsView
              taskId={taskId}
              documents={documents}
              onUpdate={loadTaskData}
            />
          ) : activeTab === "threads" ? (
            <ThreadsView
              taskId={taskId}
              threads={threads}
              onUpdate={loadTaskData}
            />
          ) : activeTab === "dependencies" ? (
            <TaskDependenciesView taskId={taskId} />
          ) : activeTab === "recurring" ? (
            <RecurringTaskView taskId={taskId} />
          ) : null}
        </div>
      </div>
    </div>
  );
}

function TaskDetailsView({ task }: { task: any }) {
  if (!task) return null;

  return (
    <div className="space-y-4">
      <div>
        <label className="text-sm text-gray-500 font-medium">Description</label>
        <p className="text-gray-900 mt-1">{task.description || "No description"}</p>
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="text-sm text-gray-500 font-medium">Status</label>
          <p className="text-gray-900 mt-1">{task.status_name}</p>
        </div>
        <div>
          <label className="text-sm text-gray-500 font-medium">Priority</label>
          <p className="text-gray-900 mt-1">{task.priority_name || "None"}</p>
        </div>
        <div>
          <label className="text-sm text-gray-500 font-medium">Due Date</label>
          <p className="text-gray-900 mt-1">
            {task.due_date ? new Date(task.due_date).toLocaleDateString() : "No due date"}
          </p>
        </div>
        <div>
          <label className="text-sm text-gray-500 font-medium">Completion</label>
          <p className="text-gray-900 mt-1">{task.completion_percentage || 0}%</p>
        </div>
      </div>
    </div>
  );
}

function SubtasksView({
  taskId,
  subtasks,
  onUpdate,
}: {
  taskId: string;
  subtasks: any[];
  onUpdate: () => void;
}) {
  const [showCreate, setShowCreate] = useState(false);
  const [newSubtask, setNewSubtask] = useState({ title: "", description: "" });

  const handleCreate = async () => {
    try {
      await api.post(`/modules/tasks/tasks/${taskId}/subtasks`, newSubtask);
      setNewSubtask({ title: "", description: "" });
      setShowCreate(false);
      onUpdate();
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to create subtask");
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold text-gray-900">Subtasks</h3>
        <button
          onClick={() => setShowCreate(!showCreate)}
          className="flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 rounded-lg text-white text-sm"
        >
          <Plus className="h-4 w-4" />
          Add Subtask
        </button>
      </div>

      {showCreate && (
        <div className="bg-gray-50 rounded-lg p-4 space-y-3 border border-gray-200">
          <input
            type="text"
            placeholder="Subtask title"
            value={newSubtask.title}
            onChange={(e) => setNewSubtask({ ...newSubtask, title: e.target.value })}
            className="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
          />
          <textarea
            placeholder="Description (optional)"
            value={newSubtask.description}
            onChange={(e) => setNewSubtask({ ...newSubtask, description: e.target.value })}
            className="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
            rows={3}
          />
          <div className="flex gap-2">
            <button
              onClick={handleCreate}
              className="px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 rounded-lg text-white text-sm"
            >
              Create
            </button>
            <button
              onClick={() => {
                setShowCreate(false);
                setNewSubtask({ title: "", description: "" });
              }}
              className="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 text-sm"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      <div className="space-y-2">
        {subtasks.map((subtask) => (
          <div
            key={subtask.id}
            className="bg-gray-50 border border-gray-200 rounded-lg p-3 flex items-center justify-between"
          >
            <div className="flex items-center gap-3">
              <CheckSquare className="h-4 w-4 text-purple-600" />
              <div>
                <p className="text-gray-900">{subtask.title}</p>
                {subtask.description && (
                  <p className="text-sm text-gray-500">{subtask.description}</p>
                )}
              </div>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs text-gray-500">
                {subtask.completion_percentage || 0}%
              </span>
              <button className="p-1 hover:bg-gray-100 rounded text-gray-500">
                <Edit className="h-4 w-4" />
              </button>
            </div>
          </div>
        ))}
        {subtasks.length === 0 && (
          <div className="text-center py-8 text-gray-500 text-sm">
            No subtasks yet
          </div>
        )}
      </div>
    </div>
  );
}

function TimeTrackingView({
  taskId,
  timeEntries,
  activeTracker,
  onStartTracker,
  onStopTracker,
  onUpdate,
}: {
  taskId: string;
  timeEntries: any[];
  activeTracker: any;
  onStartTracker: () => void;
  onStopTracker: () => void;
  onUpdate: () => void;
}) {
  const totalHours = timeEntries.reduce((sum, e) => sum + (e.hours || 0), 0);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-semibold text-gray-900">Time Tracking</h3>
          <p className="text-sm text-gray-500">Total: {totalHours.toFixed(2)} hours</p>
        </div>
        {activeTracker ? (
          <button
            onClick={onStopTracker}
            className="flex items-center gap-2 px-4 py-2 bg-red-500 hover:bg-red-600 rounded-lg text-white"
          >
            <Square className="h-4 w-4" />
            Stop Timer
          </button>
        ) : (
          <button
            onClick={onStartTracker}
            className="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 rounded-lg text-white"
          >
            <Play className="h-4 w-4" />
            Start Timer
          </button>
        )}
      </div>

      {activeTracker && (
        <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
          <p className="text-purple-700 text-sm">
            Timer running since {activeTracker.start_time ? new Date(activeTracker.start_time).toLocaleTimeString() : "just now"}
          </p>
        </div>
      )}

      <div className="space-y-2">
        {timeEntries.map((entry) => (
          <div
            key={entry.id}
            className="bg-gray-50 border border-gray-200 rounded-lg p-3 flex items-center justify-between"
          >
            <div>
              <p className="text-gray-900">{entry.description || "Time entry"}</p>
              <p className="text-sm text-gray-500">
                {new Date(entry.date).toLocaleDateString()}
              </p>
            </div>
            <div className="text-right">
              <p className="text-gray-900 font-medium">{entry.hours.toFixed(2)}h</p>
              {entry.is_billable && (
                <p className="text-xs text-green-600">Billable</p>
              )}
            </div>
          </div>
        ))}
        {timeEntries.length === 0 && (
          <div className="text-center py-8 text-gray-500 text-sm">
            No time entries yet
          </div>
        )}
      </div>
    </div>
  );
}

function DocumentsView({
  taskId,
  documents,
  onUpdate,
}: {
  taskId: string;
  documents: any[];
  onUpdate: () => void;
}) {
  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append("file", file);
    formData.append("task_id", taskId.toString());

    try {
      await api.post("/modules/tasks/documents", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      onUpdate();
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to upload document");
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold text-gray-900">Documents</h3>
        <label className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 rounded-lg text-white cursor-pointer text-sm">
          <Upload className="h-4 w-4" />
          Upload
          <input
            type="file"
            className="hidden"
            onChange={handleUpload}
          />
        </label>
      </div>

      <div className="space-y-2">
        {documents.map((doc) => (
          <div
            key={doc.id}
            className="bg-gray-50 border border-gray-200 rounded-lg p-3 flex items-center justify-between"
          >
            <div className="flex items-center gap-3">
              <FileText className="h-5 w-5 text-purple-600" />
              <div>
                <p className="text-gray-900">{doc.filename}</p>
                <p className="text-xs text-gray-500">
                  {(doc.file_size / 1024).toFixed(2)} KB • v{doc.version}
                </p>
              </div>
            </div>
            <button className="p-1 hover:bg-gray-100 rounded text-gray-500">
              <Download className="h-4 w-4" />
            </button>
          </div>
        ))}
        {documents.length === 0 && (
          <div className="text-center py-8 text-gray-500 text-sm">
            No documents yet
          </div>
        )}
      </div>
    </div>
  );
}

function ThreadsView({
  taskId,
  threads,
  onUpdate,
}: {
  taskId: string;
  threads: any[];
  onUpdate: () => void;
}) {
  const [newComment, setNewComment] = useState("");

  const handlePostComment = async () => {
    if (!newComment.trim()) return;

    try {
      await api.post("/modules/tasks/threads", {
        task_id: taskId,
        comment: newComment,
      });
      setNewComment("");
      onUpdate();
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to post comment");
    }
  };

  return (
    <div className="space-y-4">
      <h3 className="text-lg font-semibold text-gray-900">Comments & Threads</h3>

      <div className="space-y-3">
        <textarea
          placeholder="Add a comment..."
          value={newComment}
          onChange={(e) => setNewComment(e.target.value)}
          className="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
          rows={3}
        />
        <button
          onClick={handlePostComment}
          className="px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 rounded-lg text-white text-sm"
        >
          Post Comment
        </button>
      </div>

      <div className="space-y-3">
        {threads.map((thread) => (
          <div
            key={thread.id}
            className="bg-gray-50 border border-gray-200 rounded-lg p-4"
          >
            <div className="flex items-start gap-3">
              <div className="w-8 h-8 rounded-full bg-gradient-to-r from-purple-600 to-blue-600 flex items-center justify-center text-white text-sm">
                U
              </div>
              <div className="flex-1">
                <p className="text-gray-900">{thread.comment}</p>
                <p className="text-xs text-gray-500 mt-1">
                  {new Date(thread.created_at).toLocaleString()}
                </p>
                {thread.replies_count > 0 && (
                  <button className="text-xs text-purple-600 mt-2 hover:underline">
                    {thread.replies_count} replies
                  </button>
                )}
              </div>
            </div>
          </div>
        ))}
        {threads.length === 0 && (
          <div className="text-center py-8 text-gray-500 text-sm">
            No comments yet
          </div>
        )}
      </div>
    </div>
  );
}





