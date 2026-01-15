"use client";

import { Button } from "@/components/ui/button";
import { Plus } from "lucide-react";

interface CreateTaskButtonProps {
  onClick: () => void;
}

export function CreateTaskButton({ onClick }: CreateTaskButtonProps) {
  return (
    <Button onClick={onClick} className="glass">
      <Plus className="h-4 w-4 mr-2" />
      Create Task
    </Button>
  );
}





