"use client";

import { useRouter } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { UserInfo } from "@/lib/store";

interface TasksHeaderProps {
  user: UserInfo | null;
}

export function TasksHeader({ user }: TasksHeaderProps) {
  const router = useRouter();

  return (
    <div className="mb-6">
      <div className="flex items-center gap-3 mb-2">
        <button
          type="button"
          onClick={() => router.push("/dashboard")}
          className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          <ArrowLeft className="h-4 w-4" />
          Back
        </button>
        <h1 className="text-3xl font-bold text-gray-900">Task Management</h1>
      </div>
      <p className="text-gray-500">
        {user?.is_super_admin
          ? "Manage all tasks across your organization"
          : "Manage your tasks and projects"}
      </p>
    </div>
  );
}
