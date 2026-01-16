"use client";

import { CheckCircle2, Circle, Clock, AlertCircle } from "lucide-react";
import Link from "next/link";

interface Task {
  id: number;
  title: string;
  status: string;
  statusColor: string;
  dueDate?: string;
  project?: string;
  assignee?: string;
  isOverdue?: boolean;
}

interface RecentTasksWidgetProps {
  tasks: Task[];
  title?: string;
  showViewAll?: boolean;
  className?: string;
}

export function RecentTasksWidget({ 
  tasks, 
  title = "Recent Tasks",
  showViewAll = true,
  className 
}: RecentTasksWidgetProps) {
  return (
    <div className={`glass rounded-2xl p-5 ${className}`}>
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
        {showViewAll && (
          <Link 
            href="/modules/tasks" 
            className="text-sm text-purple-600 hover:text-purple-700 font-medium"
          >
            View All →
          </Link>
        )}
      </div>

      {tasks.length === 0 ? (
        <div className="text-center py-8">
          <Circle className="h-10 w-10 mx-auto mb-2 text-gray-300" />
          <p className="text-gray-500">No tasks found</p>
        </div>
      ) : (
        <div className="space-y-2">
          {tasks.map((task) => (
            <Link
              key={task.id}
              href="/modules/tasks"
              className="block p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition"
            >
              <div className="flex items-start gap-3">
                <div className="mt-0.5">
                  {task.status.toLowerCase().includes('done') || task.status.toLowerCase().includes('complete') ? (
                    <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                  ) : (
                    <Circle className="h-5 w-5 text-gray-300" />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <h4 className="text-gray-900 font-medium truncate">{task.title}</h4>
                    {task.isOverdue && (
                      <span className="flex items-center gap-1 text-xs text-rose-600 bg-rose-100 px-2 py-0.5 rounded-full">
                        <AlertCircle className="h-3 w-3" />
                        Overdue
                      </span>
                    )}
                  </div>
                  <div className="flex items-center gap-3 text-xs text-gray-500">
                    {task.project && <span>{task.project}</span>}
                    {task.dueDate && (
                      <span className="flex items-center gap-1">
                        <Clock className="h-3 w-3" />
                        {task.dueDate}
                      </span>
                    )}
                  </div>
                </div>
                <span
                  className="rounded-full px-2 py-1 text-xs font-medium shrink-0"
                  style={{
                    backgroundColor: `${task.statusColor}20`,
                    color: task.statusColor,
                  }}
                >
                  {task.status}
                </span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}

