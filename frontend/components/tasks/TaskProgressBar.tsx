interface TaskProgressBarProps {
  percentage: number;
}

export function TaskProgressBar({ percentage }: TaskProgressBarProps) {
  return (
    <div className="w-full bg-gray-700 rounded-full h-2">
      <div
        className="bg-primary h-2 rounded-full transition-all duration-300"
        style={{ width: `${percentage}%` }}
      />
    </div>
  );
}





