"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";
import Link from "next/link";
import { LayoutDashboard, Wand2, CheckSquare, Clock, AlertCircle } from "lucide-react";

type Entitlement = {
  module_code: string;
  enabled: boolean;
  seats: number;
  ai_access: boolean;
};

const moduleLabels: Record<string, string> = {
  crm: "CRM",
  hrm: "HRM",
  pos: "POS",
  tasks: "Tasks",
  booking: "Booking",
  landing: "Landing Builder",
  ai: "AI",
};

export default function StaffDashboard() {
  const { entitlements, setEntitlements, user } = useSessionStore();
  const [loading, setLoading] = useState(false);
  const [myTasks, setMyTasks] = useState<any[]>([]);
  const [tasksLoading, setTasksLoading] = useState(false);

  useEffect(() => {
    const fetchEntitlements = async () => {
      setLoading(true);
      try {
        const res = await api.get<Entitlement[]>("/entitlements");
        setEntitlements(res.data);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchEntitlements();
  }, [setEntitlements]);

  useEffect(() => {
    const fetchMyTasks = async () => {
      const tasksEnabled = entitlements.find((e) => e.module_code === "tasks" && e.enabled);
      if (!tasksEnabled) return;
      
      setTasksLoading(true);
      try {
        const res = await api.get("/modules/tasks/my-tasks");
        setMyTasks(res.data?.data || []);
      } catch (err) {
        console.error("Failed to load my tasks:", err);
      } finally {
        setTasksLoading(false);
      }
    };
    if (entitlements.length > 0) {
      fetchMyTasks();
    }
  }, [entitlements]);

  const enabledModules = entitlements.filter((e) => e.enabled);
  
  // Get task statistics
  const tasksStats = {
    total: myTasks.length,
    pending: myTasks.filter((t) => t.status_name?.toLowerCase().includes("todo") || t.status_name?.toLowerCase().includes("pending")).length,
    inProgress: myTasks.filter((t) => t.status_name?.toLowerCase().includes("progress")).length,
    completed: myTasks.filter((t) => t.status_name?.toLowerCase().includes("done") || t.status_name?.toLowerCase().includes("complete")).length,
    overdue: myTasks.filter((t) => {
      if (!t.due_date) return false;
      return new Date(t.due_date) < new Date() && !t.status_name?.toLowerCase().includes("done");
    }).length,
  };

  return (
    <div className="space-y-8">
      <header className="glass rounded-2xl px-6 py-5 shadow-xl">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Workspace</p>
            <h1 className="text-3xl font-semibold text-white">My Dashboard</h1>
            <p className="text-sm text-gray-200/80">
              Access your enabled modules and quick actions.
            </p>
          </div>
          <div className="flex items-center gap-3 text-sm text-gray-100">
            <CheckSquare className="h-5 w-5 text-emerald-300" />
            Staff Access
          </div>
        </div>
      </header>

      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {enabledModules.length === 0 && !loading && (
          <div className="glass col-span-full flex items-center justify-between rounded-xl p-4">
            <div>
              <p className="font-semibold text-white">No modules enabled</p>
              <p className="text-sm text-gray-200/80">
                Contact your administrator to enable modules for your account.
              </p>
            </div>
            <LayoutDashboard className="h-6 w-6 text-cyan-200" />
          </div>
        )}
        {enabledModules.map((m) => (
          <Link
            key={m.module_code}
            href={`/modules/${m.module_code}`}
            className="glass rounded-xl p-4 shadow transition hover:-translate-y-1 hover:shadow-2xl"
          >
            <div className="flex items-center justify-between">
              <div className="text-lg font-semibold text-white">
                {moduleLabels[m.module_code] || m.module_code.toUpperCase()}
              </div>
              <LayoutDashboard className="h-5 w-5 text-cyan-200" />
            </div>
            <p className="mt-2 text-sm text-gray-200/80">
              {m.ai_access ? "AI enabled" : "Standard access"}
            </p>
          </Link>
        ))}
      </section>

      {/* My Tasks Section */}
      {enabledModules.some((m) => m.module_code === "tasks") && (
        <section className="glass rounded-2xl p-5 shadow-xl">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <CheckSquare className="h-5 w-5 text-cyan-300" />
              <h2 className="text-lg font-semibold text-white">My Tasks</h2>
            </div>
            <Link
              href="/modules/tasks"
              className="text-sm text-cyan-300 hover:text-cyan-200 underline"
            >
              View All
            </Link>
          </div>
          
          {/* Task Statistics */}
          <div className="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-5">
            <div className="rounded-lg border border-white/10 bg-white/5 p-3">
              <div className="text-2xl font-bold text-white">{tasksStats.total}</div>
              <div className="text-xs text-gray-300">Total</div>
            </div>
            <div className="rounded-lg border border-white/10 bg-white/5 p-3">
              <div className="text-2xl font-bold text-yellow-300">{tasksStats.pending}</div>
              <div className="text-xs text-gray-300">Pending</div>
            </div>
            <div className="rounded-lg border border-white/10 bg-white/5 p-3">
              <div className="text-2xl font-bold text-blue-300">{tasksStats.inProgress}</div>
              <div className="text-xs text-gray-300">In Progress</div>
            </div>
            <div className="rounded-lg border border-white/10 bg-white/5 p-3">
              <div className="text-2xl font-bold text-green-300">{tasksStats.completed}</div>
              <div className="text-xs text-gray-300">Completed</div>
            </div>
            <div className="rounded-lg border border-red-400/30 bg-red-500/10 p-3">
              <div className="text-2xl font-bold text-red-300">{tasksStats.overdue}</div>
              <div className="text-xs text-gray-300">Overdue</div>
            </div>
          </div>

          {/* Recent Tasks */}
          {tasksLoading ? (
            <div className="text-center py-4 text-gray-300">Loading tasks...</div>
          ) : myTasks.length === 0 ? (
            <div className="text-center py-4 text-gray-300">
              <CheckSquare className="h-8 w-8 mx-auto mb-2 text-gray-400" />
              <p>No tasks assigned to you yet</p>
            </div>
          ) : (
            <div className="space-y-2">
              {myTasks.slice(0, 5).map((task) => (
                <Link
                  key={task.id}
                  href="/modules/tasks"
                  className="block rounded-lg border border-white/10 bg-white/5 p-3 hover:bg-white/10 transition"
                >
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <h3 className="font-semibold text-white">{task.title}</h3>
                        {task.due_date && new Date(task.due_date) < new Date() && !task.status_name?.toLowerCase().includes("done") && (
                          <span className="flex items-center gap-1 text-xs text-red-300">
                            <AlertCircle className="h-3 w-3" />
                            Overdue
                          </span>
                        )}
                      </div>
                      {task.project && (
                        <p className="text-xs text-gray-300">Project: {task.project.title}</p>
                      )}
                      {task.due_date && (
                        <p className="text-xs text-gray-400 flex items-center gap-1 mt-1">
                          <Clock className="h-3 w-3" />
                          Due: {new Date(task.due_date).toLocaleDateString()}
                        </p>
                      )}
                    </div>
                    {task.status_name && (
                      <span
                        className="rounded-full px-2 py-1 text-xs font-medium"
                        style={{
                          backgroundColor: `${task.status_color || "#6b7280"}20`,
                          color: task.status_color || "#6b7280",
                        }}
                      >
                        {task.status_name}
                      </span>
                    )}
                  </div>
                </Link>
              ))}
            </div>
          )}
        </section>
      )}

      <section className="glass rounded-2xl p-5 shadow-xl">
        <div className="flex items-center gap-2">
          <Wand2 className="h-5 w-5 text-emerald-300" />
          <h2 className="text-lg font-semibold text-white">Quick Actions</h2>
        </div>
        <div className="mt-4 grid gap-3 sm:grid-cols-3">
          {[
            { label: "My Tasks", href: "/modules/tasks" },
            ...(enabledModules.some((m) => m.module_code === "crm") ? [{ label: "View CRM", href: "/modules/crm" }] : []),
            ...(enabledModules.some((m) => m.module_code === "ai") ? [{ label: "Chat with AI", href: "/modules/ai" }] : []),
          ].map((action) => (
            <Link
              key={action.label}
              href={action.href}
              className="rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:border-cyan-400 hover:bg-white/10"
            >
              {action.label}
            </Link>
          ))}
        </div>
      </section>
    </div>
  );
}

