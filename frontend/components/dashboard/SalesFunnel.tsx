"use client";

import { Card } from "@/components/ui/card";

interface FunnelStage {
  label: string;
  total: number;
  completed: number;
  color: string;
}

interface SalesFunnelProps {
  stages: FunnelStage[];
  className?: string;
}

export function SalesFunnel({ stages, className }: SalesFunnelProps) {
  return (
    <Card className={`p-6 ${className}`}>
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-900">Sales Funnel</h3>
        <div className="flex gap-2 text-sm">
          <span className="px-3 py-1 bg-gray-100 rounded-lg text-gray-700">Today</span>
        </div>
      </div>
      <div className="space-y-4">
        {stages.map((stage, index) => {
          const percentage = stage.total > 0 ? (stage.completed / stage.total) * 100 : 0;
          return (
            <div key={index} className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="font-medium text-gray-700">{stage.label}</span>
                <span className="text-gray-600">
                  {stage.completed}/{stage.total} ({Math.round(percentage)}%)
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-3">
                <div
                  className={`h-3 rounded-full transition-all`}
                  style={{
                    width: `${percentage}%`,
                    backgroundColor: stage.color,
                  }}
                />
              </div>
            </div>
          );
        })}
      </div>
      <div className="mt-4 pt-4 border-t border-gray-200">
        <p className="text-sm text-gray-600">
          Today's activity: <span className="font-semibold text-gray-900">13 new leads today</span>
        </p>
      </div>
    </Card>
  );
}

