import { useState, useMemo } from "react";
import { TaskFilters } from "@/types/task";

export function useTaskFilters() {
  const [filters, setFilters] = useState<TaskFilters>({});

  const updateFilter = <K extends keyof TaskFilters>(
    key: K,
    value: TaskFilters[K] | undefined
  ) => {
    setFilters((prev) => ({
      ...prev,
      [key]: value === undefined || value === null ? undefined : value,
    }));
  };

  const clearFilters = () => {
    setFilters({});
  };

  const hasActiveFilters = useMemo(() => {
    return Object.values(filters).some((value) => value !== undefined);
  }, [filters]);

  return {
    filters,
    updateFilter,
    clearFilters,
    hasActiveFilters,
  };
}





