"use client";

import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Plus, Clock } from "lucide-react";

interface Booking {
  id: string;
  name: string;
  time: string;
  location?: string;
  value?: string;
  status?: string;
}

interface BookingCalendarProps {
  bookings: Booking[];
  estimatedValue?: string;
  className?: string;
}

export function BookingCalendar({
  bookings,
  estimatedValue,
  className,
}: BookingCalendarProps) {
  return (
    <Card className={`p-6 ${className}`}>
      <div className="flex items-center justify-between mb-4">
        <div>
          <h3 className="text-lg font-semibold text-gray-900">Booking Calendar</h3>
          {estimatedValue && (
            <p className="text-sm text-gray-600 mt-1">Estimated: {estimatedValue}</p>
          )}
        </div>
        <Button variant="gradient" size="sm">
          <Plus className="h-4 w-4 mr-2" />
          Add Booking
        </Button>
      </div>
      <div className="space-y-3">
        {bookings.map((booking) => (
          <div
            key={booking.id}
            className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition"
          >
            <div className="flex items-start justify-between">
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                  <h4 className="font-semibold text-gray-900">{booking.name}</h4>
                  {booking.value && (
                    <span className="text-sm font-medium text-purple-600">{booking.value}</span>
                  )}
                </div>
                {booking.location && (
                  <p className="text-sm text-gray-600 mb-1">{booking.location}</p>
                )}
                <div className="flex items-center gap-1 text-sm text-gray-500">
                  <Clock className="h-3 w-3" />
                  <span>{booking.time}</span>
                </div>
              </div>
              {booking.status && (
                <span className="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded">
                  {booking.status}
                </span>
              )}
            </div>
          </div>
        ))}
      </div>
      {estimatedValue && (
        <div className="mt-4 pt-4 border-t border-gray-200">
          <p className="text-sm text-gray-600">
            Total activity: <span className="font-semibold text-gray-900">{estimatedValue}</span>
          </p>
        </div>
      )}
    </Card>
  );
}

