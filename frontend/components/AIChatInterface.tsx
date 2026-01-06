"use client";

import { useState, useRef, useEffect } from "react";
import { Send, Bot, User, Loader2, Sparkles } from "lucide-react";
import { api } from "@/lib/api";

type Message = {
    role: "user" | "assistant" | "system";
    content: string;
};

export function AIChatInterface() {
    const [messages, setMessages] = useState<Message[]>([
        {
            role: "assistant",
            content: "Hello! I'm your AI business assistant. I can help you manage your CRM, HRM, tasks, and more. How can I help you today?",
        },
    ]);
    const [input, setInput] = useState("");
    const [loading, setLoading] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [messages]);

    const handleSend = async () => {
        if (!input.trim() || loading) return;

        const userMsg: Message = { role: "user", content: input };
        setMessages((prev) => [...prev, userMsg]);
        setInput("");
        setLoading(true);

        try {
            // Prepare context: backend expects list of { role, content }
            // We send the full history so the agent has context
            const payload = {
                messages: [...messages, userMsg].map((m) => ({
                    role: m.role,
                    content: m.content,
                })),
            };

            const res = await api.post("/ai/chat", payload);

            const aiMsg: Message = {
                role: "assistant",
                content: res.data.reply,
            };

            setMessages((prev) => [...prev, aiMsg]);
        } catch (err) {
            console.error("AI Chat Error:", err);
            // Determine error message
            let errorText = "Sorry, I encountered an error responding to your request.";
            // @ts-ignore
            if (err.response?.status === 403) {
                errorText = "Access denied. You might not have the 'AI' entitlement enabled.";
            }

            setMessages((prev) => [
                ...prev,
                { role: "assistant", content: errorText },
            ]);
        } finally {
            setLoading(false);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    return (
        <div className="flex h-[calc(100vh-12rem)] flex-col overflow-hidden rounded-2xl glass shadow-2xl">
            {/* Header */}
            <div className="border-b border-white/10 bg-white/5 px-6 py-4">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 shadow-lg">
                        <Sparkles className="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h2 className="font-semibold text-white">AI Assistant</h2>
                        <p className="text-xs text-gray-300">Powered by LangChain & GPT</p>
                    </div>
                </div>
            </div>

            {/* Messages Area */}
            <div className="flex-1 overflow-y-auto p-6 space-y-6" ref={scrollRef}>
                {messages.map((msg, idx) => {
                    const isAi = msg.role === "assistant" || msg.role === "system";
                    return (
                        <div
                            key={idx}
                            className={`flex items-start gap-3 ${isAi ? "justify-start" : "justify-end"}`}
                        >
                            {isAi && (
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-500/20 ring-1 ring-indigo-500/50">
                                    <Bot className="h-5 w-5 text-indigo-200" />
                                </div>
                            )}

                            <div
                                className={`max-w-[80%] rounded-2xl px-5 py-3 shadow-sm ${isAi
                                        ? "bg-white/10 text-gray-100 ring-1 ring-white/10"
                                        : "bg-cyan-600/90 text-white"
                                    }`}
                            >
                                <div className="whitespace-pre-wrap leading-relaxed text-sm">
                                    {msg.content}
                                </div>
                            </div>

                            {!isAi && (
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-cyan-500/20 ring-1 ring-cyan-500/50">
                                    <User className="h-5 w-5 text-cyan-200" />
                                </div>
                            )}
                        </div>
                    );
                })}
                {loading && (
                    <div className="flex items-start gap-3">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-500/20 ring-1 ring-indigo-500/50">
                            <Bot className="h-5 w-5 text-indigo-200" />
                        </div>
                        <div className="flex items-center gap-2 rounded-2xl bg-white/5 px-4 py-3 text-sm text-gray-300">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Thinking...
                        </div>
                    </div>
                )}
            </div>

            {/* Input Area */}
            <div className="border-t border-white/10 bg-black/20 p-4">
                <div className="relative mx-auto max-w-4xl">
                    <input
                        type="text"
                        className="w-full rounded-xl border-0 bg-white/10 px-5 py-4 pr-14 text-white placeholder-gray-400 backdrop-blur-md transition focus:bg-white/15 focus:ring-2 focus:ring-cyan-500/50"
                        placeholder="Ask AI to create a task, draft an email, or query your data..."
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        onKeyDown={handleKeyDown}
                        disabled={loading}
                    />
                    <button
                        onClick={handleSend}
                        disabled={loading || !input.trim()}
                        className="absolute right-2 top-2 rounded-lg p-2 text-cyan-200 transition hover:bg-white/10 disabled:opacity-50"
                    >
                        <Send className="h-5 w-5" />
                    </button>
                </div>
                <p className="mt-2 text-center text-xs text-gray-500">
                    AI acts on behalf of your account using established entitlements.
                </p>
            </div>
        </div>
    );
}
