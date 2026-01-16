"use client";

import { useSessionStore } from "@/lib/store";

interface TasksHeaderProps {
  user: ReturnType<typeof useSessionStore>["user"];
}

export function TasksHeader({ user }: TasksHeaderProps) {
  return (
    <div className="mb-6">
      <h1 className="text-3xl font-bold text-gray-900 mb-2">Task Management</h1>
      <p className="text-gray-500">
        {user?.is_super_admin
          ? "Manage all tasks across your organization"
          : "Manage your tasks and projects"}
      </p>
    </div>
  );
}

