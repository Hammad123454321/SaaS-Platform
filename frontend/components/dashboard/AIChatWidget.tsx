"use client";

import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Send, Bot } from "lucide-react";
import { useState } from "react";

interface AIChatWidgetProps {
  title?: string;
  initialMessage?: string;
  placeholder?: string;
  onSend?: (message: string) => void;
  className?: string;
}

export function AIChatWidget({
  title = "Ask Axiom9",
  initialMessage,
  placeholder = "What should we focus on today!",
  onSend,
  className,
}: AIChatWidgetProps) {
  const [message, setMessage] = useState("");

  const handleSend = () => {
    if (message.trim()) {
      onSend?.(message);
      setMessage("");
    }
  };

  return (
    <Card className={`p-6 ${className}`}>
      <div className="flex items-center gap-3 mb-4">
        <div className="w-10 h-10 bg-gradient-purple-blue rounded-lg flex items-center justify-center">
          <Bot className="h-5 w-5 text-white" />
        </div>
        <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
      </div>
      {initialMessage && (
        <div className="mb-4 p-3 bg-purple-50 rounded-lg">
          <p className="text-sm text-gray-700">{initialMessage}</p>
        </div>
      )}
      <div className="flex gap-2">
        <input
          type="text"
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          onKeyPress={(e) => e.key === "Enter" && handleSend()}
          placeholder={placeholder}
          className="flex-1 px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
        />
        <Button onClick={handleSend} variant="gradient" size="default">
          <Send className="h-4 w-4 mr-2" />
          Ask
        </Button>
      </div>
    </Card>
  );
}

