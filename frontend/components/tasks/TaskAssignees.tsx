import { TaskAssignee } from "@/types/task";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";

interface TaskAssigneesProps {
  assignees: TaskAssignee[];
}

export function TaskAssignees({ assignees }: TaskAssigneesProps) {
  if (!assignees || assignees.length === 0) return null;

  return (
    <div className="flex items-center gap-1">
      {assignees.slice(0, 3).map((assignee) => (
        <Avatar key={assignee.id} className="h-6 w-6">
          <AvatarFallback className="text-xs bg-primary/20 text-primary">
            {assignee.first_name?.[0] || assignee.email?.[0] || "U"}
          </AvatarFallback>
        </Avatar>
      ))}
      {assignees.length > 3 && (
        <span className="text-xs text-gray-400">+{assignees.length - 3}</span>
      )}
    </div>
  );
}





