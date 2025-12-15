import axios from "axios";
import { useSessionStore } from "./store";

const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_BASE_URL || "http://localhost:8000",
  withCredentials: true,
});

api.interceptors.request.use((config) => {
  const token = useSessionStore.getState().accessToken;
  if (token) {
    config.headers = {
      ...config.headers,
      Authorization: `Bearer ${token}`,
    };
    // Ensure cookies still go through even when Authorization is present
    config.withCredentials = true;
  }
  return config;
});

export { api };

