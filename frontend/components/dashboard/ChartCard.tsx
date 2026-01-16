"use client";

import { Card } from "@/components/ui/card";
import { ReactNode } from "react";

interface ChartCardProps {
  title: string;
  children: ReactNode;
  subtitle?: string;
  action?: ReactNode;
  className?: string;
}

export function ChartCard({
  title,
  children,
  subtitle,
  action,
  className,
}: ChartCardProps) {
  return (
    <Card className={`p-6 ${className}`}>
      <div className="flex items-center justify-between mb-4">
        <div>
          <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
          {subtitle && (
            <p className="text-sm text-gray-500 mt-1">{subtitle}</p>
          )}
        </div>
        {action && <div>{action}</div>}
      </div>
      <div className="h-64">{children}</div>
    </Card>
  );
}

