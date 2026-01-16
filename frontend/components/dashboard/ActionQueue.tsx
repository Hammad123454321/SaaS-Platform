"use client";

import { CheckCircle2, Circle } from "lucide-react";
import { useState } from "react";

interface ActionItem {
  id: string;
  text: string;
  completed?: boolean;
}

interface ActionQueueProps {
  title?: string;
  items: ActionItem[];
  onToggle?: (id: string) => void;
  className?: string;
}

export function ActionQueue({
  title = "Action Queue (Do This Next)",
  items,
  onToggle,
  className,
}: ActionQueueProps) {
  const [localItems, setLocalItems] = useState(items);

  const handleToggle = (id: string) => {
    setLocalItems((prev) =>
      prev.map((item) =>
        item.id === id ? { ...item, completed: !item.completed } : item
      )
    );
    onToggle?.(id);
  };

  return (
    <div className={className}>
      {title && <h3 className="text-lg font-semibold text-gray-900 mb-4">{title}</h3>}
      <div className="space-y-1">
        {localItems.map((item) => (
          <div
            key={item.id}
            className="flex items-start gap-3 py-2 px-2 -mx-2 hover:bg-gray-50 rounded-lg cursor-pointer transition-colors"
            onClick={() => handleToggle(item.id)}
          >
            <button className="mt-0.5 flex-shrink-0">
              {item.completed ? (
                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
              ) : (
                <Circle className="h-4 w-4 text-gray-300" />
              )}
            </button>
            <p
              className={`flex-1 text-sm ${
                item.completed ? "line-through text-gray-400" : "text-gray-700"
              }`}
            >
              {item.text}
            </p>
          </div>
        ))}
      </div>
    </div>
  );
}

