"use client";

import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from "recharts";

interface GrowthData {
  month: string;
  tenants: number;
  users: number;
}

interface TenantGrowthChartProps {
  data: GrowthData[];
  className?: string;
}

const CustomTooltip = ({ active, payload, label }: any) => {
  if (active && payload && payload.length) {
    return (
      <div className="bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-lg">
        <p className="text-gray-500 text-xs mb-1">{label}</p>
        <p className="text-purple-600 text-sm">Tenants: {payload[0]?.value || 0}</p>
        <p className="text-cyan-600 text-sm">Users: {payload[1]?.value || 0}</p>
      </div>
    );
  }
  return null;
};

export function TenantGrowthChart({ data, className }: TenantGrowthChartProps) {
  return (
    <div className={className}>
      <ResponsiveContainer width="100%" height={200}>
        <LineChart data={data} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="rgba(0,0,0,0.05)" />
          <XAxis 
            dataKey="month" 
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
          <Line 
            type="monotone" 
            dataKey="tenants" 
            stroke="#a855f7" 
            strokeWidth={2}
            dot={{ fill: '#a855f7', strokeWidth: 0, r: 4 }}
            activeDot={{ r: 6, fill: '#a855f7' }}
          />
          <Line 
            type="monotone" 
            dataKey="users" 
            stroke="#06b6d4" 
            strokeWidth={2}
            dot={{ fill: '#06b6d4', strokeWidth: 0, r: 4 }}
            activeDot={{ r: 6, fill: '#06b6d4' }}
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

