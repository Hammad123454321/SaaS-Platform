"use client";

import { Server, Globe, Database, Activity, Wifi, Shield } from "lucide-react";

interface ServiceStatus {
  name: string;
  status: "online" | "degraded" | "offline";
  latency?: number;
  icon: "server" | "globe" | "database" | "activity" | "wifi" | "shield";
}

interface SystemHealthWidgetProps {
  services: ServiceStatus[];
  className?: string;
}

const icons = {
  server: Server,
  globe: Globe,
  database: Database,
  activity: Activity,
  wifi: Wifi,
  shield: Shield,
};

const statusConfig = {
  online: { color: "text-emerald-600", bg: "bg-emerald-100", label: "Online" },
  degraded: { color: "text-amber-600", bg: "bg-amber-100", label: "Degraded" },
  offline: { color: "text-rose-600", bg: "bg-rose-100", label: "Offline" },
};

export function SystemHealthWidget({ services, className }: SystemHealthWidgetProps) {
  const allHealthy = services.every(s => s.status === "online");

  return (
    <div className={`glass rounded-2xl p-5 ${className}`}>
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Activity className={`h-5 w-5 ${allHealthy ? 'text-emerald-600' : 'text-amber-600'}`} />
          <h3 className="text-lg font-semibold text-gray-900">System Health</h3>
        </div>
        <span className={`text-xs font-medium px-2 py-1 rounded-full ${
          allHealthy ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'
        }`}>
          {allHealthy ? 'All Systems Operational' : 'Issues Detected'}
        </span>
      </div>

      <div className="grid gap-3">
        {services.map((service, index) => {
          const Icon = icons[service.icon];
          const status = statusConfig[service.status];
          
          return (
            <div key={index} className="flex items-center gap-3 p-3 rounded-xl bg-gray-50">
              <div className={`w-10 h-10 rounded-lg ${status.bg} flex items-center justify-center`}>
                <Icon className={`h-5 w-5 ${status.color}`} />
              </div>
              <div className="flex-1">
                <p className="text-gray-900 font-medium">{service.name}</p>
                {service.latency !== undefined && (
                  <p className="text-gray-500 text-xs">{service.latency}ms latency</p>
                )}
              </div>
              <div className="flex items-center gap-2">
                <span className={`w-2 h-2 rounded-full ${
                  service.status === 'online' ? 'bg-emerald-500' :
                  service.status === 'degraded' ? 'bg-amber-500' : 'bg-rose-500'
                } ${service.status === 'online' ? 'animate-pulse' : ''}`} />
                <span className={`text-sm font-medium ${status.color}`}>
                  {status.label}
                </span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

