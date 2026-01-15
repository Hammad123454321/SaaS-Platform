"use client";

import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  CheckSquare,
  FolderKanban,
  Users,
  Calendar,
  ListTodo,
  FileText,
  Settings,
  Target,
  Layers,
  Tag,
  Clock,
} from "lucide-react";
import { useSessionStore } from "@/lib/store";

type Tab = "tasks" | "projects" | "clients" | "statuses" | "priorities" | "team" | "milestones" | "task-lists" | "time-tracker" | "tags";

interface TasksTabsProps {
  activeTab: Tab;
  onTabChange: (tab: Tab) => void;
  user: ReturnType<typeof useSessionStore>["user"];
}

export function TasksTabs({ activeTab, onTabChange, user }: TasksTabsProps) {
  const isStaff = !user?.is_super_admin && !user?.roles?.some(r => r === "company_admin" || r === "admin");

  return (
    <Tabs value={activeTab} onValueChange={(v) => onTabChange(v as Tab)}>
      <TabsList className="glass mb-4">
        <TabsTrigger value="tasks">
          <CheckSquare className="h-4 w-4 mr-2" />
          Tasks
        </TabsTrigger>
        {!isStaff && (
          <>
            <TabsTrigger value="projects">
              <FolderKanban className="h-4 w-4 mr-2" />
              Projects
            </TabsTrigger>
            <TabsTrigger value="clients">
              <Users className="h-4 w-4 mr-2" />
              Clients
            </TabsTrigger>
            <TabsTrigger value="statuses">
              <ListTodo className="h-4 w-4 mr-2" />
              Statuses
            </TabsTrigger>
            <TabsTrigger value="priorities">
              <FileText className="h-4 w-4 mr-2" />
              Priorities
            </TabsTrigger>
          </>
        )}
        <TabsTrigger value="milestones">
          <Target className="h-4 w-4 mr-2" />
          Milestones
        </TabsTrigger>
        <TabsTrigger value="task-lists">
          <Layers className="h-4 w-4 mr-2" />
          Task Lists
        </TabsTrigger>
        <TabsTrigger value="tags">
          <Tag className="h-4 w-4 mr-2" />
          Tags
        </TabsTrigger>
        {!isStaff && (
          <TabsTrigger value="time-tracker">
            <Clock className="h-4 w-4 mr-2" />
            Time Tracker
          </TabsTrigger>
        )}
      </TabsList>
    </Tabs>
  );
}

