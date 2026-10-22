import api from "./axios";

export const login = (credentials) => api.post("/auth/login", credentials);
export const getCurrentUser = () => api.get("/auth/me");
export const logout = () => api.post("/auth/logout");
