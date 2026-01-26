"use client";

import { useMemo, useState } from "react";
import { useKanban, useMoveTask } from "@/hooks/tasks/useKanban";
import { api } from "@/lib/api";
import { Task } from "@/types/task";
import {
  DndContext,
  DragOverlay,
  closestCorners,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
  DragStartEvent,
} from "@dnd-kit/core";
import {
  SortableContext,
  verticalListSortingStrategy,
  useSortable,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import {
  CheckSquare,
  Clock,
  User,
  AlertCircle,
  Star,
  Pin,
  MoreVertical,
  GripVertical,
} from "lucide-react";

import { KanbanColumn as KanbanColumnType } from "@/types/task";

interface KanbanViewProps {
  projectId?: number;
  onTaskClick?: (taskId: number) => void;
}

export function KanbanView({ projectId, onTaskClick }: KanbanViewProps) {
  const { data: columns, isLoading, error } = useKanban(projectId);
  const moveTask = useMoveTask();
  const [activeTask, setActiveTask] = useState<Task | null>(null);
  const [draggedTaskId, setDraggedTaskId] = useState<number | null>(null);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor)
  );

  const handleDragStart = (event: DragStartEvent) => {
    const { active } = event;
    const taskId = Number(active.id);
    
    // Find the task being dragged
    if (columns) {
      for (const column of Object.values(columns)) {
        const task = column.tasks.find((t) => t.id === taskId);
        if (task) {
          setActiveTask(task);
          setDraggedTaskId(taskId);
          break;
        }
      }
    }
  };

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active, over } = event;
    setActiveTask(null);
    setDraggedTaskId(null);

    if (!over) return;

    const taskId = Number(active.id);
    const targetStatusId = Number(over.id);

    // Find current status
    let currentStatusId: number | null = null;
    if (columns) {
      for (const [statusName, column] of Object.entries(columns)) {
        if (column.tasks.some((t) => t.id === taskId)) {
          currentStatusId = column.status_id;
          break;
        }
      }
    }

    if (currentStatusId === targetStatusId) return;

    // Update on server using mutation
    moveTask.mutate({ taskId, statusId: targetStatusId });
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-gray-500">Loading Kanban board...</div>
      </div>
    );
  }

  if (error || !columns) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-red-600">Failed to load Kanban board</div>
      </div>
    );
  }

  const columnNames = Object.keys(columns);

  return (
    <div className="h-full overflow-x-auto pb-4">
      <DndContext
        sensors={sensors}
        collisionDetection={closestCorners}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
      >
        <div className="flex gap-4 min-w-max px-4">
          {columnNames.map((statusName) => {
            const column = columns[statusName];
            return (
              <KanbanColumn
                key={column.status_id}
                column={column}
                onTaskClick={onTaskClick}
              />
            );
          })}
        </div>
        <DragOverlay>
          {activeTask ? (
            <div className="bg-white rounded-lg p-3 border border-gray-200 shadow-lg">
              <p className="text-gray-900 font-medium">{activeTask.title}</p>
            </div>
          ) : null}
        </DragOverlay>
      </DndContext>
    </div>
  );
}

function KanbanColumn({
  column,
  onTaskClick,
}: {
  column: KanbanColumnType;
  onTaskClick?: (taskId: number) => void;
}) {
  const taskIds = useMemo(() => column.tasks.map((t) => t.id), [column.tasks]);

  return (
    <div className="flex-shrink-0 w-80">
      <div
        className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 h-full flex flex-col"
        style={{ borderTop: `4px solid ${column.status_color}` }}
      >
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <h3 className="font-semibold text-gray-900">{column.status_name}</h3>
            <span className="text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded-full">
              {column.count}
            </span>
          </div>
        </div>
        <SortableContext
          items={taskIds}
          strategy={verticalListSortingStrategy}
          id={column.status_id.toString()}
        >
          <div className="flex-1 space-y-2 overflow-y-auto max-h-[calc(100vh-250px)]">
            {column.tasks.map((task) => (
              <SortableTaskCard
                key={task.id}
                task={task}
                onClick={() => onTaskClick?.(task.id)}
              />
            ))}
            {column.tasks.length === 0 && (
              <div className="text-center py-8 text-gray-500 text-sm">
                No tasks
              </div>
            )}
          </div>
        </SortableContext>
      </div>
    </div>
  );
}

function SortableTaskCard({
  task,
  onClick,
}: {
  task: Task;
  onClick?: () => void;
}) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({
    id: task.id,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...attributes}
      {...listeners}
      onClick={onClick}
      className="bg-gray-50 rounded-lg p-3 cursor-pointer hover:bg-gray-100 transition border border-gray-200"
    >
      <TaskCardContent task={task} />
    </div>
  );
}

function TaskCardContent({ task }: { task: Task }) {
  const isOverdue =
    task.due_date && new Date(task.due_date) < new Date() && task.completion_percentage < 100;

  return (
    <>
      <div className="flex items-start justify-between mb-2">
        <h4 className="font-medium text-gray-900 text-sm flex-1 line-clamp-2">
          {task.title}
        </h4>
        <GripVertical className="h-4 w-4 text-gray-400 flex-shrink-0 ml-2" />
      </div>

      {task.description && (
        <p className="text-xs text-gray-500 mb-2 line-clamp-2">
          {task.description}
        </p>
      )}

      <div className="flex items-center gap-2 mb-2 flex-wrap">
        {task.priority_name && (
          <span
            className="text-xs px-2 py-0.5 rounded font-medium"
            style={{
              backgroundColor: `${task.priority_color || "#6b7280"}20`,
              color: task.priority_color || "#6b7280",
            }}
          >
            {task.priority_name}
          </span>
        )}
        {(task.subtasks_count || (task.subtasks && task.subtasks.length > 0)) && (
          <span className="text-xs text-gray-500 flex items-center gap-1">
            <CheckSquare className="h-3 w-3" />
            {task.subtasks_count || task.subtasks?.length || 0}
          </span>
        )}
      </div>

      <div className="flex items-center justify-between mt-2">
        <div className="flex items-center gap-2">
          {task.assignees && task.assignees.length > 0 && (
            <div className="flex items-center gap-1">
              {task.assignees.slice(0, 3).map((assignee, idx) => (
                <div
                  key={assignee.id}
                  className="w-6 h-6 rounded-full bg-gradient-to-r from-purple-600 to-blue-600 flex items-center justify-center text-xs text-white"
                  style={{ marginLeft: idx > 0 ? "-8px" : "0" }}
                  title={assignee.email || `${assignee.first_name} ${assignee.last_name}`}
                >
                  {(assignee.first_name?.[0] || assignee.email?.[0] || "U").toUpperCase()}
                </div>
              ))}
              {task.assignees.length > 3 && (
                <span className="text-xs text-gray-500">
                  +{task.assignees.length - 3}
                </span>
              )}
            </div>
          )}
        </div>

        <div className="flex items-center gap-2">
          {isOverdue && (
            <AlertCircle className="h-4 w-4 text-red-500" />
          )}
          {task.due_date && (
            <span className="text-xs text-gray-500 flex items-center gap-1">
              <Clock className="h-3 w-3" />
              {new Date(task.due_date).toLocaleDateString()}
            </span>
          )}
        </div>
      </div>

      {task.completion_percentage > 0 && (
        <div className="mt-2">
          <div className="h-1 bg-gray-200 rounded-full overflow-hidden">
            <div
              className="h-full bg-gradient-to-r from-purple-600 to-blue-600 transition-all"
              style={{ width: `${task.completion_percentage}%` }}
            />
          </div>
        </div>
      )}
    </>
  );
}


