import { ReactNode } from "react";
import { cn } from "@/lib/utils";

interface MetricCardProps {
  title: string;
  value: string | number;
  subtitle?: string;
  icon?: ReactNode;
  color?: "yellow" | "pink" | "blue" | "green" | "purple" | "default";
  className?: string;
}

const colorClasses = {
  yellow: "text-amber-700",
  pink: "text-rose-700",
  blue: "text-blue-700",
  green: "text-emerald-700",
  purple: "text-purple-700",
  default: "text-gray-900",
};

const iconBgClasses = {
  yellow: "bg-amber-100 text-amber-600",
  pink: "bg-rose-100 text-rose-600",
  blue: "bg-blue-100 text-blue-600",
  green: "bg-emerald-100 text-emerald-600",
  purple: "bg-purple-100 text-purple-600",
  default: "bg-gray-100 text-gray-600",
};

export function MetricCard({
  title,
  value,
  subtitle,
  icon,
  color = "default",
  className,
}: MetricCardProps) {
  return (
    <div className={cn("py-4", className)}>
      <div className="flex items-start justify-between">
        <div className="flex-1">
          <p className="text-xs font-medium uppercase tracking-wider text-gray-500">{title}</p>
          <p className={cn("text-3xl font-bold mt-1", colorClasses[color])}>{value}</p>
          {subtitle && <p className="text-sm mt-1 text-gray-500">{subtitle}</p>}
        </div>
        {icon && (
          <div className={cn("ml-4 flex h-12 w-12 items-center justify-center rounded-full", iconBgClasses[color])}>
            {icon}
          </div>
        )}
      </div>
    </div>
  );
}

