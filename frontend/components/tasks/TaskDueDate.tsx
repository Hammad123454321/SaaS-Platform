import { formatRelativeDate } from "@/lib/date";

interface TaskDueDateProps {
  date: string;
  overdue: boolean;
}

export function TaskDueDate({ date, overdue }: TaskDueDateProps) {
  return (
    <span
      className={`text-xs ${
        overdue ? "text-red-400" : "text-gray-400"
      }`}
    >
      {formatRelativeDate(date)}
    </span>
  );
}





