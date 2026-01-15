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
} from "lucide-react";
import { TaskDependenciesView } from "./TaskDependenciesView";
import { RecurringTaskView } from "./RecurringTaskView";

interface TaskDetailModalProps {
  taskId: number;
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
      <div className="glass rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-white/10">
          <h2 className="text-2xl font-semibold text-white">
            {loading ? "Loading..." : task?.title || "Task Details"}
          </h2>
          <button
            onClick={onClose}
            className="p-2 hover:bg-white/10 rounded-lg text-gray-400 hover:text-white"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex border-b border-white/10 px-6 overflow-x-auto">
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
                  ? "border-cyan-400 text-cyan-400"
                  : "border-transparent text-gray-400 hover:text-white"
              }`}
            >
              <tab.icon className="h-4 w-4 inline mr-2" />
              {tab.label}
            </button>
          ))}
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-6">
          {loading ? (
            <div className="text-center py-12 text-gray-300">Loading...</div>
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
        <label className="text-sm text-gray-400">Description</label>
        <p className="text-white mt-1">{task.description || "No description"}</p>
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="text-sm text-gray-400">Status</label>
          <p className="text-white mt-1">{task.status_name}</p>
        </div>
        <div>
          <label className="text-sm text-gray-400">Priority</label>
          <p className="text-white mt-1">{task.priority_name || "None"}</p>
        </div>
        <div>
          <label className="text-sm text-gray-400">Due Date</label>
          <p className="text-white mt-1">
            {task.due_date ? new Date(task.due_date).toLocaleDateString() : "No due date"}
          </p>
        </div>
        <div>
          <label className="text-sm text-gray-400">Completion</label>
          <p className="text-white mt-1">{task.completion_percentage || 0}%</p>
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
  taskId: number;
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
        <h3 className="text-lg font-semibold text-white">Subtasks</h3>
        <button
          onClick={() => setShowCreate(!showCreate)}
          className="flex items-center gap-2 px-3 py-1.5 bg-cyan-500 hover:bg-cyan-600 rounded-lg text-white text-sm"
        >
          <Plus className="h-4 w-4" />
          Add Subtask
        </button>
      </div>

      {showCreate && (
        <div className="bg-white/5 rounded-lg p-4 space-y-3">
          <input
            type="text"
            placeholder="Subtask title"
            value={newSubtask.title}
            onChange={(e) => setNewSubtask({ ...newSubtask, title: e.target.value })}
            className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-cyan-400"
          />
          <textarea
            placeholder="Description (optional)"
            value={newSubtask.description}
            onChange={(e) => setNewSubtask({ ...newSubtask, description: e.target.value })}
            className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-cyan-400"
            rows={3}
          />
          <div className="flex gap-2">
            <button
              onClick={handleCreate}
              className="px-4 py-2 bg-cyan-500 hover:bg-cyan-600 rounded-lg text-white text-sm"
            >
              Create
            </button>
            <button
              onClick={() => {
                setShowCreate(false);
                setNewSubtask({ title: "", description: "" });
              }}
              className="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-white text-sm"
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
            className="bg-white/5 rounded-lg p-3 flex items-center justify-between"
          >
            <div className="flex items-center gap-3">
              <CheckSquare className="h-4 w-4 text-gray-400" />
              <div>
                <p className="text-white">{subtask.title}</p>
                {subtask.description && (
                  <p className="text-sm text-gray-400">{subtask.description}</p>
                )}
              </div>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs text-gray-400">
                {subtask.completion_percentage || 0}%
              </span>
              <button className="p-1 hover:bg-white/10 rounded text-gray-400">
                <Edit className="h-4 w-4" />
              </button>
            </div>
          </div>
        ))}
        {subtasks.length === 0 && (
          <div className="text-center py-8 text-gray-400 text-sm">
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
  taskId: number;
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
          <h3 className="text-lg font-semibold text-white">Time Tracking</h3>
          <p className="text-sm text-gray-400">Total: {totalHours.toFixed(2)} hours</p>
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
        <div className="bg-cyan-500/20 border border-cyan-500/50 rounded-lg p-4">
          <p className="text-cyan-200 text-sm">
            Timer running since {activeTracker.start_time ? new Date(activeTracker.start_time).toLocaleTimeString() : "just now"}
          </p>
        </div>
      )}

      <div className="space-y-2">
        {timeEntries.map((entry) => (
          <div
            key={entry.id}
            className="bg-white/5 rounded-lg p-3 flex items-center justify-between"
          >
            <div>
              <p className="text-white">{entry.description || "Time entry"}</p>
              <p className="text-sm text-gray-400">
                {new Date(entry.date).toLocaleDateString()}
              </p>
            </div>
            <div className="text-right">
              <p className="text-white font-medium">{entry.hours.toFixed(2)}h</p>
              {entry.is_billable && (
                <p className="text-xs text-green-400">Billable</p>
              )}
            </div>
          </div>
        ))}
        {timeEntries.length === 0 && (
          <div className="text-center py-8 text-gray-400 text-sm">
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
  taskId: number;
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
        <h3 className="text-lg font-semibold text-white">Documents</h3>
        <label className="flex items-center gap-2 px-4 py-2 bg-cyan-500 hover:bg-cyan-600 rounded-lg text-white cursor-pointer text-sm">
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
            className="bg-white/5 rounded-lg p-3 flex items-center justify-between"
          >
            <div className="flex items-center gap-3">
              <FileText className="h-5 w-5 text-gray-400" />
              <div>
                <p className="text-white">{doc.filename}</p>
                <p className="text-xs text-gray-400">
                  {(doc.file_size / 1024).toFixed(2)} KB • v{doc.version}
                </p>
              </div>
            </div>
            <button className="p-1 hover:bg-white/10 rounded text-gray-400">
              <Download className="h-4 w-4" />
            </button>
          </div>
        ))}
        {documents.length === 0 && (
          <div className="text-center py-8 text-gray-400 text-sm">
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
  taskId: number;
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
      <h3 className="text-lg font-semibold text-white">Comments & Threads</h3>

      <div className="space-y-3">
        <textarea
          placeholder="Add a comment..."
          value={newComment}
          onChange={(e) => setNewComment(e.target.value)}
          className="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-cyan-400"
          rows={3}
        />
        <button
          onClick={handlePostComment}
          className="px-4 py-2 bg-cyan-500 hover:bg-cyan-600 rounded-lg text-white text-sm"
        >
          Post Comment
        </button>
      </div>

      <div className="space-y-3">
        {threads.map((thread) => (
          <div
            key={thread.id}
            className="bg-white/5 rounded-lg p-4"
          >
            <div className="flex items-start gap-3">
              <div className="w-8 h-8 rounded-full bg-cyan-500 flex items-center justify-center text-white text-sm">
                U
              </div>
              <div className="flex-1">
                <p className="text-white">{thread.comment}</p>
                <p className="text-xs text-gray-400 mt-1">
                  {new Date(thread.created_at).toLocaleString()}
                </p>
                {thread.replies_count > 0 && (
                  <button className="text-xs text-cyan-400 mt-2">
                    {thread.replies_count} replies
                  </button>
                )}
              </div>
            </div>
          </div>
        ))}
        {threads.length === 0 && (
          <div className="text-center py-8 text-gray-400 text-sm">
            No comments yet
          </div>
        )}
      </div>
    </div>
  );
}






