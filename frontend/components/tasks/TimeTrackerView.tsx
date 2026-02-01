"use client";

import { useState, useEffect } from "react";
import { Play, Square, Clock, Calendar } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { useActiveTracker, useStartTracker, useStopTracker, useTimeEntries } from "@/hooks/tasks/useTimeTracking";
import { useTasks } from "@/hooks/tasks/useTasks";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { formatDistanceToNow, parseISO } from "date-fns";

export function TimeTrackerView() {
  const { data: activeTracker } = useActiveTracker();
  const { data: tasks } = useTasks();
  const startTracker = useStartTracker();
  const stopTracker = useStopTracker();
  const { data: timeEntries } = useTimeEntries();

  const [selectedTaskId, setSelectedTaskId] = useState<string | undefined>();
  const [elapsedTime, setElapsedTime] = useState<string>("00:00:00");

  // Live timer for active tracker
  useEffect(() => {
    if (!activeTracker?.start_time) {
      setElapsedTime("00:00:00");
      return;
    }

    const updateTimer = () => {
      try {
        const startDate = parseISO(activeTracker.start_time);
        const now = new Date();
        const diff = now.getTime() - startDate.getTime();
        
        if (diff < 0) {
          setElapsedTime("00:00:00");
          return;
        }

        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        setElapsedTime(
          `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`
        );
      } catch (error) {
        console.error("Error calculating elapsed time:", error);
        setElapsedTime("00:00:00");
      }
    };

    updateTimer(); // Update immediately
    const interval = setInterval(updateTimer, 1000); // Update every second

    return () => clearInterval(interval);
  }, [activeTracker?.start_time]);

  const handleStart = async () => {
    if (selectedTaskId) {
      await startTracker.mutateAsync({ taskId: selectedTaskId });
    }
  };

  const handleStop = async () => {
    if (activeTracker) {
      await stopTracker.mutateAsync(activeTracker.id);
    }
  };

  const formatDuration = (minutes: number) => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-gray-900">Time Tracker</h2>
      </div>

      {/* Active Tracker */}
      {activeTracker && (
        <Card className="bg-white p-6 border-purple-200 shadow-sm">
          <div className="flex items-center justify-between">
            <div className="flex-1">
              <p className="text-sm text-gray-500">Currently tracking</p>
              <p className="text-lg font-semibold text-gray-900 mt-1">
                {tasks?.find(t => t.id === activeTracker.task_id)?.title || "Task"}
              </p>
              <div className="flex items-center gap-4 mt-2">
                <div className="flex items-center gap-2">
                  <Clock className="h-4 w-4 text-purple-600" />
                  <span className="text-2xl font-mono font-bold text-purple-600">{elapsedTime}</span>
                </div>
                {activeTracker.start_time && (
                  <p className="text-xs text-gray-500">
                    Started {formatDistanceToNow(parseISO(activeTracker.start_time), { addSuffix: true })}
                  </p>
                )}
              </div>
            </div>
            <Button
              onClick={handleStop}
              className="bg-red-500 text-white hover:bg-red-600 ml-4"
              disabled={stopTracker.isPending}
            >
              <Square className="h-4 w-4 mr-2" />
              Stop
            </Button>
          </div>
        </Card>
      )}

      {/* Start Tracker */}
      {!activeTracker && (
        <Card className="bg-white p-6 border border-gray-200 shadow-sm">
          <div className="space-y-4">
            <div>
              <label className="text-sm text-gray-600 font-medium mb-2 block">Select Task</label>
              <Select
                value={selectedTaskId || undefined}
                onValueChange={(value) => setSelectedTaskId(value || undefined)}
              >
                <SelectTrigger className="bg-white border-gray-300 text-gray-900">
                  <SelectValue placeholder="Choose a task to track" />
                </SelectTrigger>
                <SelectContent>
                  {tasks?.map((task) => (
                    <SelectItem key={task.id} value={task.id}>
                      {task.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <Button
              onClick={handleStart}
              disabled={!selectedTaskId || startTracker.isPending}
              className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700 w-full"
            >
              <Play className="h-4 w-4 mr-2" />
              Start Tracking
            </Button>
          </div>
        </Card>
      )}

      {/* Recent Time Entries */}
      <div>
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Recent Time Entries</h3>
        {timeEntries && timeEntries.length > 0 ? (
          <div className="space-y-2">
            {timeEntries.slice(0, 10).map((entry) => (
              <Card key={entry.id} className="bg-white p-4 border border-gray-200 shadow-sm">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-gray-900 font-medium">
                      {tasks?.find(t => t.id === entry.task_id)?.title || "Task"}
                    </p>
                    <div className="flex items-center gap-4 mt-1 text-xs text-gray-500">
                      <span className="flex items-center gap-1">
                        <Calendar className="h-3 w-3" />
                        {entry.start_time ? new Date(entry.start_time).toLocaleDateString() : entry.date || "N/A"}
                      </span>
                      {entry.duration_minutes && (
                        <span className="flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          {formatDuration(entry.duration_minutes)}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        ) : (
          <Card className="bg-white p-12 text-center border border-gray-200">
            <div className="h-16 w-16 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-4">
              <Clock className="h-8 w-8 text-purple-600" />
            </div>
            <p className="text-gray-500">No time entries yet. Start tracking time on a task.</p>
          </Card>
        )}
      </div>
    </div>
  );
}

