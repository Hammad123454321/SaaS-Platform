"use client";

import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell } from "recharts";

interface ModuleUsageData {
  module: string;
  usage: number;
  color: string;
}

interface ModuleUsageChartProps {
  data: ModuleUsageData[];
  className?: string;
}

const CustomTooltip = ({ active, payload }: any) => {
  if (active && payload && payload.length) {
    return (
      <div className="bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-lg">
        <p className="text-gray-900 font-medium">{payload[0].payload.module}</p>
        <p className="text-purple-600 text-sm">{payload[0].value} actions</p>
      </div>
    );
  }
  return null;
};

export function ModuleUsageChart({ data, className }: ModuleUsageChartProps) {
  return (
    <div className={className}>
      <ResponsiveContainer width="100%" height={200}>
        <BarChart data={data} layout="vertical" margin={{ left: 0, right: 20 }}>
          <XAxis type="number" hide />
          <YAxis 
            type="category" 
            dataKey="module" 
            axisLine={false} 
            tickLine={false}
            tick={{ fill: '#6b7280', fontSize: 12 }}
            width={80}
          />
          <Tooltip content={<CustomTooltip />} cursor={{ fill: 'rgba(139, 92, 246, 0.05)' }} />
          <Bar dataKey="usage" radius={[0, 6, 6, 0]} barSize={24}>
            {data.map((entry, index) => (
              <Cell key={`cell-${index}`} fill={entry.color} />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

