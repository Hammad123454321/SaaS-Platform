"use client";

import { 
  LayoutDashboard, Users, ShoppingCart, CheckSquare, 
  Calendar, Globe, Bot, ArrowRight, Sparkles
} from "lucide-react";
import Link from "next/link";

interface Module {
  code: string;
  name: string;
  enabled: boolean;
  aiAccess?: boolean;
  usageCount?: number;
}

interface ModuleAccessWidgetProps {
  modules: Module[];
  className?: string;
}

const moduleIcons: Record<string, any> = {
  crm: Users,
  hrm: Users,
  pos: ShoppingCart,
  tasks: CheckSquare,
  booking: Calendar,
  landing: Globe,
  ai: Bot,
};

const moduleColors: Record<string, string> = {
  crm: "from-pink-500 to-rose-500",
  hrm: "from-blue-500 to-indigo-500",
  pos: "from-emerald-500 to-green-500",
  tasks: "from-purple-500 to-violet-500",
  booking: "from-orange-500 to-amber-500",
  landing: "from-cyan-500 to-teal-500",
  ai: "from-fuchsia-500 to-purple-500",
};

export function ModuleAccessWidget({ modules, className }: ModuleAccessWidgetProps) {
  const enabledModules = modules.filter(m => m.enabled);

  return (
    <div className={`glass rounded-2xl p-5 ${className}`}>
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-900">Modules</h3>
        <span className="text-sm text-gray-500">{enabledModules.length} active</span>
      </div>

      {enabledModules.length === 0 ? (
        <div className="text-center py-8">
          <LayoutDashboard className="h-10 w-10 mx-auto mb-2 text-gray-300" />
          <p className="text-gray-500">No modules enabled</p>
        </div>
      ) : (
        <div className="grid gap-2">
          {enabledModules.map((module) => {
            const Icon = moduleIcons[module.code] || LayoutDashboard;
            const gradient = moduleColors[module.code] || "from-gray-500 to-gray-600";
            
            return (
              <Link
                key={module.code}
                href={`/modules/${module.code}`}
                className="flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition group"
              >
                <div className={`w-10 h-10 rounded-lg bg-gradient-to-r ${gradient} flex items-center justify-center`}>
                  <Icon className="h-5 w-5 text-white" />
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <p className="text-gray-900 font-medium">{module.name}</p>
                    {module.aiAccess && (
                      <Sparkles className="h-3 w-3 text-purple-500" />
                    )}
                  </div>
                  {module.usageCount !== undefined && (
                    <p className="text-gray-500 text-xs">{module.usageCount} actions today</p>
                  )}
                </div>
                <ArrowRight className="h-4 w-4 text-gray-300 group-hover:text-gray-500 transition" />
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}

