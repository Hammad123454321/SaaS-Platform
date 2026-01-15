"use client";

import { TrendingUp, CheckCircle2, Clock, AlertCircle, Users } from "lucide-react";
import { Card } from "@/components/ui/card";
import { useTaskMetrics, useEmployeeProgress } from "@/hooks/tasks/useTaskMetrics";

export function TaskMetricsView() {
  const { data: metrics, isLoading } = useTaskMetrics();
  const { data: employeeProgress } = useEmployeeProgress();

  if (isLoading) {
    return <div className="text-center py-12 text-gray-400">Loading metrics...</div>;
  }

  if (!metrics) {
    return <div className="text-center py-12 text-gray-400">No metrics available</div>;
  }

  return (
    <div className="space-y-6">
      <h2 className="text-xl font-semibold text-white">Task Dashboard Metrics</h2>

      {/* Key Metrics */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card className="glass p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-400">Total Tasks</p>
              <p className="text-2xl font-bold text-white mt-1">{metrics.total_tasks}</p>
            </div>
            <TrendingUp className="h-8 w-8 text-cyan-400" />
          </div>
        </Card>

        <Card className="glass p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-400">Completed</p>
              <p className="text-2xl font-bold text-white mt-1">{metrics.completed_tasks}</p>
            </div>
            <CheckCircle2 className="h-8 w-8 text-green-400" />
          </div>
        </Card>

        <Card className="glass p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-400">In Progress</p>
              <p className="text-2xl font-bold text-white mt-1">{metrics.in_progress_tasks}</p>
            </div>
            <Clock className="h-8 w-8 text-yellow-400" />
          </div>
        </Card>

        <Card className="glass p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-400">Overdue</p>
              <p className="text-2xl font-bold text-white mt-1">{metrics.overdue_tasks}</p>
            </div>
            <AlertCircle className="h-8 w-8 text-red-400" />
          </div>
        </Card>
      </div>

      {/* Completion Rate */}
      <Card className="glass p-6">
        <h3 className="text-lg font-semibold text-white mb-4">Completion Rate</h3>
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-gray-400">Overall</span>
            <span className="text-2xl font-bold text-white">{metrics.completion_rate.toFixed(1)}%</span>
          </div>
          <div className="w-full bg-white/5 rounded-full h-2">
            <div
              className="bg-cyan-400 h-2 rounded-full transition-all"
              style={{ width: `${metrics.completion_rate}%` }}
            />
          </div>
        </div>
      </Card>

      {/* Tasks by Status */}
      {metrics.tasks_by_status && Object.keys(metrics.tasks_by_status).length > 0 && (
        <Card className="glass p-6">
          <h3 className="text-lg font-semibold text-white mb-4">Tasks by Status</h3>
          <div className="space-y-2">
            {Object.entries(metrics.tasks_by_status).map(([status, count]) => (
              <div key={status} className="flex items-center justify-between">
                <span className="text-gray-400 capitalize">{status.replace("_", " ")}</span>
                <span className="text-white font-semibold">{count as number}</span>
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* Employee Progress */}
      {employeeProgress && employeeProgress.length > 0 && (
        <Card className="glass p-6">
          <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <Users className="h-5 w-5" />
            Employee Progress
          </h3>
          <div className="space-y-3">
            {employeeProgress.map((employee) => (
              <div key={employee.user_id} className="space-y-1">
                <div className="flex items-center justify-between">
                  <span className="text-white">{employee.user_name}</span>
                  <span className="text-sm text-gray-400">
                    {employee.completed_tasks} / {employee.total_tasks}
                  </span>
                </div>
                <div className="w-full bg-white/5 rounded-full h-2">
                  <div
                    className="bg-cyan-400 h-2 rounded-full transition-all"
                    style={{ width: `${employee.completion_rate}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
}

