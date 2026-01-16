"use client";

import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from "recharts";

interface TaskTrendData {
  date: string;
  completed: number;
  created: number;
}

interface TaskTrendChartProps {
  data: TaskTrendData[];
  className?: string;
}

const CustomTooltip = ({ active, payload, label }: any) => {
  if (active && payload && payload.length) {
    return (
      <div className="bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-lg">
        <p className="text-gray-500 text-xs mb-1">{label}</p>
        <p className="text-emerald-600 text-sm">Completed: {payload[0]?.value || 0}</p>
        <p className="text-purple-600 text-sm">Created: {payload[1]?.value || 0}</p>
      </div>
    );
  }
  return null;
};

export function TaskTrendChart({ data, className }: TaskTrendChartProps) {
  return (
    <div className={className}>
      <ResponsiveContainer width="100%" height={200}>
        <AreaChart data={data} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
          <defs>
            <linearGradient id="completedGradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#10b981" stopOpacity={0.3} />
              <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
            </linearGradient>
            <linearGradient id="createdGradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#a855f7" stopOpacity={0.3} />
              <stop offset="95%" stopColor="#a855f7" stopOpacity={0} />
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" stroke="rgba(0,0,0,0.05)" />
          <XAxis 
            dataKey="date" 
            axisLine={false} 
            tickLine={false}
            tick={{ fill: '#6b7280', fontSize: 11 }}
          />
          <YAxis 
            axisLine={false} 
            tickLine={false}
            tick={{ fill: '#6b7280', fontSize: 11 }}
          />
          <Tooltip content={<CustomTooltip />} />
          <Area 
            type="monotone" 
            dataKey="completed" 
            stroke="#10b981" 
            strokeWidth={2}
            fill="url(#completedGradient)" 
          />
          <Area 
            type="monotone" 
            dataKey="created" 
            stroke="#a855f7" 
            strokeWidth={2}
            fill="url(#createdGradient)" 
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}

