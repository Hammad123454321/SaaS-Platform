"use client";

import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from "recharts";

interface RevenueData {
  month: string;
  revenue: number;
}

interface RevenueChartProps {
  data: RevenueData[];
  className?: string;
}

const CustomTooltip = ({ active, payload, label }: any) => {
  if (active && payload && payload.length) {
    return (
      <div className="bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-lg">
        <p className="text-gray-500 text-xs mb-1">{label}</p>
        <p className="text-purple-600 font-medium">${payload[0].value.toLocaleString()}</p>
      </div>
    );
  }
  return null;
};

export function RevenueChart({ data, className }: RevenueChartProps) {
  return (
    <div className={className}>
      <ResponsiveContainer width="100%" height={200}>
        <BarChart data={data} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
          <defs>
            <linearGradient id="revenueGradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#06b6d4" />
              <stop offset="100%" stopColor="#3b82f6" />
            </linearGradient>
          </defs>
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
            tickFormatter={(value) => `$${value / 1000}k`}
          />
          <Tooltip content={<CustomTooltip />} cursor={{ fill: 'rgba(139, 92, 246, 0.05)' }} />
          <Bar 
            dataKey="revenue" 
            fill="url(#revenueGradient)" 
            radius={[4, 4, 0, 0]}
            barSize={32}
          />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

