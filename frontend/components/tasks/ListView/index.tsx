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
  const groupedTasks = useMemo(() => {
    const groups: Record<string, Task[]> = {
      pinned: [],
      overdue: [],
      today: [],
      "this-week": [],
      upcoming: [],
      "no-date": [],
    };

    tasks.forEach((task) => {
      if (task.is_pinned) {
        groups.pinned.push(task);
      } else {
        const group = getDateGroup(task.due_date);
        groups[group].push(task);
      }
    });

    return groups;
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
      {Object.entries(groupedTasks).map(([key, groupTasks]) => {
        if (groupTasks.length === 0) return null;

        return (
          <div key={key} className="space-y-3">
            <h3 className="text-sm font-semibold text-gray-400 uppercase tracking-wide">
              {groupTitles[key]} ({groupTasks.length})
            </h3>
            <div className="grid gap-3">
              {groupTasks.map((task) => (
                <TaskCard
                  key={task.id}
                  task={task}
                  onClick={() => onTaskClick?.(task.id)}
                  onEdit={() => onEdit?.(task.id)}
                  onDelete={() => onDelete?.(task.id)}
                />
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}





