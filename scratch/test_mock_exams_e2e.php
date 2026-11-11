<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';

$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Services\StudentTokenService;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\Student\StudentExamController;

echo "=======================================================\n";
echo "PRUEBA INTEGRAL E2E: MÓDULO DE SIMULACROS ICFES ATHENA\n";
echo "=======================================================\n\n";

// 1. Generar token JWT para estudiante de prueba (Juan Acevedo, id: 1)
$tokenService = app(StudentTokenService::class);
$studentPayload = [
    'sub'   => 1,
    'email' => 'juan.acevedo@athena.edu.co',
    'name'  => 'Juan David Acevedo',
    'role'  => 'student',
];
$studentToken = $tokenService->generateToken($studentPayload);

$otherStudentToken = $tokenService->generateToken([
    'sub'   => 99,
    'email' => 'otro.estudiante@athena.edu.co',
    'name'  => 'Estudiante Intruso',
    'role'  => 'student',
]);

$controller = app(StudentExamController::class);

// TEST 1: Config Options
echo "--- TEST 1: Opciones de Configuración (GET /config-options) ---\n";
$req = Request::create('/api/v1/student/exams/config-options', 'GET');
$req->headers->set('Authorization', 'Bearer ' . $studentToken);
$res = $controller->configOptions($req);
$configData = json_decode($res->getContent(), true);

assert($configData['success'] === true, 'Config options devolvió success: true');
$lecturaCritica = collect($configData['data']['areas'])->firstWhere('name', 'Lectura Crítica');
assert($lecturaCritica !== null, 'Lectura Crítica está en el catálogo de áreas');
assert($lecturaCritica['available'] === true, 'Lectura Crítica está disponible');
assert($lecturaCritica['published_count'] === 49, 'Lectura Crítica tiene 49 preguntas publicadas');

$otrasAreas = collect($configData['data']['areas'])->where('name', '!=', 'Lectura Crítica');
foreach ($otrasAreas as $area) {
    assert($area['available'] === false, "Área {$area['name']} debe estar marcada como no disponible (Próximamente)");
}
echo "✓ Config options validado exitosamente. Lectura Crítica activa (49 preguntas), 4 áreas restantes 'Próximamente'.\n\n";

// Limpiar intentos previos en progreso de juan para prueba limpia
ExamAttempt::where('student_id', '1')->where('status', 'in_progress')->delete();

// TEST 2: Iniciar Simulacro de 10 preguntas
echo "--- TEST 2: Iniciar Simulacro (POST /start) ---\n";
$startPayload = [
    'subject_id'       => 1,
    'mode'             => 'area',
    'questions_count'  => 10,
    'duration_minutes' => 20,
];
$req = Request::create('/api/v1/student/exams/start', 'POST', $startPayload);
$req->headers->set('Authorization', 'Bearer ' . $studentToken);
$res = $controller->start($req);
$startData = json_decode($res->getContent(), true);

assert($startData['success'] === true, 'Simulacro iniciado exitosamente');
$attemptId = $startData['data']['attempt']['id'];
assert(!empty($attemptId), 'ID de intento UUID generado');
assert($startData['data']['attempt']['questions_count'] === 10, 'Total preguntas es 10');
assert(count($startData['data']['questions']) === 10, 'Devuelve 10 preguntas formateadas');

// TEST 2.1: REGLA DE SEGURIDAD CRÍTICA - ZERO EXPOSURE DURANTE EL EXAMEN
echo "Verificando regla de seguridad: CERO EXPOSICIÓN de respuestas durante el examen...\n";
foreach ($startData['data']['questions'] as $q) {
    assert(!isset($q['is_correct']), "Pregunta {$q['id']} no debe tener 'is_correct'");
    assert(!isset($q['explanation']), "Pregunta {$q['id']} no debe exponer 'explanation'");
    foreach ($q['options'] as $opt) {
        assert(!isset($opt['is_correct']), "Opción {$opt['id']} no debe exponer 'is_correct'");
        assert(!empty($opt['option_letter']), "Opción debe tener letra A, B, C o D");
    }
}
echo "✓ SEGURIDAD CONFIRMADA: 100% de las opciones tienen 'is_correct' protegido y oculto.\n\n";

// TEST 3: Recuperación de Sesión / Intento Activo (GET /active)
echo "--- TEST 3: Recuperación de Sesión (GET /active) ---\n";
$req = Request::create('/api/v1/student/exams/active', 'GET');
$req->headers->set('Authorization', 'Bearer ' . $studentToken);
$res = $controller->active($req);
$activeData = json_decode($res->getContent(), true);

assert($activeData['success'] === true, 'Consulta de activo exitosa');
assert($activeData['data']['attempt']['id'] === $attemptId, 'Recupera el intento activo en progreso');
assert($activeData['data']['attempt']['remaining_seconds'] > 0, 'Calcula segundos restantes reales');
echo "✓ Sesión activa recuperada correctamente. Segundos restantes: " . $activeData['data']['attempt']['remaining_seconds'] . "\n\n";

// TEST 4: Guardar Respuestas y Marcas (POST /{id}/answers)
echo "--- TEST 4: Guardar Respuestas y Marcas (POST /{id}/answers) ---\n";
$firstQuestion = $startData['data']['questions'][0];
$firstOption = $firstQuestion['options'][0];

$ansPayload = [
    'question_id'            => $firstQuestion['id'],
    'selected_option_id'     => $firstOption['id'],
    'selected_option_letter' => $firstOption['option_letter'],
    'time_spent_seconds'     => 45,
    'flagged'                => true,
];
$req = Request::create("/api/v1/student/exams/{$attemptId}/answers", 'POST', $ansPayload);
$req->headers->set('Authorization', 'Bearer ' . $studentToken);
$res = $controller->saveAnswer($req, $attemptId);
$ansData = json_decode($res->getContent(), true);

assert($ansData['success'] === true, 'Respuesta guardada');
assert($ansData['data']['flagged'] === true, 'Pregunta marcada correctamente');

// Responder la segunda pregunta sin marcar
$secondQuestion = $startData['data']['questions'][1];
$secondOption = $secondQuestion['options'][1];
$req2 = Request::create("/api/v1/student/exams/{$attemptId}/answers", 'POST', [
    'question_id'            => $secondQuestion['id'],
    'selected_option_id'     => $secondOption['id'],
    'selected_option_letter' => $secondOption['option_letter'],
    'time_spent_seconds'     => 60,
    'flagged'                => false,
]);
$req2->headers->set('Authorization', 'Bearer ' . $studentToken);
$res2 = $controller->saveAnswer($req2, $attemptId);
assert(json_decode($res2->getContent(), true)['success'] === true, 'Segunda respuesta guardada');

echo "✓ Respuestas y marcas registradas y persistidas en la BD.\n\n";

// TEST 5: Control de Acceso y Aislamiento de Estudiantes
echo "--- TEST 5: Seguridad y Aislamiento de Estudiantes ---\n";
// Otro estudiante intenta acceder al simulacro de Juan
$reqUnauthorized = Request::create("/api/v1/student/exams/{$attemptId}", 'GET');
$reqUnauthorized->headers->set('Authorization', 'Bearer ' . $otherStudentToken);
$resUnauthorized = $controller->show($reqUnauthorized, $attemptId);
assert($resUnauthorized->getStatusCode() === 404, 'Estudiante ajeno no puede ver el simulacro de otro estudiante');

// Intentar guardar respuesta sin token
$reqNoAuth = Request::create("/api/v1/student/exams/{$attemptId}/answers", 'POST', $ansPayload);
$resNoAuth = $controller->saveAnswer($reqNoAuth, $attemptId);
assert($resNoAuth->getStatusCode() === 401, 'Petición sin autenticación es rechazada con 401');

echo "✓ Aislamiento y control de acceso verificado estrictamente.\n\n";

// TEST 6: Finalizar y Calificar Simulacro (POST /{id}/finish)
echo "--- TEST 6: Finalizar y Calificar 100% Backend (POST /{id}/finish) ---\n";
$reqFinish = Request::create("/api/v1/student/exams/{$attemptId}/finish", 'POST');
$reqFinish->headers->set('Authorization', 'Bearer ' . $studentToken);
$resFinish = $controller->finish($reqFinish, $attemptId);
$finishData = json_decode($resFinish->getContent(), true);

assert($finishData['success'] === true, 'Simulacro finalizado');
$attemptFinished = $finishData['data']['attempt'];
assert($attemptFinished['status'] === 'completed', 'Estado actualizado a completed');
assert(isset($attemptFinished['score']), 'Puntaje calculado por el backend');
assert(isset($attemptFinished['accuracy']), 'Precisión calculada');
assert($attemptFinished['omitted_answers'] === 8, '8 preguntas no contestadas marcadas como omitidas');
assert($attemptFinished['correct_answers'] + $attemptFinished['incorrect_answers'] === 2, '2 preguntas respondidas');

// Verificar sincronización con student_answers
$syncedAnswers = StudentAnswer::where('student_id', 1)
    ->where('practice_mode', 'simulacro')
    ->count();
assert($syncedAnswers >= 2, 'Respuestas sincronizadas en student_answers con practice_mode = simulacro');

echo "✓ Calificación oficial completada:\n";
echo "  - Puntaje: {$attemptFinished['score']} / 100\n";
echo "  - Precisión: {$attemptFinished['accuracy']}%\n";
echo "  - Correctas: {$attemptFinished['correct_answers']}\n";
echo "  - Incorrectas: {$attemptFinished['incorrect_answers']}\n";
echo "  - Omitidas: {$attemptFinished['omitted_answers']}\n";
echo "  - Sincronizadas en student_answers: {$syncedAnswers}\n\n";

// TEST 7: Revisión Detallada (GET /{id}/results)
echo "--- TEST 7: Revisión Pregunta por Pregunta (GET /{id}/results) ---\n";
$reqResults = Request::create("/api/v1/student/exams/{$attemptId}/results", 'GET');
$reqResults->headers->set('Authorization', 'Bearer ' . $studentToken);
$resResults = $controller->results($reqResults, $attemptId);
$resultsData = json_decode($resResults->getContent(), true);

assert($resultsData['success'] === true, 'Resultados consultados exitosamente');
$questionsDetail = $resultsData['data']['questions_detail'];
assert(count($questionsDetail) === 10, '10 preguntas en la revisión');

// Ahora sí cada pregunta debe incluir la justificación y la clave oficial
$firstDetail = $questionsDetail[0];
assert(!empty($firstDetail['correct_option_letter']), 'Revela la letra correcta oficial');
assert(!empty($firstDetail['explanation']), 'Revela la justificación pedagógica');
assert(isset($firstDetail['is_correct']), 'Indica si el estudiante acertó');
echo "✓ Revisión detallada validada: claves oficiales y justificaciones pedagógicas reveladas post-examen.\n\n";

// TEST 8: Historial de Simulacros (GET /history)
echo "--- TEST 8: Historial de Simulacros (GET /history) ---\n";
$reqHistory = Request::create('/api/v1/student/exams/history', 'GET');
$reqHistory->headers->set('Authorization', 'Bearer ' . $studentToken);
$resHistory = $controller->history($reqHistory);
$historyData = json_decode($resHistory->getContent(), true);

assert($historyData['success'] === true, 'Historial consultado exitosamente');
assert(count($historyData['data']) >= 1, 'Historial contiene al menos el intento recién finalizado');
$historyAttempt = collect($historyData['data'])->firstWhere('id', $attemptId);
assert($historyAttempt !== null, 'El intento finalizado aparece en el historial');
echo "✓ Historial verificado: el intento aparece con puntaje {$historyAttempt['score']} y estado finalizado.\n\n";

echo "=======================================================\n";
echo "TODAS LAS PRUEBAS E2E DEL MÓDULO DE SIMULACROS PASARON!\n";
echo "=======================================================\n";
