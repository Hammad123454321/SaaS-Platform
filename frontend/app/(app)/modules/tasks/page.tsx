"use client";

import { useState } from "react";
import { useSessionStore } from "@/lib/store";
import { TasksHeader } from "./components/TasksHeader";
import { TasksTabs } from "./components/TasksTabs";
import { TasksToolbar } from "./components/TasksToolbar";
import { TasksContent } from "./components/TasksContent";
import { useTasksPage } from "./hooks/useTasksPage";
import { TaskDetailModal } from "@/components/tasks/TaskDetailModal";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { TaskForm } from "@/components/tasks/TaskForm";
import { useCreateTask } from "@/hooks/tasks/useCreateTask";
import { useUpdateTask } from "@/hooks/tasks/useUpdateTask";
import { TaskFormData } from "@/types/task";

type Tab = "tasks" | "projects" | "clients" | "statuses" | "priorities" | "team" | "milestones" | "task-lists" | "time-tracker" | "tags";

export default function TasksModulePage() {
  const { user } = useSessionStore();
  const [activeTab, setActiveTab] = useState<Tab>("tasks");
  const [viewMode, setViewMode] = useState<"kanban" | "list">("list");
  const [selectedTaskId, setSelectedTaskId] = useState<string | null>(null);
  const [showTaskModal, setShowTaskModal] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingTaskId, setEditingTaskId] = useState<string | null>(null);

  const {
    tasks,
    projects,
    projectsDropdown,
    clients,
    statuses,
    statusesDropdown,
    priorities,
    prioritiesDropdown,
    users,
    isLoading,
    error,
  } = useTasksPage(activeTab);

  const createTask = useCreateTask();
  const updateTask = useUpdateTask();

  const handleCreateTask = () => {
    setEditingTaskId(null);
    setShowCreateModal(true);
  };

  const handleEditTask = (taskId: string) => {
    setEditingTaskId(taskId);
    setShowCreateModal(true);
  };

  const handleTaskSubmit = async (data: TaskFormData) => {
    if (editingTaskId) {
      await updateTask.mutateAsync({ id: editingTaskId, data });
    } else {
      await createTask.mutateAsync(data);
    }
    setShowCreateModal(false);
    setEditingTaskId(null);
  };

  const handleTaskClick = (taskId: string) => {
    setSelectedTaskId(taskId);
    setShowTaskModal(true);
  };

  const taskToEdit = editingTaskId ? tasks.find(t => t.id === editingTaskId) : undefined;

  return (
    <div className="mx-auto max-w-7xl space-y-4">
      <TasksHeader user={user} />
      <TasksTabs
        activeTab={activeTab}
        onTabChange={setActiveTab}
        user={user}
      />
      {activeTab === "tasks" && (
        <TasksToolbar
          viewMode={viewMode}
          onViewModeChange={setViewMode}
          onCreateTask={handleCreateTask}
        />
      )}
      <TasksContent
        activeTab={activeTab}
        viewMode={viewMode}
        onViewModeChange={setViewMode}
        tasks={tasks}
        projects={projects}
        clients={clients}
        statuses={statuses}
        priorities={priorities}
        users={users}
        isLoading={isLoading}
        error={error}
        user={user}
        onTaskClick={handleTaskClick}
      />

      {/* Task Detail Modal */}
      {selectedTaskId && (
        <TaskDetailModal
          taskId={selectedTaskId}
          isOpen={showTaskModal}
          onClose={() => {
            setShowTaskModal(false);
            setSelectedTaskId(null);
          }}
          onUpdate={() => {
            // Refetch tasks
          }}
        />
      )}

      {/* Create/Edit Task Modal */}
      <Dialog open={showCreateModal} onOpenChange={setShowCreateModal}>
        <DialogContent className="glass max-w-2xl max-h-[90vh] overflow-y-auto">
          <TaskForm
            task={taskToEdit}
            projects={projectsDropdown}
            statuses={statusesDropdown}
            priorities={prioritiesDropdown}
            users={users}
            onSubmit={handleTaskSubmit}
            onCancel={() => {
              setShowCreateModal(false);
              setEditingTaskId(null);
            }}
            isLoading={createTask.isPending || updateTask.isPending}
          />
        </DialogContent>
      </Dialog>
    </div>
  );
}
