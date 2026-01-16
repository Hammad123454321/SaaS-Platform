"use client";

import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { AIChatWidget } from "@/components/dashboard/AIChatWidget";
import { MetricCard } from "@/components/dashboard/MetricCard";
import { AlertTriangle, ShoppingCart, DollarSign, Package, Clock } from "lucide-react";

const priceChanges = [
  {
    item: "Margherita Pizza",
    newPrice: "$11.95",
    clerk: "Alex",
    reason: "Promo Price",
    action: "Revert",
  },
  {
    item: "House Martini",
    newPrice: "$5",
    clerk: "Alex",
    reason: "Manual Comp",
    action: "Lock",
  },
  {
    item: "Caesar Salad",
    newPrice: "$8.99",
    clerk: "Taylor",
    reason: "Tism 11:56",
    action: "Flag",
  },
];

const clerkCorrections = [
  { time: "8:30 am", clerk: "Jessica", item: "Wrong item", amount: "$2.69" },
  { time: "9:15 am", clerk: "Alex", item: "Flagged $14.90", amount: "$14.90", status: "AUTH WAITING" },
  { time: "10:30 am", clerk: "Taylor", item: "Comped House Martini", amount: "$44.20" },
];

const lowInventory = [
  { item: "Ground Beef", status: "ORDER NOW", color: "orange" },
  { item: "House Red Wine", status: "3 LIPSIS", color: "blue" },
  { item: "Margarita Mix", status: "LOW", color: "orange" },
  { item: "Spinach Dip", status: "TTF REFURUY", color: "green" },
];

const recentTransactions = [
  { time: "6:48 am", clerk: "#4738", items: "House Martini, Cheeseburger", amount: "$24.67" },
  { time: "6:37 am", clerk: "Alex", items: "Margherita Pizza x2", amount: "$22.15" },
  { time: "8:94 am", clerk: "Taylor", items: "Spicy Margarita, Garlic Bread", status: "AUTH WAITING" },
];

export default function POSDashboard() {
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-white rounded-2xl p-6 shadow-sm border border-gray-200">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm text-gray-500 mb-1">Axiom9 - Point of Sale</p>
            <h1 className="text-3xl font-bold text-gray-900">POS Home</h1>
            <p className="text-sm text-gray-600 mt-1">Manage your point of sale operations.</p>
          </div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Left Column */}
        <div className="lg:col-span-2 space-y-6">
          {/* AI Chat */}
          <AIChatWidget
            title="Ask Axiom9"
            initialMessage="Orders up are #4728 + #4727. Want me to fast-track them?"
            placeholder="Ask about sales activity, low stock, or staff performance..."
          />

          {/* Today's Sales */}
          <div className="grid gap-4 sm:grid-cols-3">
            <Card className="p-5">
              <div className="space-y-2">
                <p className="text-sm text-gray-600">Net Sales</p>
                <p className="text-3xl font-bold text-gray-900">$13,820.50</p>
                <p className="text-xs text-gray-500">(nat set (OGS))</p>
              </div>
            </Card>
            <Card className="p-5 border-2 border-green-200 bg-green-50">
              <div className="space-y-2">
                <p className="text-sm text-gray-700">Closed Transactions</p>
                <p className="text-3xl font-bold text-green-700">108</p>
              </div>
            </Card>
            <Card className="p-5 border-2 border-orange-200 bg-orange-50">
              <div className="space-y-2">
                <p className="text-sm text-gray-700">Open Transactions</p>
                <p className="text-3xl font-bold text-orange-700">12</p>
              </div>
            </Card>
          </div>

          {/* Price Changes & Overrides */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Price Changes & Overrides</h3>
              <span className="text-sm text-gray-600">2 reactors &gt;</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-2 text-sm font-medium text-gray-700">Item</th>
                    <th className="text-left py-2 text-sm font-medium text-gray-700">New Price</th>
                    <th className="text-left py-2 text-sm font-medium text-gray-700">Clerk</th>
                    <th className="text-left py-2 text-sm font-medium text-gray-700">Reason</th>
                    <th className="text-left py-2 text-sm font-medium text-gray-700">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {priceChanges.map((change, index) => (
                    <tr key={index} className="border-b border-gray-100">
                      <td className="py-3 text-sm text-gray-900">{change.item}</td>
                      <td className="py-3 text-sm font-medium text-gray-900">{change.newPrice}</td>
                      <td className="py-3 text-sm text-gray-600">{change.clerk}</td>
                      <td className="py-3 text-sm text-gray-600">{change.reason}</td>
                      <td className="py-3">
                        <Button variant="outline" size="sm">
                          {change.action}
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
              <p className="text-sm font-medium text-yellow-800 flex items-center gap-2">
                <AlertTriangle className="h-4 w-4" />
                LOW STOCK ON 3 ITEMS
              </p>
            </div>
          </Card>

          {/* Clerk Corrections */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Clerk Corrections</h3>
              <div className="flex gap-2 text-sm">
                <span className="px-3 py-1 bg-gray-100 rounded-lg text-gray-700">O Void managers</span>
                <span className="px-3 py-1 bg-gray-100 rounded-lg text-gray-700">5 Reason</span>
              </div>
            </div>
            <div className="space-y-3">
              {clerkCorrections.map((correction, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between p-3 border border-gray-200 rounded-lg"
                >
                  <div className="flex-1">
                    <div className="flex items-center gap-3">
                      <span className="text-sm text-gray-600">{correction.time}</span>
                      <span className="text-sm font-medium text-gray-900">{correction.clerk}</span>
                    </div>
                    <p className="text-sm text-gray-600 mt-1">{correction.item}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-semibold text-gray-900">{correction.amount}</p>
                    {correction.status && (
                      <Button variant="gradient" size="sm" className="mt-1">
                        {correction.status}
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </Card>

          {/* Recent Transactions */}
          <Card className="p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Recent Transactions</h3>
            <div className="space-y-3">
              {recentTransactions.map((transaction, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between p-3 border border-gray-200 rounded-lg"
                >
                  <div className="flex-1">
                    <div className="flex items-center gap-3">
                      <span className="text-sm text-gray-600">{transaction.time}</span>
                      <span className="text-sm font-medium text-gray-900">{transaction.clerk}</span>
                    </div>
                    <p className="text-sm text-gray-600 mt-1">{transaction.items}</p>
                  </div>
                  <div className="text-right">
                    {transaction.amount && (
                      <p className="text-sm font-semibold text-gray-900">{transaction.amount}</p>
                    )}
                    {transaction.status && (
                      <Button variant="gradient" size="sm" className="mt-1">
                        {transaction.status}
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
          {/* Low Inventory */}
          <Card className="p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Low Inventory</h3>
            <div className="space-y-3">
              {lowInventory.map((item, index) => (
                <div
                  key={index}
                  className={`p-3 border-2 rounded-lg ${
                    item.color === "orange"
                      ? "border-orange-200 bg-orange-50"
                      : item.color === "blue"
                      ? "border-blue-200 bg-blue-50"
                      : "border-green-200 bg-green-50"
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className="font-medium text-gray-900">{item.item}</span>
                    <Button
                      variant="outline"
                      size="sm"
                      className={
                        item.color === "orange"
                          ? "border-orange-300 text-orange-700"
                          : item.color === "blue"
                          ? "border-blue-300 text-blue-700"
                          : "border-green-300 text-green-700"
                      }
                    >
                      {item.status}
                    </Button>
                  </div>
                </div>
              ))}
            </div>
            <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
              <p className="text-sm font-medium text-yellow-800 flex items-center gap-2">
                <AlertTriangle className="h-4 w-4" />
                LOW STOCK ON 3 ITEMS
              </p>
            </div>
          </Card>

          {/* Quick Actions */}
          <Card className="p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
            <div className="space-y-3">
              <Button variant="gradient" className="w-full justify-start">
                <ShoppingCart className="h-4 w-4 mr-2" />
                New Sale
              </Button>
              <Button variant="purple" className="w-full justify-start">
                <Package className="h-4 w-4 mr-2" />
                New Tab
              </Button>
              <Button variant="blue" className="w-full justify-start">
                <Clock className="h-4 w-4 mr-2" />
                Close Tab
              </Button>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}

