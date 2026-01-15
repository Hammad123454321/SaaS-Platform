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

  const [selectedTaskId, setSelectedTaskId] = useState<number | undefined>();
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
        <h2 className="text-xl font-semibold text-white">Time Tracker</h2>
      </div>

      {/* Active Tracker */}
      {activeTracker && (
        <Card className="glass p-6 border-cyan-400/20">
          <div className="flex items-center justify-between">
            <div className="flex-1">
              <p className="text-sm text-gray-400">Currently tracking</p>
              <p className="text-lg font-semibold text-white mt-1">
                {tasks?.find(t => t.id === activeTracker.task_id)?.title || "Task"}
              </p>
              <div className="flex items-center gap-4 mt-2">
                <div className="flex items-center gap-2">
                  <Clock className="h-4 w-4 text-cyan-400" />
                  <span className="text-2xl font-mono font-bold text-cyan-400">{elapsedTime}</span>
                </div>
                {activeTracker.start_time && (
                  <p className="text-xs text-gray-400">
                    Started {formatDistanceToNow(parseISO(activeTracker.start_time), { addSuffix: true })}
                  </p>
                )}
              </div>
            </div>
            <Button
              onClick={handleStop}
              className="bg-red-500/20 text-red-400 border-red-500/50 hover:bg-red-500/30 ml-4"
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
        <Card className="glass p-6">
          <div className="space-y-4">
            <div>
              <label className="text-sm text-gray-200/80 mb-2 block">Select Task</label>
              <Select
                value={selectedTaskId?.toString() || ""}
                onValueChange={(value) => setSelectedTaskId(value ? parseInt(value) : undefined)}
              >
                <SelectTrigger className="bg-white/5 border-white/10 text-white">
                  <SelectValue placeholder="Choose a task to track" />
                </SelectTrigger>
                <SelectContent>
                  {tasks?.map((task) => (
                    <SelectItem key={task.id} value={task.id.toString()}>
                      {task.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <Button
              onClick={handleStart}
              disabled={!selectedTaskId || startTracker.isPending}
              className="glass w-full"
            >
              <Play className="h-4 w-4 mr-2" />
              Start Tracking
            </Button>
          </div>
        </Card>
      )}

      {/* Recent Time Entries */}
      <div>
        <h3 className="text-lg font-semibold text-white mb-4">Recent Time Entries</h3>
        {timeEntries && timeEntries.length > 0 ? (
          <div className="space-y-2">
            {timeEntries.slice(0, 10).map((entry) => (
              <Card key={entry.id} className="glass p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-white font-medium">
                      {tasks?.find(t => t.id === entry.task_id)?.title || "Task"}
                    </p>
                    <div className="flex items-center gap-4 mt-1 text-xs text-gray-400">
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
          <Card className="glass p-12 text-center">
            <Clock className="h-12 w-12 mx-auto mb-4 text-gray-400" />
            <p className="text-gray-400">No time entries yet. Start tracking time on a task.</p>
          </Card>
        )}
      </div>
    </div>
  );
}

