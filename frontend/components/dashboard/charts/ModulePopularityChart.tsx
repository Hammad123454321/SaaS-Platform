"use client";

import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from "recharts";

interface ModuleData {
  name: string;
  value: number;
  color: string;
}

interface ModulePopularityChartProps {
  data: ModuleData[];
  className?: string;
}

const CustomTooltip = ({ active, payload }: any) => {
  if (active && payload && payload.length) {
    return (
      <div className="bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-lg">
        <p className="text-gray-900 font-medium">{payload[0].name}</p>
        <p className="text-purple-600 text-sm">{payload[0].value} subscriptions</p>
      </div>
    );
  }
  return null;
};

export function ModulePopularityChart({ data, className }: ModulePopularityChartProps) {
  return (
    <div className={`flex items-center gap-4 ${className}`}>
      <ResponsiveContainer width={140} height={140}>
        <PieChart>
          <Pie
            data={data}
            cx="50%"
            cy="50%"
            innerRadius={40}
            outerRadius={65}
            paddingAngle={2}
            dataKey="value"
          >
            {data.map((entry, index) => (
              <Cell key={`cell-${index}`} fill={entry.color} />
            ))}
          </Pie>
          <Tooltip content={<CustomTooltip />} />
        </PieChart>
      </ResponsiveContainer>
      <div className="flex-1 space-y-2">
        {data.map((item, index) => (
          <div key={index} className="flex items-center gap-2">
            <div 
              className="w-3 h-3 rounded-full" 
              style={{ backgroundColor: item.color }}
            />
            <span className="text-gray-600 text-sm flex-1">{item.name}</span>
            <span className="text-gray-900 font-medium text-sm">{item.value}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

