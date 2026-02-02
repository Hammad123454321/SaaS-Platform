import { create } from "zustand";
import { persist, createJSONStorage, StateStorage } from "zustand/middleware";

type PosSessionState = {
  locationId: string | null;
  registerId: string | null;
  registerSessionId: string | null;
  setSession: (data: { locationId: string | null; registerId: string | null; registerSessionId: string | null }) => void;
  clearSession: () => void;
};

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

export const usePosSessionStore = create<PosSessionState>()(
  persist(
    (set) => ({
      locationId: null,
      registerId: null,
      registerSessionId: null,
      setSession: ({ locationId, registerId, registerSessionId }) =>
        set({ locationId, registerId, registerSessionId }),
      clearSession: () =>
        set({ locationId: null, registerId: null, registerSessionId: null }),
    }),
    {
      name: "pos-session-store",
      storage: createJSONStorage(() => sessionStorageWrapper),
      partialize: (state) => ({
        locationId: state.locationId,
        registerId: state.registerId,
        registerSessionId: state.registerSessionId,
      }),
    }
  )
);
