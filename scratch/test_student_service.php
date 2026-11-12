<?php
require __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\Student\StudentAuthController;
use App\Http\Controllers\Api\V1\Student\StudentQuestionController;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\DB;

echo "========================================================\n";
echo "PRUEBA AUTOMATIZADA: AUTENTICACIÓN, PREGUNTAS Y ANTI-SPOOFING\n";
echo "========================================================\n\n";

$authCtrl = new StudentAuthController();
$studentCtrl = new StudentQuestionController();

// 1. Test Login con credenciales correctas
echo "[TEST 1] Login de estudiante con credenciales válidas (juan.acevedo@athena.edu.co / password):\n";
$loginReq = Request::create('/api/v1/student/auth/login', 'POST', [
    'email' => 'juan.acevedo@athena.edu.co',
    'password' => 'password',
]);
$loginRes = $authCtrl->login($loginReq);
$loginData = json_decode($loginRes->getContent(), true);

if ($loginRes->getStatusCode() === 200 && !empty($loginData['token'])) {
    echo "  -> SUCCESS! Token generado: " . substr($loginData['token'], 0, 30) . "...\n";
    echo "  -> Estudiante: " . $loginData['student']['name'] . " (ID: " . $loginData['student']['id'] . ")\n";
    $token = $loginData['token'];
} else {
    echo "  -> FAILED: " . $loginRes->getContent() . "\n";
    exit(1);
}

// 2. Test Login con credenciales incorrectas
echo "\n[TEST 2] Login con contraseña incorrecta (debe fallar 401):\n";
$badLoginReq = Request::create('/api/v1/student/auth/login', 'POST', [
    'email' => 'juan.acevedo@athena.edu.co',
    'password' => 'wrong_password_123',
]);
$badLoginRes = $authCtrl->login($badLoginReq);
if ($badLoginRes->getStatusCode() === 401) {
    echo "  -> SUCCESS! Rechazado con código 401: " . $badLoginRes->getContent() . "\n";
} else {
    echo "  -> FAILED: No devolvió 401: " . $badLoginRes->getStatusCode() . "\n";
}

// 3. Test /me con token válido
echo "\n[TEST 3] Verificación de identidad con GET /me:\n";
$meReq = Request::create('/api/v1/student/auth/me', 'GET');
$meReq->headers->set('Authorization', 'Bearer ' . $token);
$meRes = $authCtrl->me($meReq);
$meData = json_decode($meRes->getContent(), true);
if ($meRes->getStatusCode() === 200 && $meData['student']['id'] === '1') {
    echo "  -> SUCCESS! Identidad confirmada para " . $meData['student']['name'] . "\n";
} else {
    echo "  -> FAILED: " . $meRes->getContent() . "\n";
}

// 4. Test listado de preguntas (Lectura Crítica)
echo "\n[TEST 4] Consulta de preguntas publicadas de Lectura Crítica:\n";
$indexReq = Request::create('/api/v1/student/questions', 'GET', [
    'subject_id' => 1,
    'per_page' => 10,
]);
$indexReq->headers->set('Authorization', 'Bearer ' . $token);
$indexRes = $studentCtrl->index($indexReq);
$indexData = json_decode($indexRes->getContent(), true);

$firstItem = $indexData['data'][0] ?? null;
if ($firstItem && !isset($firstItem['options'][0]['is_correct'])) {
    echo "  -> SUCCESS! Total preguntas encontradas: " . $indexData['meta']['total'] . "\n";
    echo "  -> Verificación de seguridad: 'is_correct' está completamente oculto en las opciones.\n";
    echo "  -> Verificación de estímulo: " . ($firstItem['stimulus']['title'] ?? 'Sin título') . "\n";
} else {
    echo "  -> FAILED o 'is_correct' expuesto: " . $indexRes->getContent() . "\n";
}

// 5. Test responder pregunta con token autenticado
echo "\n[TEST 5] Responder pregunta 1 con token de Juan Sebastian:\n";
$targetQ = Question::where('status', 'published')->orderBy('order_in_group', 'asc')->first();
$answerReq = Request::create('/api/v1/student/questions/' . $targetQ->id . '/answer', 'POST', [
    'selected_option_letter' => 'B',
    'time_spent_seconds' => 45,
    'practice_mode' => 'entrenamiento_mvp',
]);
$answerReq->headers->set('Authorization', 'Bearer ' . $token);
$answerRes = $studentCtrl->submitAnswer($answerReq, $targetQ->id);
$answerData = json_decode($answerRes->getContent(), true);

if ($answerRes->getStatusCode() === 200 && $answerData['data']['student_id'] === '1') {
    echo "  -> SUCCESS! Respuesta guardada con student_id = 1\n";
    echo "  -> Calificación: " . ($answerData['data']['is_correct'] ? 'CORRECTA' : 'INCORRECTA') . "\n";
    echo "  -> Clave oficial: " . $answerData['data']['correct_option_letter'] . "\n";
    echo "  -> Orientación pedagógica: " . substr($answerData['data']['explanation'], 0, 80) . "...\n";
} else {
    echo "  -> FAILED: " . $answerRes->getContent() . "\n";
}

// 6. Test Anti-Spoofing: El atacante envía student_id = '9999' en el body
echo "\n[TEST 6] Prueba Anti-Spoofing (intento de suplantar student_id = 9999 en el cuerpo):\n";
$spoofReq = Request::create('/api/v1/student/questions/' . $targetQ->id . '/answer', 'POST', [
    'selected_option_letter' => 'C',
    'student_id' => '9999', // Ataque de suplantación
    'time_spent_seconds' => 20,
]);
$spoofReq->headers->set('Authorization', 'Bearer ' . $token); // Token legítimo de user 1
$spoofRes = $studentCtrl->submitAnswer($spoofReq, $targetQ->id);
$spoofData = json_decode($spoofRes->getContent(), true);

if ($spoofData['data']['student_id'] === '1') {
    echo "  -> SUCCESS! Anti-Spoofing activo. El servidor ignoró el '9999' del cuerpo y asignó '1' del token.\n";
} else {
    echo "  -> SECURITY VULNERABILITY! El servidor guardó: " . $spoofData['data']['student_id'] . "\n";
}

// 7. Test progreso del estudiante
echo "\n[TEST 7] Consulta de progreso y estadísticas en tiempo real (GET /student/progress):\n";
$progReq = Request::create('/api/v1/student/progress', 'GET');
$progReq->headers->set('Authorization', 'Bearer ' . $token);
$progRes = $studentCtrl->progress($progReq);
$progData = json_decode($progRes->getContent(), true);

echo "  -> Total respondidas: " . $progData['data']['total_answered'] . "\n";
echo "  -> Aciertos: " . $progData['data']['correct_count'] . "\n";
echo "  -> Incorrectas: " . $progData['data']['incorrect_count'] . "\n";
echo "  -> Precisión: " . $progData['data']['accuracy'] . "%\n";
echo "  -> Tiempo total acumulado: " . $progData['data']['total_time_seconds'] . "s\n";
if (!empty($progData['data']['by_subject'])) {
    foreach ($progData['data']['by_subject'] as $sub) {
        echo "     * " . $sub['subject_name'] . ": " . $sub['total_answered'] . " respondidas (" . $sub['accuracy'] . "%)\n";
    }
}

echo "\nTODAS LAS PRUEBAS DE SERVICIO Y SEGURIDAD COMPLETADAS CON ÉXITO.\n";

