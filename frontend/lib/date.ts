import { format, formatDistance, isPast, isToday, isTomorrow, isThisWeek, parseISO } from "date-fns";

export function formatDate(date: string | Date): string {
  return format(typeof date === "string" ? parseISO(date) : date, "MMM dd, yyyy");
}

export function formatDateTime(date: string | Date): string {
  return format(typeof date === "string" ? parseISO(date) : date, "MMM dd, yyyy HH:mm");
}

export function formatRelativeDate(date: string | Date): string {
  return formatDistance(typeof date === "string" ? parseISO(date) : date, new Date(), {
    addSuffix: true,
  });
}

export function isOverdue(date: string | Date): boolean {
  const dateObj = typeof date === "string" ? parseISO(date) : date;
  return isPast(dateObj) && !isToday(dateObj);
}

export function getDateGroup(date: string | Date | null | undefined): string {
  if (!date) return "no-date";
  const dateObj = typeof date === "string" ? parseISO(date) : date;
  
  if (isToday(dateObj)) return "today";
  if (isTomorrow(dateObj)) return "tomorrow";
  if (isThisWeek(dateObj)) return "this-week";
  if (isOverdue(dateObj)) return "overdue";
  return "upcoming";
}





