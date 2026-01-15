"use client";

import { Task } from "@/types/task";
import { Card } from "@/components/ui/card";
import { TaskStatusBadge } from "./TaskStatusBadge";
import { TaskPriorityBadge } from "./TaskPriorityBadge";
import { TaskActions } from "./TaskActions";
import { TaskAssignees } from "./TaskAssignees";
import { TaskDueDate } from "./TaskDueDate";
import { TaskProgressBar } from "./TaskProgressBar";
import { formatRelativeDate, isOverdue } from "@/lib/date";
import { motion } from "framer-motion";

interface TaskCardProps {
  task: Task;
  onClick?: () => void;
  onEdit?: () => void;
  onDelete?: () => void;
}

export function TaskCard({ task, onClick, onEdit, onDelete }: TaskCardProps) {
  const overdue = task.due_date ? isOverdue(task.due_date) : false;

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
    >
      <Card
        className="glass cursor-pointer hover:bg-white/10 transition p-4 space-y-3"
        onClick={onClick}
      >
        <div className="flex items-start justify-between">
          <div className="flex-1 space-y-2">
            <h4 className="font-semibold text-white line-clamp-2">{task.title}</h4>
            {task.description && (
              <p className="text-sm text-gray-300 line-clamp-2">{task.description}</p>
            )}
          </div>
          <TaskActions
            task={task}
            onEdit={onEdit}
            onDelete={onDelete}
            className="ml-2"
          />
        </div>

        <div className="flex items-center gap-2 flex-wrap">
          {task.status_name && (
            <TaskStatusBadge name={task.status_name} color={task.status_color} />
          )}
          {task.priority_name && (
            <TaskPriorityBadge name={task.priority_name} color={task.priority_color} />
          )}
        </div>

        {task.completion_percentage > 0 && (
          <TaskProgressBar percentage={task.completion_percentage} />
        )}

        <div className="flex items-center justify-between text-xs text-gray-400">
          <div className="flex items-center gap-3">
            {task.assignees && task.assignees.length > 0 && (
              <TaskAssignees assignees={task.assignees} />
            )}
            {task.due_date && (
              <TaskDueDate date={task.due_date} overdue={overdue} />
            )}
          </div>
          {task.project && (
            <span className="text-gray-500">{task.project.title}</span>
          )}
        </div>
      </Card>
    </motion.div>
  );
}





