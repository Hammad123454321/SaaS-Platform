import { create, StateStorage } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";

type Entitlement = {
  module_code: string;
  enabled: boolean;
  seats: number;
  ai_access: boolean;
};

export type UserInfo = {
  id?: number;
  email: string;
  is_super_admin?: boolean;
  roles?: string[];
};

type SessionState = {
  accessToken: string | null;
  refreshToken: string | null;
  user: UserInfo | null;
  entitlements: Entitlement[];
  branding: { color: string; logoUrl?: string };
  setSession: (data: { accessToken: string | null; refreshToken: string | null; user: UserInfo | null }) => void;
  clearSession: () => void;
  setEntitlements: (entitlements: Entitlement[]) => void;
  setBranding: (branding: { color: string; logoUrl?: string }) => void;
};

// Custom storage that uses sessionStorage instead of localStorage for security
// Session data is cleared when the browser tab/window is closed
const sessionStorageWrapper: StateStorage = {
  getItem: (name: string): string | null => {
    if (typeof window === "undefined") return null;
    return sessionStorage.getItem(name);
  },
  setItem: (name: string, value: string): void => {
    if (typeof window === "undefined") return;
    sessionStorage.setItem(name, value);
  },
  removeItem: (name: string): void => {
    if (typeof window === "undefined") return;
    sessionStorage.removeItem(name);
  },
};

export const useSessionStore = create<SessionState>()(
  persist(
    (set) => ({
      accessToken: null,
      refreshToken: null,
      user: null,
      entitlements: [],
      branding: { color: "#0ea5e9", logoUrl: "" },
      setSession: ({ accessToken, refreshToken, user }) =>
        set({ accessToken, refreshToken, user }),
      clearSession: () => {
        // Clear cookies
        if (typeof document !== "undefined") {
          document.cookie = "access_token=; path=/; max-age=0";
          document.cookie = "refresh_token=; path=/; max-age=0";
        }
        // Also clear sessionStorage explicitly
        if (typeof window !== "undefined") {
          sessionStorage.removeItem("session-store");
        }
        set({
          accessToken: null,
          refreshToken: null,
          user: null,
          entitlements: [],
          branding: { color: "#0ea5e9", logoUrl: "" },
        });
      },
      setEntitlements: (entitlements) => set({ entitlements }),
      setBranding: (branding) => set({ branding }),
    }),
    {
      name: "session-store",
      storage: createJSONStorage(() => sessionStorageWrapper),
      partialize: (state) => ({
        accessToken: state.accessToken,
        refreshToken: state.refreshToken,
        user: state.user,
        entitlements: state.entitlements,
        branding: state.branding,
      }),
    }
  )
);

