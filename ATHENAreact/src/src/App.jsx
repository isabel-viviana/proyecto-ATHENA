import {
    BrowserRouter as Router,
    Routes,
    Route,
    Navigate,
} from "react-router-dom";

// Layout base
import MainLayout from "./components/MainLayout";

// Páginas de Autenticación
import LoginStudent from "./pages/authentications/login_student";
import RegisterStudent from "./pages/authentications/register_student";

// Layouts adicionales
import MockLayout from "./pages/mock_exams/MockLayout";
import ProfileLayout from "./pages/profiles/ProfileLayout";

// Páginas Internas
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

function App() {
    return (
        <Router>
            <Routes>
                {/* Rutas sin Sidebar (Login/Registro) */}
                <Route path="/login" element={<LoginStudent />} />
                <Route path="/register" element={<RegisterStudent />} />
                <Route path="/forgotPassword" element={<ForgotPassword />} />
                <Route path="/reset-password" element={<ResetPassword />} />

                {/* Rutas con Sidebar (MainLayout) */}
                <Route element={<MainLayout />}>
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
                    <Route
                        path="/practices-result"
                        element={<PracticesResult />}
                    />

                    <Route path="/profile" element={<ProfileLayout />}>
                        <Route index element={<Profiles />} />
                        <Route path="edit" element={<ProfileEdit />} />
                        <Route
                            path="achievements"
                            element={<ProfileLogros />}
                        />
                        <Route
                            path="notifications"
                            element={<Notificaciones />}
                        />
                    </Route>

                    <Route path="/ranking" element={<Ranking />} />
                    <Route path="/statistics" element={<Statistics />} />
                    <Route path="/analysis" element={<Analysis />} />
                    <Route path="/store" element={<Store />} />
                    <Route path="/subscriptions" element={<Subscriptions />} />
                    <Route path="/payments" element={<Payments />} />
                </Route>

                {/* Redirección por defecto */}
                <Route path="*" element={<Navigate to="/login" />} />
            </Routes>
        </Router>
    );
}

export default App;
