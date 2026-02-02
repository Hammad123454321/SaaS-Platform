"use client";

import { Task } from "@/types/task";
import { Project } from "@/types/project";
import { Client } from "@/types/client";
import { Status } from "@/types/status";
import { Priority } from "@/types/priority";
import { DropdownItem } from "@/types/api";
import { KanbanView } from "@/components/tasks/KanbanView";
import { ListView } from "@/components/tasks/ListView/index";
import { LoadingSkeleton } from "@/components/shared/LoadingSkeleton";
import { EmptyState } from "@/components/shared/EmptyState";
import { ErrorMessage } from "@/components/shared/ErrorMessage";
import { CheckSquare } from "lucide-react";
import { useSessionStore, UserInfo } from "@/lib/store";
import { ProjectsView } from "@/components/tasks/ProjectsView";
import { ClientsView } from "@/components/tasks/ClientsView";
import { StatusesView } from "@/components/tasks/StatusesView";
import { PrioritiesView } from "@/components/tasks/PrioritiesView";
import { MilestonesView } from "@/components/tasks/MilestonesView";
import { TaskListsView } from "@/components/tasks/TaskListsView";
import { TagsView } from "@/components/tasks/TagsView";
import { TimeTrackerView } from "@/components/tasks/TimeTrackerView";
import { TaskMetricsView } from "@/components/tasks/TaskMetricsView";

interface TasksContentProps {
  activeTab: string;
  viewMode: "kanban" | "list";
  onViewModeChange: (mode: "kanban" | "list") => void;
  tasks: Task[];
  projects: Project[];
  clients: Client[];
  statuses: Status[];
  priorities: Priority[];
  users: DropdownItem[];
  isLoading: boolean;
  error: string | null;
  user: UserInfo | null;
  onTaskClick?: (taskId: string) => void;
}

export function TasksContent({
  activeTab,
  viewMode,
  tasks,
  isLoading,
  error,
  onTaskClick,
}: TasksContentProps) {
  if (isLoading) {
    return <LoadingSkeleton />;
  }

  if (error) {
    return <ErrorMessage message={error} />;
  }

  if (activeTab === "tasks") {
    if (tasks.length === 0) {
      return (
        <EmptyState
          icon={CheckSquare}
          title="No tasks found"
          description="Create your first task to get started"
        />
      );
    }

    return (
      <div>
        {viewMode === "kanban" ? (
          <KanbanView projectId={undefined} onTaskClick={onTaskClick} />
        ) : (
          <ListView tasks={tasks} onTaskClick={onTaskClick} />
        )}
      </div>
    );
  }

  // Render appropriate view based on active tab
  switch (activeTab) {
    case "projects":
      return <ProjectsView />;
    case "clients":
      return <ClientsView />;
    case "statuses":
      return <StatusesView />;
    case "priorities":
      return <PrioritiesView />;
    case "milestones":
      return <MilestonesView />;
    case "task-lists":
      return <TaskListsView />;
    case "tags":
      return <TagsView />;
    case "time-tracker":
      return <TimeTrackerView />;
    case "dashboard":
      return <TaskMetricsView />;
    default:
      return (
        <div className="text-center py-12 text-gray-500">
          Content for {activeTab} tab
        </div>
      );
  }
}
