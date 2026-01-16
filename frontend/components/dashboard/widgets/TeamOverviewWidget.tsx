"use client";

import { Users, MoreHorizontal } from "lucide-react";
import Link from "next/link";

interface TeamMember {
  id: number;
  name: string;
  email: string;
  role: string;
  avatar?: string;
  lastActive?: string;
  tasksCompleted?: number;
}

interface TeamOverviewWidgetProps {
  members: TeamMember[];
  totalCount?: number;
  className?: string;
}

export function TeamOverviewWidget({ 
  members, 
  totalCount,
  className 
}: TeamOverviewWidgetProps) {
  const getInitials = (name: string) => {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  };

  const getAvatarColor = (name: string) => {
    const colors = [
      'from-purple-500 to-pink-500',
      'from-cyan-500 to-blue-500',
      'from-emerald-500 to-teal-500',
      'from-orange-500 to-amber-500',
      'from-rose-500 to-pink-500',
    ];
    const index = name.length % colors.length;
    return colors[index];
  };

  return (
    <div className={`glass rounded-2xl p-5 ${className}`}>
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Users className="h-5 w-5 text-purple-600" />
          <h3 className="text-lg font-semibold text-gray-900">Team Overview</h3>
        </div>
        {totalCount && totalCount > members.length && (
          <span className="text-sm text-gray-500">+{totalCount - members.length} more</span>
        )}
      </div>

      {members.length === 0 ? (
        <div className="text-center py-8">
          <Users className="h-10 w-10 mx-auto mb-2 text-gray-300" />
          <p className="text-gray-500">No team members</p>
        </div>
      ) : (
        <div className="space-y-3">
          {members.map((member) => (
            <div
              key={member.id}
              className="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition"
            >
              <div className={`w-10 h-10 rounded-full bg-gradient-to-r ${getAvatarColor(member.name)} flex items-center justify-center text-white text-sm font-medium`}>
                {getInitials(member.name)}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-gray-900 font-medium truncate">{member.name}</p>
                <p className="text-gray-500 text-xs truncate">{member.role}</p>
              </div>
              <div className="text-right">
                {member.tasksCompleted !== undefined && (
                  <p className="text-purple-600 text-sm font-medium">{member.tasksCompleted} tasks</p>
                )}
                {member.lastActive && (
                  <p className="text-gray-400 text-xs">{member.lastActive}</p>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      <Link
        href="/admin/users"
        className="mt-4 flex items-center justify-center gap-2 p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition text-gray-600 hover:text-gray-900 text-sm"
      >
        <Users className="h-4 w-4" />
        Manage Team
      </Link>
    </div>
  );
}

