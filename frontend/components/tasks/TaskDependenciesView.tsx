"use client";

import { useState } from "react";
import { Plus, X, Link2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useTaskDependencies, useCreateTaskDependency, useDeleteTaskDependency } from "@/hooks/tasks/useTaskDependencies";
import { useTasks } from "@/hooks/tasks/useTasks";
import { TaskDependencyFormData } from "@/lib/api/taskDependencies";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";

interface TaskDependenciesViewProps {
  taskId: number;
}

export function TaskDependenciesView({ taskId }: TaskDependenciesViewProps) {
  const { data: dependencies } = useTaskDependencies(taskId);
  const { data: allTasks } = useTasks();
  const createDependency = useCreateTaskDependency();
  const deleteDependency = useDeleteTaskDependency();
  const { canUpdateTask } = useTaskAccess();

  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState<Partial<TaskDependencyFormData>>({
    depends_on_task_id: undefined,
    dependency_type: "blocks",
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.depends_on_task_id) {
      alert("Please select a task");
      return;
    }
    await createDependency.mutateAsync({
      taskId,
      data: formData as TaskDependencyFormData,
    });
    setShowForm(false);
    resetForm();
  };

  const resetForm = () => {
    setFormData({
      depends_on_task_id: undefined,
      dependency_type: "blocks",
    });
  };

  const handleDelete = async (dependencyId: number) => {
    await deleteDependency.mutateAsync(dependencyId);
  };

  const currentTask = allTasks?.find(t => t.id === taskId);
  const availableTasks = allTasks?.filter(t => t.id !== taskId) || [];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold text-white">Dependencies</h3>
        {canUpdateTask && (
          <Button onClick={() => setShowForm(true)} size="sm" className="glass">
            <Plus className="h-4 w-4 mr-2" />
            Add Dependency
          </Button>
        )}
      </div>

      {dependencies && dependencies.length > 0 ? (
        <div className="space-y-2">
          {dependencies.map((dep) => {
            const depTask = allTasks?.find(t => t.id === dep.depends_on_task_id);
            return (
              <div key={dep.id} className="glass rounded-lg p-3 flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Link2 className="h-4 w-4 text-gray-400" />
                  <span className="text-white">{depTask?.title || `Task #${dep.depends_on_task_id}`}</span>
                  <span className="text-xs text-gray-400 capitalize">({dep.dependency_type})</span>
                </div>
                {canUpdateTask && (
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => handleDelete(dep.id)}
                    className="h-6 w-6 text-red-400"
                  >
                    <X className="h-4 w-4" />
                  </Button>
                )}
              </div>
            );
          })}
        </div>
      ) : (
        <div className="glass rounded-lg p-6 text-center">
          <p className="text-gray-400">No dependencies yet.</p>
        </div>
      )}

      {/* Create Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="glass max-w-md">
          <DialogHeader>
            <DialogTitle className="text-white">Add Dependency</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-200/80">Depends On *</label>
              <Select
                value={formData.depends_on_task_id?.toString() || ""}
                onValueChange={(value) => setFormData({ ...formData, depends_on_task_id: parseInt(value) })}
                required
              >
                <SelectTrigger className="mt-1 bg-white/5 border-white/10 text-white">
                  <SelectValue placeholder="Select task" />
                </SelectTrigger>
                <SelectContent>
                  {availableTasks.map((task) => (
                    <SelectItem key={task.id} value={task.id.toString()}>
                      {task.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="text-sm text-gray-200/80">Dependency Type *</label>
              <Select
                value={formData.dependency_type || "blocks"}
                onValueChange={(value) => setFormData({ ...formData, dependency_type: value as any })}
                required
              >
                <SelectTrigger className="mt-1 bg-white/5 border-white/10 text-white">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="blocks">Blocks</SelectItem>
                  <SelectItem value="blocked_by">Blocked By</SelectItem>
                  <SelectItem value="related">Related</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="glass" disabled={createDependency.isPending}>
                Add
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}

