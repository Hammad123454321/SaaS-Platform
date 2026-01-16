import { AlertCircle } from "lucide-react";

interface ErrorMessageProps {
  message: string;
  onDismiss?: () => void;
}

export function ErrorMessage({ message, onDismiss }: ErrorMessageProps) {
  return (
    <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-3">
      <AlertCircle className="h-4 w-4 text-red-500 mt-0.5" />
      <p className="text-red-700 text-sm flex-1">{message}</p>
      {onDismiss && (
        <button
          onClick={onDismiss}
          className="text-red-600 hover:text-red-800 text-sm font-medium"
        >
          Dismiss
        </button>
      )}
    </div>
  );
}





