"use client";

import { ReactNode } from "react";
import { ArrowUp, ArrowDown } from "lucide-react";
import Link from "next/link";

interface StatCardProps {
  title: string;
  value: string | number;
  icon: ReactNode;
  trend?: {
    value: number;
    isPositive: boolean;
  };
  href?: string;
  gradient: string;
  className?: string;
}

export function StatCard({ 
  title, 
  value, 
  icon, 
  trend, 
  href, 
  gradient,
  className 
}: StatCardProps) {
  const content = (
    <div className={`glass rounded-2xl p-5 group hover:shadow-lg transition ${className}`}>
      <div className="flex items-start justify-between mb-3">
        <div className={`w-12 h-12 rounded-xl bg-gradient-to-r ${gradient} flex items-center justify-center`}>
          {icon}
        </div>
        {trend && (
          <div className={`flex items-center gap-1 text-xs font-medium ${
            trend.isPositive ? 'text-emerald-600' : 'text-rose-600'
          }`}>
            {trend.isPositive ? <ArrowUp className="w-3 h-3" /> : <ArrowDown className="w-3 h-3" />}
            {trend.value}%
          </div>
        )}
      </div>
      <p className="text-3xl font-bold text-gray-900 mb-1">{value}</p>
      <p className="text-sm text-gray-500">{title}</p>
    </div>
  );

  if (href) {
    return <Link href={href}>{content}</Link>;
  }

  return content;
}

