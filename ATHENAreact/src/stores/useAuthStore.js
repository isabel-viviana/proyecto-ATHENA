import { create } from "zustand";
import {
    signInWithEmailAndPassword,
    createUserWithEmailAndPassword,
    signInWithPopup,
    GoogleAuthProvider,
    signOut,
    onAuthStateChanged,
    sendPasswordResetEmail,
    verifyPasswordResetCode,
    confirmPasswordReset,
    EmailAuthProvider,
    reauthenticateWithCredential,
    updatePassword,
} from "firebase/auth";
import { auth as firebaseAuth } from "../config/firebase";
import api, { setAuthToken } from "../api/axios";

/**
 * Extrae un mensaje de error legible a partir de errores de Firebase o Axios.
 */
const errorMessage = (error) => {
    // Priorizar mensajes explícitos del backend (ej. HTTP 403 cuenta bloqueada)
    if (error?.response?.data?.message) {
        return error.response.data.message;
    }

    const validationError = Object.values(error?.response?.data?.errors ?? {})
        .flat()
        .find(Boolean);
    if (validationError) {
        return validationError;
    }

    // Errores de Firebase Auth
    if (error?.code) {
        const firebaseErrors = {
            "auth/invalid-email": "El correo electrónico no es válido.",
            "auth/user-disabled": "Tu cuenta ha sido bloqueada o deshabilitada por un administrador.",
            "auth/user-not-found": "No existe una cuenta registrada con este correo electrónico.",
            "auth/wrong-password": "La contraseña ingresada es incorrecta.",
            "auth/invalid-credential": "El correo o la contraseña son incorrectos.",
            "auth/email-already-in-use": "Este correo ya está registrado.",
            "auth/weak-password": "La contraseña debe tener al menos 8 caracteres.",
            "auth/too-many-requests": "Demasiados intentos fallidos. Intenta más tarde.",
            "auth/network-request-failed": "Error de conexión. Verifica tu internet.",
            "auth/popup-closed-by-user": "Se cerró la ventana de Google antes de completar el inicio de sesión.",
            "auth/cancelled-popup-request": "Solicitud de ventana emergente cancelada.",
            "auth/popup-blocked": "El navegador bloqueó la ventana emergente de Google. Permítela e intenta de nuevo.",
            "auth/account-exists-with-different-credential": "Ya existe una cuenta con este correo usando otro método de acceso.",
            "auth/requires-recent-login": "Por seguridad, cierra sesión y vuelve a ingresar antes de cambiar tu contraseña.",
            "auth/expired-action-code": "El enlace de recuperación ha expirado. Solicita uno nuevo.",
            "auth/invalid-action-code": "El enlace de recuperación es inválido o ya fue utilizado.",
        };
        return firebaseErrors[error.code] || error.message;
    }

    if (!error?.response) {
        if (error?.message && error?.message !== "Network Error") {
            return error.message;
        }
        return "No se pudo conectar con el servidor. Verifica tu conexión e inténtalo de nuevo.";
    }

    return "No fue posible completar la operación.";
};

let _unsubscribe = null;
let _isAuthenticating = false;
let _blockedListenerRegistered = false;

/**
 * Retorna la ruta inicial de dashboard correspondiente al rol del usuario.
 */
export const getRedirectPathByRole = (role) => {
    switch (role) {
        case "superadmin":
        case "super_admin":
            return "/dashboard/superadmin";
        case "admin":
            return "/dashboard/admin";
        case "support":
            return "/support/dashboard";
        case "student":
        case "user":
        default:
            return "/dashboard";
    }
};

const clearAllLocalAuthData = () => {
    setAuthToken(null);
    try {
        localStorage.removeItem("athena_token");
        localStorage.removeItem("athena_admin_token");
        localStorage.removeItem("athena_admin_user");
    } catch {
        // Ignorar errores de storage
    }
};

export const useAuthStore = create((set, get) => ({
    user: null,
    token: localStorage.getItem("athena_token") || null,
    role: null,
    isAuthenticated: false,
    isInitialized: false,
    isAuthLoading: true,
    loading: false,
    error: sessionStorage.getItem("athena_blocked_notice") || null,

    clearError: () => {
        try {
            sessionStorage.removeItem("athena_blocked_notice");
        } catch {
            // ignore
        }
        set({ error: null });
    },
    setError: (error) => set({ error }),

    /**
     * Inicia sesión con Firebase (o fallback tradicional Laravel), verifica que el usuario
     * no esté bloqueado en PostgreSQL y obtiene un token Sanctum.
     */
    login: async (credentials) => {
        _isAuthenticating = true;
        set({ loading: true, error: null });
        try {
            sessionStorage.removeItem("athena_blocked_notice");
        } catch {
            // ignore
        }

        try {
            let response;

            try {
                // 1. Autenticar con Firebase
                const userCredential = await signInWithEmailAndPassword(
                    firebaseAuth,
                    credentials.email.trim(),
                    credentials.password
                );

                // 2. Obtener Firebase ID Token
                const idToken = await userCredential.user.getIdToken();

                // 3. Intercambiar por token Sanctum en Laravel (verifica bloqueo 403 en PostgreSQL)
                response = await api.post("/auth/firebase-login", {
                    firebase_token: idToken,
                });
            } catch (firebaseOrBackendErr) {
                // Si el backend rechazó explícitamente con 403 (Cuenta bloqueada), cerrar Firebase y propagar error
                if (firebaseOrBackendErr?.response?.status === 403) {
                    throw firebaseOrBackendErr;
                }

                // Si el usuario fue creado sólo en PostgreSQL (seeders/admin) y no en Firebase, intentar login tradicional
                if (
                    firebaseOrBackendErr?.code === "auth/user-not-found" ||
                    firebaseOrBackendErr?.code === "auth/invalid-credential" ||
                    firebaseOrBackendErr?.code === "auth/wrong-password"
                ) {
                    try {
                        response = await api.post("/auth/login", {
                            email: credentials.email.trim(),
                            password: credentials.password,
                        });
                    } catch (laravelErr) {
                        if (laravelErr?.response?.status === 403) {
                            throw laravelErr;
                        }
                        throw firebaseOrBackendErr;
                    }
                } else {
                    throw firebaseOrBackendErr;
                }
            }

            const user = response.data?.user;
            const token = response.data?.token || response.data?.access_token;

            if (!user || !token) {
                throw new Error("El servidor no devolvió la sesión o token de usuario.");
            }

            setAuthToken(token);

            set({
                user,
                token,
                role: user.role || "student",
                isAuthenticated: true,
                isInitialized: true,
                isAuthLoading: false,
                loading: false,
                error: null,
            });

            return response.data;
        } catch (error) {
            if (firebaseAuth.currentUser) {
                await signOut(firebaseAuth).catch(() => {});
            }
            clearAllLocalAuthData();
            const msg = errorMessage(error);
            set({
                user: null,
                token: null,
                role: null,
                loading: false,
                isAuthenticated: false,
                isInitialized: true,
                isAuthLoading: false,
                error: msg,
            });
            throw new Error(msg);
        } finally {
            _isAuthenticating = false;
        }
    },

    /**
     * Inicia sesión con Google (Firebase Popup) e intercambia el ID Token por Sanctum en Laravel.
     */
    loginWithGoogle: async () => {
        _isAuthenticating = true;
        set({ loading: true, error: null });
        try {
            sessionStorage.removeItem("athena_blocked_notice");
        } catch {
            // ignore
        }

        try {
            const provider = new GoogleAuthProvider();
            const userCredential = await signInWithPopup(firebaseAuth, provider);
            const idToken = await userCredential.user.getIdToken();

            const payload = { firebase_token: idToken };
            if (userCredential.user.displayName) {
                payload.full_name = userCredential.user.displayName;
            }

            const response = await api.post("/auth/firebase-login", payload);

            const user = response.data?.user;
            const token = response.data?.token || response.data?.access_token;

            if (!user || !token) {
                throw new Error("El servidor no devolvió la sesión o token de usuario.");
            }

            setAuthToken(token);

            set({
                user,
                token,
                role: user.role || "student",
                isAuthenticated: true,
                isInitialized: true,
                isAuthLoading: false,
                loading: false,
                error: null,
            });

            return response.data;
        } catch (error) {
            if (firebaseAuth.currentUser) {
                await signOut(firebaseAuth).catch(() => {});
            }
            clearAllLocalAuthData();
            const msg = errorMessage(error);
            set({
                user: null,
                token: null,
                role: null,
                loading: false,
                isAuthenticated: false,
                isInitialized: true,
                isAuthLoading: false,
                error: msg,
            });
            throw new Error(msg);
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
            const userCredential = await createUserWithEmailAndPassword(
                firebaseAuth,
                userData.email.trim(),
                userData.password
            );

            const idToken = await userCredential.user.getIdToken();

            const response = await api.post("/auth/firebase-login", {
                firebase_token: idToken,
                full_name: userData.full_name,
            });

            const user = response.data?.user;
            const token = response.data?.token || response.data?.access_token;

            if (!user || !token) {
                throw new Error("El servidor no devolvió la sesión o token de usuario.");
            }

            setAuthToken(token);

            set({
                user,
                token,
                role: user.role || "student",
                isAuthenticated: true,
                isInitialized: true,
                isAuthLoading: false,
                loading: false,
                error: null,
            });

            return response.data;
        } catch (error) {
            if (firebaseAuth.currentUser) {
                await signOut(firebaseAuth).catch(() => {});
            }
            clearAllLocalAuthData();
            const msg = errorMessage(error);
            set({
                user: null,
                token: null,
                role: null,
                loading: false,
                isAuthenticated: false,
                isInitialized: true,
                isAuthLoading: false,
                error: msg,
            });
            throw new Error(msg);
        } finally {
            _isAuthenticating = false;
        }
    },

    /**
     * Fuente única de verdad: valida el token Sanctum contra /api/auth/me en PostgreSQL.
     * Si el usuario fue bloqueado (403) o el token expiró (401), cierra Firebase y limpia la sesión.
     */
    fetchCurrentUser: async () => {
        const token = localStorage.getItem("athena_token");
        if (!token) {
            clearAllLocalAuthData();
            set({
                user: null,
                token: null,
                role: null,
                isAuthenticated: false,
                isInitialized: true,
                isAuthLoading: false,
            });
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
                role: user.role || "student",
                isAuthenticated: true,
                isInitialized: true,
                isAuthLoading: false,
            });
            return user;
        } catch (error) {
            const status = error.response?.status;
            if (status === 401 || status === 403) {
                if (firebaseAuth.currentUser) {
                    await signOut(firebaseAuth).catch(() => {});
                }
                clearAllLocalAuthData();
                set({
                    user: null,
                    token: null,
                    role: null,
                    isAuthenticated: false,
                    isInitialized: true,
                    isAuthLoading: false,
                    error: status === 403 ? errorMessage(error) : get().error,
                });
                return null;
            }

            set({
                error: errorMessage(error),
                isInitialized: true,
                isAuthLoading: false,
            });
            return null;
        }
    },

    /**
     * Inicializa la autenticación verificando la sesión contra Laravel (/api/auth/me)
     * y sincronizando con Firebase Auth.
     */
    initAuth: () => {
        if (_unsubscribe) _unsubscribe();

        // Listener global para expulsar inmediatamente a un usuario bloqueado en mitad de sesión
        if (typeof window !== "undefined" && !_blockedListenerRegistered) {
            _blockedListenerRegistered = true;
            window.addEventListener("athena:account-blocked", async (event) => {
                const blockedMsg =
                    event?.detail?.message ||
                    "Tu cuenta ha sido bloqueada o suspendida por un administrador.";

                if (firebaseAuth.currentUser) {
                    await signOut(firebaseAuth).catch(() => {});
                }
                clearAllLocalAuthData();
                set({
                    user: null,
                    token: null,
                    role: null,
                    isAuthenticated: false,
                    isInitialized: true,
                    isAuthLoading: false,
                    loading: false,
                    error: blockedMsg,
                });
            });
        }

        _unsubscribe = onAuthStateChanged(firebaseAuth, async (firebaseUser) => {
            if (_isAuthenticating) {
                return;
            }

            const currentToken = localStorage.getItem("athena_token");

            // 1. Si hay un token Sanctum en localStorage, validarlo siempre contra Laravel (/auth/me)
            if (currentToken) {
                try {
                    const res = await api.get("/auth/me");
                    const user = res.data?.user;
                    if (user) {
                        set({
                            user,
                            token: currentToken,
                            role: user.role || "student",
                            isAuthenticated: true,
                            isInitialized: true,
                            isAuthLoading: false,
                            loading: false,
                        });
                        return;
                    }
                } catch (err) {
                    // Si la cuenta fue bloqueada (403), cerrar también Firebase y abortar
                    if (err?.response?.status === 403) {
                        if (firebaseAuth.currentUser) {
                            await signOut(firebaseAuth).catch(() => {});
                        }
                        clearAllLocalAuthData();
                        set({
                            user: null,
                            token: null,
                            role: null,
                            isAuthenticated: false,
                            isInitialized: true,
                            isAuthLoading: false,
                            loading: false,
                            error: errorMessage(err),
                        });
                        return;
                    }
                    clearAllLocalAuthData();
                }
            }

            // 2. Si no hay token Sanctum válido y tampoco hay usuario en Firebase
            if (!firebaseUser) {
                clearAllLocalAuthData();
                set({
                    user: null,
                    token: null,
                    role: null,
                    isAuthenticated: false,
                    isInitialized: true,
                    isAuthLoading: false,
                    loading: false,
                });
                return;
            }

            // 3. Si hay sesión en Firebase, intercambiar con Laravel (verifica estado activo en PostgreSQL)
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
                        role: user.role || "student",
                        isAuthenticated: true,
                        isInitialized: true,
                        isAuthLoading: false,
                        loading: false,
                    });
                } else {
                    throw new Error("Respuesta inválida del servidor");
                }
            } catch (err) {
                await signOut(firebaseAuth).catch(() => {});
                clearAllLocalAuthData();
                set({
                    user: null,
                    token: null,
                    role: null,
                    isAuthenticated: false,
                    isInitialized: true,
                    isAuthLoading: false,
                    loading: false,
                    error: err?.response?.status === 403 ? errorMessage(err) : get().error,
                });
            }
        });

        return _unsubscribe;
    },

    /**
     * Solicita recuperación de contraseña verificando primero en Laravel y enviando el correo oficial con Firebase Auth.
     */
    requestPasswordReset: async (email) => {
        const cleanEmail = String(email || "").trim().toLowerCase();
        if (!cleanEmail) {
            throw new Error("Por favor ingresa tu correo electrónico.");
        }

        // 1. Verificar en PostgreSQL que el correo exista y no esté bloqueado
        await api.post("/auth/forgot-password", { email: cleanEmail });

        // 2. Enviar correo real de restablecimiento a través de Firebase Auth
        try {
            await sendPasswordResetEmail(firebaseAuth, cleanEmail);
        } catch (firebaseErr) {
            // Si el usuario solo existía en PostgreSQL (sin cuenta en Firebase), no bloquear el flujo si ya se generó el token en BD
            if (firebaseErr?.code && firebaseErr.code !== "auth/user-not-found") {
                throw new Error(errorMessage(firebaseErr));
            }
        }

        return {
            success: true,
            message:
                "Hemos enviado un enlace de recuperación a tu correo electrónico. Revisa tu bandeja de entrada y la carpeta de spam.",
        };
    },

    /**
     * Confirma el restablecimiento de contraseña con oobCode de Firebase y sincroniza el hash en PostgreSQL.
     */
    confirmPasswordResetFlow: async ({ oobCode, email, password, passwordConfirmation, token }) => {
        let resolvedEmail = String(email || "").trim().toLowerCase();
        let firebaseVerified = false;

        if (oobCode) {
            const firebaseEmail = await verifyPasswordResetCode(firebaseAuth, oobCode);
            await confirmPasswordReset(firebaseAuth, oobCode, password);
            resolvedEmail = String(firebaseEmail || resolvedEmail).trim().toLowerCase();
            firebaseVerified = true;
        }

        if (resolvedEmail) {
            await api.post("/auth/reset-password", {
                email: resolvedEmail,
                password,
                password_confirmation: passwordConfirmation,
                token: token || null,
                firebase_oob_verified: firebaseVerified,
            });
        }

        return {
            success: true,
            message: "¡Tu contraseña ha sido actualizada! Ya puedes iniciar sesión con tu nueva clave.",
        };
    },

    /**
     * Cambia la contraseña del usuario autenticado:
     * 1. Si tiene sesión en Firebase Auth (proveedor password), reautentica con currentPassword y actualiza en Firebase.
     * 2. Actualiza y sincroniza la contraseña en PostgreSQL (POST /api/auth/change-password).
     */
    changePassword: async ({ currentPassword, newPassword, confirmPassword }) => {
        let freshFirebaseToken = null;
        const fbUser = firebaseAuth.currentUser;

        if (fbUser && fbUser.email) {
            const hasPasswordProvider = fbUser.providerData?.some(
                (p) => p.providerId === "password"
            );

            if (hasPasswordProvider) {
                try {
                    const credential = EmailAuthProvider.credential(
                        fbUser.email,
                        currentPassword
                    );
                    await reauthenticateWithCredential(fbUser, credential);
                    await updatePassword(fbUser, newPassword);
                    freshFirebaseToken = await fbUser.getIdToken(true);
                } catch (fbErr) {
                    throw new Error(errorMessage(fbErr));
                }
            }
        }

        const res = await api.post("/auth/change-password", {
            current_password: currentPassword,
            new_password: newPassword,
            new_password_confirmation: confirmPassword,
            firebase_token: freshFirebaseToken,
        });

        return res.data;
    },

    /**
     * Cierre de sesión atómico: revoca token en Laravel, cierra sesión en Firebase y limpia store + localStorage.
     */
    logout: async () => {
        set({ loading: true, error: null });
        try {
            await api.post("/auth/logout").catch(() => {});
            await signOut(firebaseAuth).catch(() => {});
        } finally {
            clearAllLocalAuthData();
            set({
                user: null,
                token: null,
                role: null,
                isAuthenticated: false,
                isInitialized: true,
                isAuthLoading: false,
                loading: false,
            });
        }
    },
}));

export default useAuthStore;
