import { create } from "zustand";
import { persist } from "zustand/middleware";

type Entitlement = {
  module_code: string;
  enabled: boolean;
  seats: number;
  ai_access: boolean;
};

type UserInfo = {
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
      clearSession: () =>
        set({
          accessToken: null,
          refreshToken: null,
          user: null,
          entitlements: [],
          branding: { color: "#0ea5e9", logoUrl: "" },
        }),
      setEntitlements: (entitlements) => set({ entitlements }),
      setBranding: (branding) => set({ branding }),
    }),
    {
      name: "session-store",
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

