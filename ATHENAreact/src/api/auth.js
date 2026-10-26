import api from "./axios";

export const firebaseLogin = (firebaseToken, extraData = {}) =>
    api.post("/auth/firebase-login", { firebase_token: firebaseToken, ...extraData });

export const getCurrentUser = () => api.get("/auth/me");

export const logout = () => api.post("/auth/logout");
