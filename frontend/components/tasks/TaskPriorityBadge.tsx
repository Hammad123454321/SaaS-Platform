import { Badge } from "@/components/ui/badge";

interface TaskPriorityBadgeProps {
  name: string;
  color?: string;
}

export function TaskPriorityBadge({ name, color }: TaskPriorityBadgeProps) {
  const bgColor = color ? `${color}20` : "rgba(107, 114, 128, 0.2)";
  const textColor = color || "#6b7280";

  return (
    <Badge
      className="text-xs font-medium"
      style={{
        backgroundColor: bgColor,
        color: textColor,
        border: `1px solid ${textColor}40`,
      }}
    >
      {name}
    </Badge>
  );
}





