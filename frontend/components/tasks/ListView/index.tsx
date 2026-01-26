"use client";

import { useMemo } from "react";
import { Task } from "@/types/task";
import { TaskCard } from "../TaskCard";
import { getDateGroup } from "@/lib/date";

interface ListViewProps {
  tasks: Task[];
  onTaskClick?: (taskId: number) => void;
  onEdit?: (taskId: number) => void;
  onDelete?: (taskId: number) => void;
}

export function ListView({
  tasks,
  onTaskClick,
  onEdit,
  onDelete,
}: ListViewProps) {
  const { groups, subtasksByParent } = useMemo(() => {
    const taskGroups: Record<string, Task[]> = {
      pinned: [],
      overdue: [],
      today: [],
      "this-week": [],
      upcoming: [],
      "no-date": [],
    };

    // Separate parent tasks from subtasks
    const parentTasks: Task[] = [];
    const subtasksByParentId: Record<number, Task[]> = {};

    tasks.forEach((task) => {
      // Check if task has parent_id (it's a subtask)
      if (task.parent_id) {
        if (!subtasksByParentId[task.parent_id]) {
          subtasksByParentId[task.parent_id] = [];
        }
        subtasksByParentId[task.parent_id].push(task);
      } else {
        // It's a parent task
        parentTasks.push(task);
      }
    });

    // Group only parent tasks by date
    parentTasks.forEach((task) => {
      if (task.is_pinned) {
        taskGroups.pinned.push(task);
      } else {
        const group = getDateGroup(task.due_date);
        taskGroups[group].push(task);
      }
    });

    return { groups: taskGroups, subtasksByParent: subtasksByParentId };
  }, [tasks]);

  const groupTitles: Record<string, string> = {
    pinned: "Pinned",
    overdue: "Overdue",
    today: "Due Today",
    "this-week": "This Week",
    upcoming: "Upcoming",
    "no-date": "No Due Date",
  };

  return (
    <div className="space-y-6">
      {Object.entries(groups).map(([key, groupTasks]) => {
        if (groupTasks.length === 0) return null;

        return (
          <div key={key} className="space-y-3">
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wide">
              {groupTitles[key]} ({groupTasks.length})
            </h3>
            <div className="space-y-3">
              {groupTasks.map((task) => (
                <div key={task.id}>
                  <TaskCard
                    task={task}
                    onClick={() => onTaskClick?.(task.id)}
                    onEdit={() => onEdit?.(task.id)}
                    onDelete={() => onDelete?.(task.id)}
                  />
                  {/* Render subtasks nested under parent */}
                  {subtasksByParent[task.id] && subtasksByParent[task.id].length > 0 && (
                    <div className="ml-8 mt-2 space-y-2 border-l-2 border-gray-200 pl-4">
                      {subtasksByParent[task.id].map((subtask) => (
                        <div key={subtask.id} className="opacity-90">
                          <TaskCard
                            task={subtask}
                            onClick={() => onTaskClick?.(subtask.id)}
                            onEdit={() => onEdit?.(subtask.id)}
                            onDelete={() => onDelete?.(subtask.id)}
                          />
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}





