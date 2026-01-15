"use client";

import { Button } from "@/components/ui/button";
import { LayoutGrid, List, Filter } from "lucide-react";
import { CreateTaskButton } from "./CreateTaskButton";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";

interface TasksToolbarProps {
  viewMode: "kanban" | "list";
  onViewModeChange: (mode: "kanban" | "list") => void;
  onCreateTask: () => void;
}

export function TasksToolbar({
  viewMode,
  onViewModeChange,
  onCreateTask,
}: TasksToolbarProps) {
  const { canCreateTask } = useTaskAccess();
  
  return (
    <div className="flex items-center justify-between mb-4">
      <div className="flex items-center gap-2">
        <Button
          variant={viewMode === "list" ? "default" : "outline"}
          size="icon"
          onClick={() => onViewModeChange("list")}
        >
          <List className="h-4 w-4" />
        </Button>
        <Button
          variant={viewMode === "kanban" ? "default" : "outline"}
          size="icon"
          onClick={() => onViewModeChange("kanban")}
        >
          <LayoutGrid className="h-4 w-4" />
        </Button>
      </div>
      <div className="flex items-center gap-2">
        <Button variant="outline" size="icon">
          <Filter className="h-4 w-4" />
        </Button>
        {canCreateTask && <CreateTaskButton onClick={onCreateTask} />}
      </div>
    </div>
  );
}





