"use client";

import { Activity, CheckSquare, Users, Calendar, Bot, FileText } from "lucide-react";

interface ActivityItem {
  id: number;
  type: "task" | "user" | "booking" | "ai" | "document";
  title: string;
  description: string;
  timestamp: string;
  user?: string;
}

interface ActivityFeedWidgetProps {
  activities: ActivityItem[];
  className?: string;
}

const typeIcons = {
  task: CheckSquare,
  user: Users,
  booking: Calendar,
  ai: Bot,
  document: FileText,
};

const typeColors = {
  task: "text-purple-600 bg-purple-100",
  user: "text-blue-600 bg-blue-100",
  booking: "text-orange-600 bg-orange-100",
  ai: "text-fuchsia-600 bg-fuchsia-100",
  document: "text-cyan-600 bg-cyan-100",
};

export function ActivityFeedWidget({ activities, className }: ActivityFeedWidgetProps) {
  return (
    <div className={`glass rounded-2xl p-5 ${className}`}>
      <div className="flex items-center gap-2 mb-4">
        <Activity className="h-5 w-5 text-purple-600" />
        <h3 className="text-lg font-semibold text-gray-900">Recent Activity</h3>
      </div>

      {activities.length === 0 ? (
        <div className="text-center py-8">
          <Activity className="h-10 w-10 mx-auto mb-2 text-gray-300" />
          <p className="text-gray-500">No recent activity</p>
        </div>
      ) : (
        <div className="space-y-3">
          {activities.map((activity) => {
            const Icon = typeIcons[activity.type];
            const colorClass = typeColors[activity.type];
            
            return (
              <div key={activity.id} className="flex gap-3">
                <div className={`w-8 h-8 rounded-lg ${colorClass} flex items-center justify-center shrink-0`}>
                  <Icon className="h-4 w-4" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-gray-900 text-sm font-medium">{activity.title}</p>
                  <p className="text-gray-500 text-xs truncate">{activity.description}</p>
                  <div className="flex items-center gap-2 mt-1 text-xs text-gray-400">
                    {activity.user && <span>{activity.user}</span>}
                    {activity.user && <span>•</span>}
                    <span>{activity.timestamp}</span>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

