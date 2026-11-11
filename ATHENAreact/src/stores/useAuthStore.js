import { create } from "zustand";
import {
    signInWithEmailAndPassword,
    createUserWithEmailAndPassword,
    signOut,
    onAuthStateChanged,
} from "firebase/auth";
import { auth as firebaseAuth } from "../config/firebase";
import api, { setAuthToken } from "../api/axios";

/**
 * Extrae un mensaje de error legible a partir de errores de Firebase o Axios.
 */
const errorMessage = (error) => {
    // Errores de Firebase Auth
    if (error?.code) {
        const firebaseErrors = {
            "auth/invalid-email": "El correo electrónico no es válido.",
            "auth/user-disabled": "Esta cuenta ha sido deshabilitada.",
            "auth/user-not-found": "No existe una cuenta con este correo.",
            "auth/wrong-password": "La contraseña es incorrecta.",
            "auth/invalid-credential": "El correo o la contraseña son incorrectos.",
            "auth/email-already-in-use": "Este correo ya está registrado.",
            "auth/weak-password": "La contraseña debe tener al menos 6 caracteres.",
            "auth/too-many-requests": "Demasiados intentos. Intenta más tarde.",
            "auth/network-request-failed": "Error de conexión. Verifica tu internet.",
        };
        return firebaseErrors[error.code] || error.message;
    }

    // Errores de red / Axios
    if (!error?.response) {
        if (error?.message && error?.message !== "Network Error") {
            return error.message;
        }
        return "No se pudo conectar con el servidor. Verifica que Laravel esté iniciado e inténtalo de nuevo.";
    }

    const validationError = Object.values(error.response.data?.errors ?? {})
        .flat()
        .find(Boolean);

    return (
        validationError ??
        error.response.data?.message ??
        "No fue posible completar la operación."
    );
};

/**
 * Referencia al listener de Firebase y semáforo para evitar peticiones
 * concurrentes entre login/register manual y el listener onAuthStateChanged.
 */
let _unsubscribe = null;
let _isAuthenticating = false;

/**
 * Retorna la ruta inicial de dashboard correspondiente al rol del usuario.
 */
export const getRedirectPathByRole = (role) => {
    switch (role) {
        case "super_admin":
            return "/super/dashboard";
        case "admin":
            return "/admin/dashboard";
        case "support":
            return "/support/dashboard";
        case "user":
        default:
            return "/dashboard";
    }
};

export const useAuthStore = create((set, get) => ({
    user: null,
    token: localStorage.getItem("athena_token") || null,
    role: null,
    isAuthenticated: false,
    isInitialized: false,
    loading: false,
    error: null,

    clearError: () => set({ error: null }),
    setError: (error) => set({ error }),

    /**
     * Inicia sesión con Firebase y obtiene un token Sanctum de Laravel.
     */
    login: async (credentials) => {
        _isAuthenticating = true;
        set({ loading: true, error: null });
        try {
            // 1. Autenticar con Firebase
            const userCredential = await signInWithEmailAndPassword(
                firebaseAuth,
                credentials.email,
                credentials.password
            );

            // 2. Obtener Firebase ID Token
            const idToken = await userCredential.user.getIdToken();

            // 3. Intercambiar por token Sanctum vía Laravel
            const response = await api.post("/auth/firebase-login", {
                firebase_token: idToken,
            });

            const user = response.data?.user;
            const token = response.data?.token || response.data?.access_token;

            if (!user || !token) {
                throw new Error("El servidor no devolvió la sesión o token de usuario.");
            }

            // Inyectar en localStorage y Axios inmediatamente
            setAuthToken(token);

            set({
                user,
                token,
                role: user.role || "user",
                isAuthenticated: true,
                isInitialized: true,
                loading: false,
                error: null,
            });

            return response.data;
        } catch (error) {
            // Si Firebase autenticó pero Laravel rechazó, cerrar Firebase
            if (firebaseAuth.currentUser) {
                await signOut(firebaseAuth).catch(() => {});
            }
            setAuthToken(null);
            set({
                user: null,
                token: null,
                role: null,
                loading: false,
                isAuthenticated: false,
                isInitialized: true,
                error: errorMessage(error),
            });
            throw error;
        } finally {
            _isAuthenticating = false;
        }
    },

    /**
     * Registra un usuario en Firebase y luego lo crea en Laravel.
     */
    register: async (userData) => {
        _isAuthenticating = true;
        set({ loading: true, error: null });
        try {
            // 1. Crear usuario en Firebase
            const userCredential = await createUserWithEmailAndPassword(
                firebaseAuth,
                userData.email,
                userData.password
            );

            // 2. Obtener Firebase ID Token
            const idToken = await userCredential.user.getIdToken();

            // 3. Registrar en Laravel (firebase-login crea el registro si no existe)
            const response = await api.post("/auth/firebase-login", {
                firebase_token: idToken,
                full_name: userData.full_name,
            });

            const user = response.data?.user;
            const token = response.data?.token || response.data?.access_token;

            if (!user || !token) {
                throw new Error("El servidor no devolvió la sesión o token de usuario.");
            }

            // Inyectar en localStorage y Axios inmediatamente
            setAuthToken(token);

            set({
                user,
                token,
                role: user.role || "user",
                isAuthenticated: true,
                isInitialized: true,
                loading: false,
                error: null,
            });

            return response.data;
        } catch (error) {
            // Rollback en Firebase si Laravel falló
            if (firebaseAuth.currentUser) {
                await signOut(firebaseAuth).catch(() => {});
            }
            setAuthToken(null);
            set({
                user: null,
                token: null,
                role: null,
                loading: false,
                isAuthenticated: false,
                isInitialized: true,
                error: errorMessage(error),
            });
            throw error;
        } finally {
            _isAuthenticating = false;
        }
    },

    /**
     * Recupera los datos del usuario actual usando el token Sanctum almacenado.
     */
    fetchCurrentUser: async () => {
        const token = localStorage.getItem("athena_token");
        if (!token) {
            setAuthToken(null);
            set({ user: null, token: null, role: null, isAuthenticated: false, isInitialized: true });
            return null;
        }

        try {
            const response = await api.get("/auth/me");
            const user = response.data?.user;
            if (!user) {
                throw new Error("No se pudo obtener el perfil de usuario.");
            }

            set({
                user,
                token,
                role: user.role || "user",
                isAuthenticated: true,
                isInitialized: true,
            });
            return user;
        } catch (error) {
            if (error.response?.status === 401) {
                setAuthToken(null);
                set({ user: null, token: null, role: null, isAuthenticated: false, isInitialized: true });
                return null;
            }

            set({ error: errorMessage(error), isInitialized: true });
            return null;
        }
    },

    /**
     * Inicializa la autenticación escuchando cambios de estado de Firebase.
     * Protegido contra llamadas concurrentes con login() o register().
     */
    initAuth: () => {
        if (_unsubscribe) _unsubscribe();

        _unsubscribe = onAuthStateChanged(firebaseAuth, async (firebaseUser) => {
            // Si hay un login o register explícito ejecutándose, delegar en dicha función
            if (_isAuthenticating) {
                return;
            }

            if (!firebaseUser) {
                // Firebase sin sesión activa
                setAuthToken(null);
                set({
                    user: null,
                    token: null,
                    role: null,
                    isAuthenticated: false,
                    isInitialized: true,
                    loading: false,
                });
                return;
            }

            // Si el estado en memoria ya está autenticado y tiene token, verificar si ya está listo
            const currentToken = localStorage.getItem("athena_token") || get().token;

            if (currentToken) {
                try {
                    const res = await api.get("/auth/me");
                    const user = res.data?.user;
                    if (user) {
                        set({
                            user,
                            token: currentToken,
                            role: user.role || "user",
                            isAuthenticated: true,
                            isInitialized: true,
                            loading: false,
                        });
                        return;
                    }
                } catch {
                    // Token Sanctum expirado o inválido; intentamos re-autenticar abajo con Firebase
                }
            }

            // Obtener token fresco de Firebase e intercambiar con Laravel
            try {
                const idToken = await firebaseUser.getIdToken();
                const res = await api.post("/auth/firebase-login", {
                    firebase_token: idToken,
                });

                const user = res.data?.user;
                const token = res.data?.token || res.data?.access_token;

                if (user && token) {
                    setAuthToken(token);
                    set({
                        user,
                        token,
                        role: user.role || "user",
                        isAuthenticated: true,
                        isInitialized: true,
                        loading: false,
                    });
                } else {
                    throw new Error("Respuesta inválida del servidor");
                }
            } catch {
                setAuthToken(null);
                set({
                    user: null,
                    token: null,
                    role: null,
                    isAuthenticated: false,
                    isInitialized: true,
                    loading: false,
                });
            }
        });

        return _unsubscribe;
    },

    /**
     * Cierra sesión en Firebase y Laravel, limpia todo el estado y cabeceras.
     */
    logout: async () => {
        set({ loading: true, error: null });
        try {
            // 1. Revocar token Sanctum en Laravel
            await api.post("/auth/logout").catch(() => {});

            // 2. Cerrar sesión de Firebase
            await signOut(firebaseAuth).catch(() => {});

            // 3. Limpiar estado local y headers
            setAuthToken(null);
            set({
                user: null,
                token: null,
                role: null,
                isAuthenticated: false,
                loading: false,
            });
        } catch (error) {
            setAuthToken(null);
            set({
                user: null,
                token: null,
                role: null,
                isAuthenticated: false,
                loading: false,
                error: errorMessage(error),
            });
        }
    },
}));

export default useAuthStore;
