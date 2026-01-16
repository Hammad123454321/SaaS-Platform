"use client";

import { useState } from "react";
import { 
  Sparkles, 
  AlertTriangle, 
  Clock, 
  ListTodo, 
  ChevronRight,
  Loader2,
  RefreshCw,
  Lightbulb
} from "lucide-react";
import { useAIInsights } from "@/hooks/useDashboard";

interface AIInsightsWidgetProps {
  className?: string;
}

export function AIInsightsWidget({ className = "" }: AIInsightsWidgetProps) {
  const { data: insights, isLoading, error, refetch, isRefetching } = useAIInsights();

  if (isLoading) {
    return (
      <div className={`glass rounded-2xl p-5 ${className}`}>
        <div className="flex items-center gap-3 mb-4">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-r from-violet-500 to-purple-500 flex items-center justify-center">
            <Sparkles className="h-5 w-5 text-white" />
          </div>
          <div>
            <h3 className="text-gray-900 font-semibold">AI Insights</h3>
            <p className="text-gray-500 text-xs">What needs attention today</p>
          </div>
        </div>
        <div className="flex items-center justify-center py-8">
          <Loader2 className="h-6 w-6 animate-spin text-purple-500" />
          <span className="ml-2 text-gray-500 text-sm">Analyzing your data...</span>
        </div>
      </div>
    );
  }

  if (error || !insights) {
    return (
      <div className={`glass rounded-2xl p-5 ${className}`}>
        <div className="flex items-center gap-3 mb-4">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-r from-violet-500 to-purple-500 flex items-center justify-center">
            <Sparkles className="h-5 w-5 text-white" />
          </div>
          <div>
            <h3 className="text-gray-900 font-semibold">AI Insights</h3>
            <p className="text-gray-500 text-xs">What needs attention today</p>
          </div>
        </div>
        <div className="text-center py-6">
          <p className="text-gray-500 text-sm mb-3">Unable to load insights</p>
          <button 
            onClick={() => refetch()}
            className="text-purple-600 text-sm hover:text-purple-700 flex items-center gap-1 mx-auto"
          >
            <RefreshCw className="h-4 w-4" /> Try again
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className={`glass rounded-2xl p-5 ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-r from-violet-500 to-purple-500 flex items-center justify-center shadow-lg shadow-purple-500/20">
            <Sparkles className="h-5 w-5 text-white" />
          </div>
          <div>
            <h3 className="text-gray-900 font-semibold">AI Insights</h3>
            <p className="text-gray-500 text-xs">What needs attention today</p>
          </div>
        </div>
        <button 
          onClick={() => refetch()}
          disabled={isRefetching}
          className="p-2 rounded-lg hover:bg-gray-100 transition text-gray-400 hover:text-gray-600"
        >
          <RefreshCw className={`h-4 w-4 ${isRefetching ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {/* Summary */}
      <div className="bg-gradient-to-r from-purple-50 to-violet-50 rounded-xl p-4 mb-4 border border-purple-100">
        <p className="text-gray-700 text-sm leading-relaxed">{insights.summary}</p>
      </div>

      {/* Stats Pills */}
      <div className="flex flex-wrap gap-2 mb-4">
        {insights.overdue_tasks > 0 && (
          <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-red-50 border border-red-100">
            <AlertTriangle className="h-3.5 w-3.5 text-red-500" />
            <span className="text-xs font-medium text-red-700">{insights.overdue_tasks} overdue</span>
          </div>
        )}
        {insights.upcoming_deadlines > 0 && (
          <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 border border-amber-100">
            <Clock className="h-3.5 w-3.5 text-amber-500" />
            <span className="text-xs font-medium text-amber-700">{insights.upcoming_deadlines} due soon</span>
          </div>
        )}
        {insights.pending_items > 0 && (
          <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-100">
            <ListTodo className="h-3.5 w-3.5 text-blue-500" />
            <span className="text-xs font-medium text-blue-700">{insights.pending_items} pending setup</span>
          </div>
        )}
        {insights.overdue_tasks === 0 && insights.upcoming_deadlines === 0 && insights.pending_items === 0 && (
          <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-100">
            <span className="text-xs font-medium text-emerald-700">✓ All caught up!</span>
          </div>
        )}
      </div>

      {/* Suggestions */}
      {insights.suggestions && insights.suggestions.length > 0 && (
        <div className="space-y-2">
          <div className="flex items-center gap-1.5 text-gray-500 text-xs font-medium mb-2">
            <Lightbulb className="h-3.5 w-3.5" />
            Suggestions
          </div>
          {insights.suggestions.map((suggestion, index) => (
            <div 
              key={index}
              className="flex items-start gap-2 p-2.5 rounded-lg bg-gray-50 hover:bg-gray-100 transition cursor-pointer group"
            >
              <ChevronRight className="h-4 w-4 text-purple-400 mt-0.5 group-hover:translate-x-0.5 transition-transform" />
              <span className="text-sm text-gray-600 group-hover:text-gray-800">{suggestion}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

