"use client";

import { Calendar, Clock, AlertTriangle } from "lucide-react";
import Link from "next/link";

interface Deadline {
  id: number;
  title: string;
  dueDate: string;
  daysLeft: number;
  project?: string;
  priority?: "low" | "medium" | "high" | "urgent";
}

interface UpcomingDeadlinesWidgetProps {
  deadlines: Deadline[];
  className?: string;
}

export function UpcomingDeadlinesWidget({ deadlines, className }: UpcomingDeadlinesWidgetProps) {
  const getUrgencyColor = (daysLeft: number) => {
    if (daysLeft <= 0) return "text-rose-600";
    if (daysLeft <= 2) return "text-orange-600";
    if (daysLeft <= 5) return "text-amber-600";
    return "text-emerald-600";
  };

  const getUrgencyBg = (daysLeft: number) => {
    if (daysLeft <= 0) return "bg-rose-100";
    if (daysLeft <= 2) return "bg-orange-100";
    if (daysLeft <= 5) return "bg-amber-100";
    return "bg-emerald-100";
  };

  return (
    <div className={`glass rounded-2xl p-5 ${className}`}>
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Calendar className="h-5 w-5 text-orange-500" />
          <h3 className="text-lg font-semibold text-gray-900">Upcoming Deadlines</h3>
        </div>
        <Link 
          href="/modules/tasks" 
          className="text-sm text-purple-600 hover:text-purple-700 font-medium"
        >
          View All →
        </Link>
      </div>

      {deadlines.length === 0 ? (
        <div className="text-center py-8">
          <Calendar className="h-10 w-10 mx-auto mb-2 text-gray-300" />
          <p className="text-gray-500">No upcoming deadlines</p>
        </div>
      ) : (
        <div className="space-y-2">
          {deadlines.map((deadline) => (
            <Link
              key={deadline.id}
              href="/modules/tasks"
              className="block p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition"
            >
              <div className="flex items-start gap-3">
                <div className={`p-2 rounded-lg ${getUrgencyBg(deadline.daysLeft)}`}>
                  {deadline.daysLeft <= 0 ? (
                    <AlertTriangle className={`h-4 w-4 ${getUrgencyColor(deadline.daysLeft)}`} />
                  ) : (
                    <Clock className={`h-4 w-4 ${getUrgencyColor(deadline.daysLeft)}`} />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <h4 className="text-gray-900 font-medium truncate">{deadline.title}</h4>
                  <div className="flex items-center gap-2 mt-1">
                    {deadline.project && (
                      <span className="text-gray-500 text-xs">{deadline.project}</span>
                    )}
                    <span className="text-gray-400 text-xs">•</span>
                    <span className="text-gray-500 text-xs">{deadline.dueDate}</span>
                  </div>
                </div>
                <span className={`text-xs font-medium ${getUrgencyColor(deadline.daysLeft)}`}>
                  {deadline.daysLeft <= 0 
                    ? "Overdue" 
                    : deadline.daysLeft === 1 
                      ? "Tomorrow" 
                      : `${deadline.daysLeft} days`
                  }
                </span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}

