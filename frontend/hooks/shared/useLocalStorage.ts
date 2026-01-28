import { useState, useEffect, useCallback } from "react";

/**
 * Hook to store data in sessionStorage (cleared when browser tab/window closes)
 * This is more secure than localStorage for sensitive session data
 */
export function useSessionStorage<T>(key: string, initialValue: T) {
  const [storedValue, setStoredValue] = useState<T>(() => {
    if (typeof window === "undefined") {
      return initialValue;
    }
    try {
      const item = window.sessionStorage.getItem(key);
      return item ? JSON.parse(item) : initialValue;
    } catch (error) {
      return initialValue;
    }
  });

  const setValue = useCallback((value: T | ((val: T) => T)) => {
    try {
      setStoredValue((prev) => {
        const valueToStore = value instanceof Function ? value(prev) : value;
        if (typeof window !== "undefined") {
          window.sessionStorage.setItem(key, JSON.stringify(valueToStore));
        }
        return valueToStore;
      });
    } catch (error) {
      console.error("Failed to set session storage:", error);
    }
  }, [key]);

  const removeValue = useCallback(() => {
    try {
      setStoredValue(initialValue);
      if (typeof window !== "undefined") {
        window.sessionStorage.removeItem(key);
      }
    } catch (error) {
      console.error("Failed to remove from session storage:", error);
    }
  }, [key, initialValue]);

  return [storedValue, setValue, removeValue] as const;
}

/**
 * @deprecated Use useSessionStorage instead for security.
 * localStorage persists across browser sessions which is a security risk for sensitive data.
 */
export function useLocalStorage<T>(key: string, initialValue: T) {
  console.warn(
    `useLocalStorage is deprecated. Use useSessionStorage instead for "${key}". ` +
    "localStorage persists across sessions which is a security risk."
  );
  return useSessionStorage(key, initialValue);
}





