import { AlertCircle } from "lucide-react";

interface ErrorMessageProps {
  message: string;
  onDismiss?: () => void;
}

export function ErrorMessage({ message, onDismiss }: ErrorMessageProps) {
  return (
    <div className="glass border-red-500/50 rounded-lg p-4 flex items-start gap-3">
      <AlertCircle className="h-4 w-4 text-red-400 mt-0.5" />
      <p className="text-red-200 text-sm flex-1">{message}</p>
      {onDismiss && (
        <button
          onClick={onDismiss}
          className="text-red-400 hover:text-red-300 text-sm"
        >
          Dismiss
        </button>
      )}
    </div>
  );
}





