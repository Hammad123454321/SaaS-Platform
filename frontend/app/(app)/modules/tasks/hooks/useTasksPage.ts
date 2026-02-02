import { useState, useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import { tasksApi } from "@/lib/api/tasks";
import { projectsApi } from "@/lib/api/projects";
import { clientsApi } from "@/lib/api/clients";
import { statusesApi } from "@/lib/api/statuses";
import { prioritiesApi } from "@/lib/api/priorities";
import { usersApi } from "@/lib/api/users";
import { Task } from "@/types/task";
import { Project } from "@/types/project";
import { Client } from "@/types/client";
import { Status } from "@/types/status";
import { Priority } from "@/types/priority";
import { DropdownItem } from "@/types/api";
import { useSessionStore } from "@/lib/store";

type Tab = "tasks" | "projects" | "clients" | "statuses" | "priorities" | "team" | "milestones" | "task-lists" | "time-tracker" | "tags";

export function useTasksPage(activeTab: Tab) {
  const { user } = useSessionStore();
  const isStaff = !user?.is_super_admin && !user?.roles?.some(r => r === "company_admin" || r === "admin");

  // Tasks query
  const tasksQuery = useQuery({
    queryKey: ["tasks", activeTab],
    queryFn: () => {
      if (activeTab === "tasks") {
        return isStaff ? tasksApi.getMyTasks() : tasksApi.list();
      }
      return [];
    },
    enabled: activeTab === "tasks",
  });

  // Projects query - always fetch for dropdowns (task creation, etc.)
  const projectsQuery = useQuery({
    queryKey: ["projects"],
    queryFn: () => projectsApi.list(),
    enabled: !isStaff,
  });
  
  // Projects dropdown query - for task form dropdowns
  const projectsDropdownQuery = useQuery({
    queryKey: ["projects-dropdown"],
    queryFn: () => projectsApi.getDropdown(),
    enabled: !isStaff,
  });

  // Clients query
  const clientsQuery = useQuery({
    queryKey: ["clients"],
    queryFn: () => clientsApi.list(),
    enabled: activeTab === "clients" && !isStaff,
  });

  // Statuses query
  const statusesQuery = useQuery({
    queryKey: ["statuses"],
    queryFn: () => statusesApi.list(),
    enabled: activeTab === "statuses" && !isStaff,
  });
  
  // Statuses dropdown query - for task form dropdowns
  const statusesDropdownQuery = useQuery({
    queryKey: ["statuses-dropdown"],
    queryFn: () => statusesApi.getDropdown(),
    enabled: !isStaff,
  });

  // Priorities query
  const prioritiesQuery = useQuery({
    queryKey: ["priorities"],
    queryFn: () => prioritiesApi.list(),
    enabled: activeTab === "priorities" && !isStaff,
  });
  
  // Priorities dropdown query - for task form dropdowns
  const prioritiesDropdownQuery = useQuery({
    queryKey: ["priorities-dropdown"],
    queryFn: () => prioritiesApi.getDropdown(),
    enabled: !isStaff,
  });

  // Users query
  const usersQuery = useQuery({
    queryKey: ["users"],
    queryFn: () => usersApi.getDropdown(),
  });

  return {
    tasks: (tasksQuery.data || []) as Task[],
    projects: (projectsQuery.data || []) as Project[],
    projectsDropdown: (projectsDropdownQuery.data || []) as DropdownItem[],
    clients: (clientsQuery.data || []) as Client[],
    statuses: (statusesQuery.data || []) as Status[],
    statusesDropdown: (statusesDropdownQuery.data || []) as DropdownItem[],
    priorities: (prioritiesQuery.data || []) as Priority[],
    prioritiesDropdown: (prioritiesDropdownQuery.data || []) as DropdownItem[],
    users: (usersQuery.data || []) as DropdownItem[],
    isLoading: tasksQuery.isLoading || projectsQuery.isLoading || projectsDropdownQuery.isLoading || clientsQuery.isLoading || statusesQuery.isLoading || statusesDropdownQuery.isLoading || prioritiesQuery.isLoading || prioritiesDropdownQuery.isLoading,
    error: tasksQuery.error || projectsQuery.error || projectsDropdownQuery.error || clientsQuery.error || statusesQuery.error || statusesDropdownQuery.error || prioritiesQuery.error || prioritiesDropdownQuery.error ? "Failed to load data" : null,
  };
}





