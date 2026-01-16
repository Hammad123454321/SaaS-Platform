"use client";

import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { ChartCard } from "@/components/dashboard/ChartCard";
import {
  BarChart,
  Bar,
  LineChart,
  Line,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from "recharts";
import { TrendingUp, MessageSquare, Heart, Share2, Eye } from "lucide-react";

const followersData = [
  { date: "Jan", comments: 20000, followers: 18000 },
  { date: "Feb", comments: 22000, followers: 19000 },
  { date: "Mar", comments: 24000, followers: 20000 },
  { date: "Apr", comments: 25000, followers: 21000 },
  { date: "May", comments: 25634, followers: 22856 },
];

const trafficData = [
  { name: "Homepage", views: 21763 },
  { name: "Product Page", views: 7142 },
  { name: "Blog Articles", views: 5545 },
  { name: "About Page", views: 2096 },
  { name: "Contact Page", views: 1213 },
];

const trafficSources = [
  { name: "Organic", value: 52, color: "#9333ea" },
  { name: "Paid", value: 24, color: "#3b82f6" },
  { name: "Social", value: 16, color: "#ec4899" },
  { name: "Referral", value: 8, color: "#f59e0b" },
];

const platformActions = [
  { action: "Comments", value: 681 },
  { action: "Likes", value: 786 },
  { action: "Shares", value: 117 },
  { action: "Views", value: 480 },
];

const ppcData = [
  { platform: "Google Ads", spend: 310, reach: "7.2K" },
  { platform: "Meta Ads", spend: 325, reach: "5.6K" },
  { platform: "LinkedIn Ads", spend: 210, reach: "275K" },
  { platform: "Facebook Ads", spend: 450, reach: "6.5K" },
  { platform: "YouTube Ads", spend: 450, reach: "1.2K" },
];

export default function MarketingDashboard() {
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-white rounded-2xl p-6 shadow-sm border border-gray-200">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm text-gray-500 mb-1">Welcome</p>
            <h1 className="text-3xl font-bold text-gray-900">Marketing Dashboard</h1>
            <p className="text-sm text-gray-600 mt-1">Track your marketing performance and engagement.</p>
          </div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Left Column */}
        <div className="lg:col-span-2 space-y-6">
          {/* Followers & Engagement */}
          <ChartCard title="Followers & Engagement">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={followersData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="date" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Line
                  type="monotone"
                  dataKey="comments"
                  stroke="#ec4899"
                  strokeWidth={2}
                  name="Comments"
                />
                <Line
                  type="monotone"
                  dataKey="followers"
                  stroke="#9333ea"
                  strokeWidth={2}
                  name="Followers"
                />
              </LineChart>
            </ResponsiveContainer>
          </ChartCard>

          {/* Website Traffic */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h3 className="text-lg font-semibold text-gray-900">Website Traffic (Top Pages)</h3>
                <p className="text-sm text-gray-600 mt-1">
                  38,745 Site visits this week <span className="text-green-600 font-medium">▲ +8.7%</span>
                </p>
              </div>
            </div>
            <div className="space-y-3">
              {trafficData.map((page, index) => (
                <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <div className="flex-1">
                    <p className="font-medium text-gray-900">{page.name}</p>
                  </div>
                  <p className="text-sm font-semibold text-gray-700">{page.views.toLocaleString()}</p>
                </div>
              ))}
            </div>
          </Card>

          {/* PPC Advertising */}
          <Card className="p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">PPC Advertising</h3>
            <div className="space-y-3">
              {ppcData.map((item, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between p-3 border border-gray-200 rounded-lg"
                >
                  <div>
                    <p className="font-medium text-gray-900">{item.platform}</p>
                    <p className="text-sm text-gray-600">Reach: {item.reach}</p>
                  </div>
                  <p className="text-lg font-semibold text-purple-600">${item.spend}</p>
                </div>
              ))}
            </div>
          </Card>
        </div>

        {/* Right Column */}
        <div className="space-y-6">
          {/* Engagement Metrics */}
          <Card className="p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Engagement Metrics</h3>
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <TrendingUp className="h-5 w-5 text-blue-600" />
                  <span className="text-sm text-gray-700">New Followers</span>
                </div>
                <span className="font-semibold text-gray-900">+1.5k</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <MessageSquare className="h-5 w-5 text-pink-600" />
                  <span className="text-sm text-gray-700">Comments</span>
                </div>
                <span className="font-semibold text-gray-900">382</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Heart className="h-5 w-5 text-purple-600" />
                  <span className="text-sm text-gray-700">Likes</span>
                </div>
                <span className="font-semibold text-gray-900">792</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Share2 className="h-5 w-5 text-green-600" />
                  <span className="text-sm text-gray-700">Shares</span>
                </div>
                <span className="font-semibold text-gray-900">68</span>
              </div>
            </div>
          </Card>

          {/* Traffic Sources */}
          <ChartCard title="Traffic Sources" className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={trafficSources}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                  outerRadius={80}
                  fill="#8884d8"
                  dataKey="value"
                >
                  {trafficSources.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </ChartCard>

          {/* Actions by Platform */}
          <Card className="p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Actions by Platform</h3>
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={platformActions}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="action" />
                <YAxis />
                <Tooltip />
                <Bar dataKey="value" fill="#9333ea" />
              </BarChart>
            </ResponsiveContainer>
          </Card>
        </div>
      </div>
    </div>
  );
}

