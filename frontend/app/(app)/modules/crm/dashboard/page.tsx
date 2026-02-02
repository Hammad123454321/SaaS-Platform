"use client";

import { useState } from "react";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { SalesFunnel } from "@/components/dashboard/SalesFunnel";
import { BookingCalendar } from "@/components/dashboard/BookingCalendar";
import { AIChatWidget } from "@/components/dashboard/AIChatWidget";
import { ActionQueue } from "@/components/dashboard/ActionQueue";
import { Phone, Mail, Calendar, TrendingUp, DollarSign, Users } from "lucide-react";
import {
  BarChart,
  Bar,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from "recharts";

const funnelStages = [
  { label: "New Leads", total: 13, completed: 9, color: "#3b82f6" },
  { label: "Contacted", total: 15, completed: 5, color: "#60a5fa" },
  { label: "Followed Up", total: 8, completed: 4, color: "#34d399" },
  { label: "Booked", total: 10, completed: 5, color: "#fbbf24" },
  { label: "Lost", total: 4, completed: 3, color: "#f87171" },
];

const bookings = [
  {
    id: "1",
    name: "Pamela Jenkins",
    time: "9:00 am",
    location: "Standard Room-5",
    value: "$32.5k",
    status: "Send Proposal",
  },
  {
    id: "2",
    name: "Michael Kim",
    time: "11:00 am",
    location: "Apartment#1:3",
    value: "$9.6k",
    status: "Send Reminder",
  },
  {
    id: "3",
    name: "Chris Patel",
    time: "12:00 pm",
    location: "Standard Room-2",
    value: "$27k",
  },
];

const emailFollowUps = [
  { id: "1", text: "Send win-back offer to 42 at-risk guests. Build with Axiom9" },
  { id: "2", text: "Promote slow time slot tonight with a time-bound incentive." },
  { id: "3", text: "Reply to 3 new reviews from this weekend." },
  { id: "4", text: "Check staff coverage for Friday dinner rush." },
];

const phoneFollowUps = [
  { id: "1", text: "Michael Kim - Demo Reminder for 11:00 am" },
  { id: "2", text: "Chris Patel - Need a callback by end of the week" },
  { id: "3", text: "Lisa Martin - Proposal Follow-Up 6 days ago" },
];

const salesData = [
  { name: "Mon", bookings: 45, revenue: 12000 },
  { name: "Tue", bookings: 52, revenue: 15000 },
  { name: "Wed", bookings: 48, revenue: 13000 },
  { name: "Thu", bookings: 61, revenue: 18000 },
  { name: "Fri", bookings: 55, revenue: 16000 },
  { name: "Sat", bookings: 70, revenue: 22000 },
  { name: "Sun", bookings: 65, revenue: 20000 },
];

export default function SalesDashboard() {
  const [phoneNumber, setPhoneNumber] = useState("+1 (212) 555-0123");

  const followUpsData = [
    {
      name: "Pamela Jenkins",
      value: "$32.5k",
      company: "Horizon Solar",
      description: "Here's a summary of our needs and budget...",
      action: "Needs Proposal",
    },
    {
      name: "Emily Davis",
      value: "$9.6k",
      description: "I can meet tomorrow about our collaboration...",
      action: "Send Proposal",
    },
    {
      name: "Harry Evans",
      value: "$27k",
      company: "GlobalSoft",
      description: "Thanks for your proposal. It will review shortly.",
    },
  ];

  const bookingCalendarItems = [
    {
      name: "Pamela Jenkins",
      value: "$32.5k",
      location: "Standard Room-5",
      time: "9:00 am",
      action: "Send Proposal",
    },
    {
      name: "Michael Kim",
      value: "$45k",
      location: "Apartment #1",
      time: "11:00 am",
      description: "Demo Reminder 10:00 am",
      action: "Send Reminder",
    },
    {
      name: "Chris Patel",
      value: "$27k",
      location: "Entrepreneur",
      description: "Boring_abus. 6 days ago",
      action: "Call Back",
    },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-white rounded-2xl p-6 shadow-sm border border-gray-200">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm text-gray-500 mb-1">Welcome</p>
            <h1 className="text-3xl font-bold text-gray-900">Sales Dashboard</h1>
            <p className="text-sm text-gray-600 mt-1">Manage, track, and close your leads effectively.</p>
          </div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Left Column */}
        <div className="lg:col-span-2 space-y-6">
          {/* Sales Funnel */}
          <SalesFunnel stages={funnelStages} />

          {/* Follow-Ups */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Follow-Ups</h3>
              <div className="flex items-center gap-2">
                <span className="text-sm text-gray-600">3 overdue</span>
                <span className="text-sm font-medium text-purple-600">$32.5k</span>
              </div>
            </div>
            <div className="space-y-4">
              {followUpsData.map((item, index) => (
                <div key={index} className="p-4 border border-gray-200 rounded-lg">
                  <div className="flex items-start justify-between mb-2">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <h4 className="font-semibold text-gray-900">{item.name}</h4>
                        <span className="text-sm font-medium text-purple-600">{item.value}</span>
                        {item.company && (
                          <span className="text-sm text-gray-600">({item.company})</span>
                        )}
                      </div>
                      <p className="text-sm text-gray-600">{item.description}</p>
                    </div>
                    {item.action && (
                      <Button variant="gradient" size="sm">
                        {item.action}
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
            <div className="mt-4 pt-4 border-t border-gray-200">
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Email & Social</span>
                <Button variant="outline" size="sm">View All Emails</Button>
              </div>
            </div>
          </Card>

          {/* Today Booking Calendar */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Today Booking Calendar</h3>
            </div>
            <div className="space-y-4">
              {bookingCalendarItems.map((item, index) => (
                <div key={index} className="p-4 border border-gray-200 rounded-lg">
                  <div className="flex items-start justify-between mb-2">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <h4 className="font-semibold text-gray-900">{item.name}</h4>
                        <span className="text-sm font-medium text-purple-600">{item.value}</span>
                      </div>
                      {item.location && (
                        <p className="text-sm text-gray-600">{item.location}</p>
                      )}
                      {item.description && (
                        <p className="text-xs text-gray-500 mt-1">{item.description}</p>
                      )}
                    </div>
                    {item.action && (
                      <Button variant="gradient" size="sm">
                        {item.action}
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </Card>
        </div>

        {/* Right Column */}
        <div className="space-y-6">
          {/* VOIP Call */}
          <Card className="p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">VOIP Call</h3>
            <div className="space-y-4">
              <div className="text-center">
                <p className="text-2xl font-bold text-gray-900">{phoneNumber}</p>
              </div>
              <div className="flex gap-2">
                <Button variant="default" className="flex-1 bg-green-600 hover:bg-green-700">
                  <Phone className="h-4 w-4 mr-2" />
                  Call
                </Button>
                <Button variant="destructive" className="flex-1 bg-pink-600 hover:bg-pink-700">
                  End
                </Button>
              </div>
              <div className="grid grid-cols-3 gap-2">
                {[1, 2, 3, 4, 5, 6, 7, 8, 9, "*", 0, "#"].map((num) => (
                  <button
                    key={num}
                    className="p-3 border border-gray-200 rounded-lg hover:bg-gray-50 font-semibold text-gray-700"
                  >
                    {num}
                  </button>
                ))}
              </div>
              <div className="space-y-2">
                <p className="text-sm font-medium text-gray-700">Recent Contacts</p>
                {["Pamela Jenkins", "Lisa Martin", "Olivia Brown"].map((name) => (
                  <div key={name} className="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                    <span className="text-sm text-gray-700">{name}</span>
                    <Mail className="h-4 w-4 text-gray-400" />
                  </div>
                ))}
              </div>
              <textarea
                placeholder="Notes..."
                className="w-full p-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                rows={3}
              />
            </div>
          </Card>

          {/* Booking Review */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Booking Review</h3>
              <span className="text-sm font-medium text-purple-600">Estimated: $12.3k</span>
            </div>
            <div className="space-y-3 mb-4">
              <div className="p-3 border border-gray-200 rounded-lg">
                <p className="text-sm font-medium text-gray-900">Victoria Smith</p>
                <p className="text-xs text-gray-600">Apartment#8 - 9:00 am</p>
              </div>
              <div className="p-3 border border-gray-200 rounded-lg">
                <p className="text-sm font-medium text-gray-900">Jacob Garcia</p>
                <p className="text-xs text-gray-600">11:00 am - 12:00 pm</p>
              </div>
            </div>
            <div className="pt-4 border-t border-gray-200">
              <p className="text-sm text-gray-600 mb-2">Total activity: Today: $12.3k</p>
              <Button variant="gradient" className="w-full">
                <Calendar className="h-4 w-4 mr-2" />
                + Add Booking
              </Button>
            </div>
          </Card>

          {/* Quick Actions */}
          <Card className="p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
            <div className="space-y-2">
              <Button variant="gradient" className="w-full justify-start">
                Call new leads →
              </Button>
              <Button variant="gradient" className="w-full justify-start">
                Qualify recent leads →
              </Button>
              <Button variant="gradient" className="w-full justify-start">
                Send follow-up emails →
              </Button>
              <Button variant="gradient" className="w-full justify-start">
                View unread emails →
              </Button>
              <Button variant="gradient" className="w-full justify-start">
                Review proposal pipeline →
              </Button>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}

