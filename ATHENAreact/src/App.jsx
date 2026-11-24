import { useEffect } from "react";
import {
    BrowserRouter as Router,
    Routes,
    Route,
    Navigate,
} from "react-router-dom";

// Layout base (Estudiante)
import MainLayout from "./components/MainLayout";

// Layout Administrativo
import AdminLayout from "./components/AdminLayout";

// Páginas de Autenticación Estudiante
import LoginStudent from "./pages/authentications/login_student";
import RegisterStudent from "./pages/authentications/register_student";

// Páginas del Módulo Administrativo
import LoginAdmin from "./pages/admin/login_admin";
import DashboardSuper from "./pages/admin/dashboard_super";
import DashboardAdmin from "./pages/admin/dashboard_admin";
import DashboardSupport from "./pages/admin/dashboard_support";
import Users from "./pages/admin/users";
import Questions from "./pages/admin/questions";
import Subjects from "./pages/admin/subjects";
import Exams from "./pages/admin/exams";

// Layouts adicionales Estudiante
import MockLayout from "./pages/mock_exams/MockLayout";
import ProfileLayout from "./pages/profiles/ProfileLayout";

// Páginas Internas Estudiante
import Dashboard from "./pages/dashboards/dashboard";
import MockConfig from "./pages/mock_exams/mock_config";
import MockQuick from "./pages/mock_exams/mock_quick";
import MockExam from "./pages/mock_exams/mock_exam";
import MockResult from "./pages/mock_exams/mock_results";
import MockReview from "./pages/mock_exams/mock_review";
import MockHistory from "./pages/mock_exams/mock_history";
import AiTutor from "./pages/ai_tutor/chat";
import Practices from "./pages/practices/practice_config";
import PracticesFull from "./pages/practices/practice_full";
import PracticesResult from "./pages/practices/practice_results";
import PracticeReview from "./pages/practices/practice_review";
import Profiles from "./pages/profiles/profile";
import ProfileLogros from "./pages/profiles/profile_achievements";
import ProfileEdit from "./pages/profiles/profile_edit";
import Ranking from "./pages/ranking/ranking";
import Statistics from "./pages/statistics/statistics";
import Analysis from "./pages/statistics/analysis";
import Store from "./pages/store/store";
import Subscriptions from "./pages/subscriptions/subscription";
import Notificaciones from "./pages/profiles/profile_notifications";
import ForgotPassword from "./pages/authentications/forgot_password";
import ResetPassword from "./pages/authentications/reset_password";
import Payments from "./pages/subscriptions/payments";
import PaymentReturn from "./pages/subscriptions/payment_return";

// Auth & Route Guards
import useAuthStore from "./stores/useAuthStore";
import ProtectedRoute from "./components/ProtectedRoute";
import AdminRoute from "./components/AdminRoute";
import GuestRoute from "./components/GuestRoute";

function App() {
    const initAuth = useAuthStore((s) => s.initAuth);

    useEffect(() => {
        const unsubscribe = initAuth();
        return () => {
            if (typeof unsubscribe === "function") unsubscribe();
        };
    }, [initAuth]);

    return (
        <Router>
            <Routes>
                {/* Rutas de Invitado (redirigen automáticamente al dashboard si ya tiene sesión activa) */}
                <Route
                    path="/login"
                    element={
                        <GuestRoute>
                            <LoginStudent />
                        </GuestRoute>
                    }
                />
                <Route
                    path="/register"
                    element={
                        <GuestRoute>
                            <RegisterStudent />
                        </GuestRoute>
                    }
                />
                <Route path="/forgotPassword" element={<ForgotPassword />} />
                <Route path="/reset-password" element={<ResetPassword />} />

                {/* Ruta pública de retorno desde Wompi Web Checkout */}
                <Route path="/payment-return" element={<PaymentReturn />} />
                <Route path="/payments/return" element={<PaymentReturn />} />

                {/* Ruta de Autenticación Administrativa */}
                <Route
                    path="/admin/login"
                    element={
                        <GuestRoute>
                            <LoginAdmin />
                        </GuestRoute>
                    }
                />

                {/* Rutas del Módulo Administrativo (AdminRoute: solo superadmin y admin) */}
                <Route
                    element={
                        <AdminRoute allowedRoles={["superadmin", "admin"]}>
                            <AdminLayout />
                        </AdminRoute>
                    }
                >
                    <Route path="/dashboard/superadmin" element={<DashboardSuper />} />
                    <Route path="/dashboard/admin" element={<DashboardAdmin />} />
                    <Route path="/super/dashboard" element={<Navigate to="/dashboard/superadmin" replace />} />
                    <Route path="/admin/dashboard" element={<Navigate to="/dashboard/admin" replace />} />
                    <Route path="/admin/support" element={<DashboardSupport />} />
                    <Route path="/support/dashboard" element={<Navigate to="/admin/support" replace />} />
                    <Route path="/admin/users" element={<Users />} />
                    <Route path="/admin/questions" element={<Questions />} />
                    <Route path="/admin/subjects" element={<Subjects />} />
                    <Route path="/admin/exams" element={<Exams />} />
                </Route>

                {/* Rutas con Sidebar Estudiante (ProtectedRoute: rol student) */}
                <Route
                    element={
                        <ProtectedRoute allowedRoles={["student", "user"]}>
                            <MainLayout />
                        </ProtectedRoute>
                    }
                >
                    <Route path="/dashboard" element={<Dashboard />} />

                    <Route path="/mock" element={<MockLayout />}>
                        <Route index element={<MockConfig />} />
                        <Route path="config" element={<MockConfig />} />
                        <Route path="quick" element={<MockQuick />} />
                        <Route path="exam" element={<MockExam />} />
                        <Route path="result" element={<MockResult />} />
                        <Route path="review" element={<MockReview />} />
                        <Route path="history" element={<MockHistory />} />
                    </Route>

                    <Route path="/ai-tutor" element={<AiTutor />} />
                    <Route path="/practices" element={<Practices />} />
                    <Route path="/practices-full" element={<PracticesFull />} />
                    <Route path="/practices-result" element={<PracticesResult />} />
                    <Route path="/practices-review" element={<PracticeReview />} />

                    <Route path="/profile" element={<ProfileLayout />}>
                        <Route index element={<Profiles />} />
                        <Route path="edit" element={<ProfileEdit />} />
                        <Route path="achievements" element={<ProfileLogros />} />
                        <Route path="notifications" element={<Notificaciones />} />
                    </Route>

                    <Route path="/ranking" element={<Ranking />} />
                    <Route path="/statistics" element={<Statistics />} />
                    <Route
                        path="/notifications"
                        element={<Navigate to="/profile/notifications" replace />}
                    />
                    <Route path="/analysis" element={<Analysis />} />
                    <Route path="/store" element={<Store />} />
                    <Route path="/suscripciones" element={<Subscriptions />} />
                    <Route path="/subscriptions" element={<Subscriptions />} />
                    <Route path="/payments" element={<Payments />} />
                </Route>

                {/* Redirección por defecto */}
                <Route path="*" element={<Navigate to="/login" replace />} />
            </Routes>
        </Router>
    );
}

export default App;
