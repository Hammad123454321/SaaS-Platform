"use client";

import { Task } from "@/types/task";
import { Button } from "@/components/ui/button";
import { Star, Pin, MoreVertical } from "lucide-react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";

interface TaskActionsProps {
  task: Task;
  onEdit?: () => void;
  onDelete?: () => void;
  className?: string;
}

export function TaskActions({
  task,
  onEdit,
  onDelete,
  className,
}: TaskActionsProps) {
  const { canUpdateTask, canDeleteTask, isReadOnly } = useTaskAccess();
  
  // Required tasks cannot be deleted
  const canDelete = canDeleteTask && !task.is_required;
  
  return (
    <div className={`flex items-center gap-1 ${className}`}>
      {!isReadOnly && (
        <>
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={(e) => {
              e.stopPropagation();
              // Handle favorite toggle
            }}
          >
            <Star
              className={`h-4 w-4 ${
                task.is_favorite ? "fill-yellow-400 text-yellow-400" : ""
              }`}
            />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={(e) => {
              e.stopPropagation();
              // Handle pin toggle
            }}
          >
            <Pin
              className={`h-4 w-4 ${
                task.is_pinned ? "fill-blue-400 text-blue-400" : ""
              }`}
            />
          </Button>
        </>
      )}
      {(canUpdateTask || canDelete) && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="h-8 w-8">
              <MoreVertical className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {canUpdateTask && onEdit && (
              <DropdownMenuItem onClick={onEdit}>Edit</DropdownMenuItem>
            )}
            {canDelete && onDelete && (
              <DropdownMenuItem onClick={onDelete} className="text-red-400">
                Delete
              </DropdownMenuItem>
            )}
            {task.is_required && (
              <DropdownMenuItem disabled className="text-gray-400">
                Required task (cannot delete)
              </DropdownMenuItem>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </div>
  );
}





