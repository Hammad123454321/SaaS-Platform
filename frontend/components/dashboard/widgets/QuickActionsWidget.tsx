"use client";

import { ArrowRight, LucideIcon } from "lucide-react";
import Link from "next/link";

interface QuickAction {
  label: string;
  href: string;
  icon: LucideIcon;
  gradient: string;
}

interface QuickActionsWidgetProps {
  actions: QuickAction[];
  title?: string;
  className?: string;
}

export function QuickActionsWidget({ 
  actions, 
  title = "Quick Actions",
  className 
}: QuickActionsWidgetProps) {
  return (
    <div className={`glass rounded-2xl p-5 ${className}`}>
      <h3 className="text-lg font-semibold text-gray-900 mb-4">{title}</h3>
      <div className="space-y-2">
        {actions.map((action, index) => {
          const Icon = action.icon;
          return (
            <Link
              key={index}
              href={action.href}
              className={`w-full p-4 rounded-xl flex items-center justify-between font-medium transition hover:opacity-90 text-white ${action.gradient}`}
            >
              <div className="flex items-center gap-3">
                <Icon className="h-5 w-5" />
                <span>{action.label}</span>
              </div>
              <ArrowRight className="h-4 w-4" />
            </Link>
          );
        })}
      </div>
    </div>
  );
}

