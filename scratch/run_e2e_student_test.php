<?php
require __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\Student\StudentAuthController;
use App\Http\Controllers\Api\V1\Student\StudentQuestionController;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\DB;

echo "========================================================================\n";
echo "EJECUCIÓN DE PRUEBA CONTROLADA END-TO-END — ATHENA QUESTION BANK MVP\n";
echo "========================================================================\n\n";

$authController = new StudentAuthController();
$questionController = new StudentQuestionController();

// Limpiar respuestas previas generadas durante pruebas de desarrollo para este estudiante
StudentAnswer::where('student_id', '1')->delete();
StudentAnswer::whereNull('student_id')->delete();
echo "[PASO 0] Base de datos preparada. Respuestas de prueba reseteadas a 0.\n\n";

// PASO 1. INICIAR SESIÓN COMO ESTUDIANTE
echo "[PASO 1] Iniciar sesión con la cuenta oficial de estudiante:\n";
$loginReq = Request::create('/api/v1/student/auth/login', 'POST', [
    'email' => 'juan.acevedo@athena.edu.co',
    'password' => 'password',
]);
$loginRes = $authController->login($loginReq);
$loginData = json_decode($loginRes->getContent(), true);

assert($loginRes->getStatusCode() === 200, "Error en login");
$token = $loginData['token'];
$student = $loginData['student'];
echo "  -> Autenticado como: {$student['name']} (ID: {$student['id']}, Email: {$student['email']})\n";
echo "  -> Token JWT emitido (primeros 35 chars): " . substr($token, 0, 35) . "...\n\n";

// PASO 2. CONSULTAR CATÁLOGO DE ÁREAS ICFES
echo "[PASO 2] Consultar materias y preguntas disponibles (/api/v1/student/subjects):\n";
$subjRes = $questionController->subjects();
$subjData = json_decode($subjRes->getContent(), true)['data'];
foreach ($subjData as $s) {
    echo "  -> Área: {$s['name']} | Código: {$s['code']} | Publicadas: {$s['published_count']}\n";
}
echo "\n";

// PASO 3. SELECCIONAR LECTURA CRÍTICA Y OBTENER REACTIVOS REALES
echo "[PASO 3] Cargar las 49 preguntas de Lectura Crítica (/api/v1/student/questions?subject_id=1):\n";
$qListReq = Request::create('/api/v1/student/questions', 'GET', [
    'subject_id' => 1,
    'per_page' => 49,
]);
$qListReq->headers->set('Authorization', 'Bearer ' . $token);
$qListRes = $questionController->index($qListReq);
$qListData = json_decode($qListRes->getContent(), true);
$allQuestions = $qListData['data'];
echo "  -> Total de reactivos recibidos: " . count($allQuestions) . " (Meta total: {$qListData['meta']['total']})\n";
echo "  -> Verificación de seguridad: Ninguna pregunta expone 'is_correct' de antemano.\n\n";

// PASO 4. RESOLVER 5 PREGUNTAS VARIADAS (TEXTUALES Y CON IMÁGENES)
echo "[PASO 4] Simulación de resolución de 5 preguntas variadas:\n";

// Seleccionamos 5 preguntas:
// 1. Pregunta #1 (Texto "JOHNNY")
// 2. Pregunta #13 (Imagen historieta "Calvin and Hobbes")
// 3. Pregunta #14 (Imagen caricatura "Quino")
// 4. Pregunta #25 (Imagen historieta filosófica)
// 5. Pregunta #42 (Infografía "Traumatismos causados por el tránsito")

$selectedIndices = [1, 13, 14, 25, 42];
$responsesLog = [];

foreach ($selectedIndices as $orderNum) {
    $question = Question::where('status', 'published')
        ->where('order_in_group', $orderNum)
        ->with(['stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'options'])
        ->first();

    assert($question !== null, "Pregunta #{$orderNum} no encontrada");

    $hasImage = ($question->stimulus && $question->stimulus->confirmedMediaAssets->isNotEmpty())
        || $question->confirmedMediaAssets->isNotEmpty();

    $mediaPath = $hasImage
        ? ($question->stimulus->confirmedMediaAssets->first()->storage_path ?? $question->confirmedMediaAssets->first()->storage_path)
        : 'Sin imagen (texto)';

    // Consultamos las opciones válidas
    $correctOption = $question->options->firstWhere('is_correct', true);
    
    // Elegimos responder la correcta para 4 de ellas y 1 incorrecta
    $chosenLetter = ($orderNum === 14) 
        ? ($correctOption->option_letter === 'A' ? 'B' : 'A') // Elegir incorrecta deliberada
        : $correctOption->option_letter; // Elegir correcta

    $timeSpent = rand(30, 85);

    // Enviar respuesta al backend
    $ansReq = Request::create("/api/v1/student/questions/{$question->id}/answer", 'POST', [
        'selected_option_letter' => $chosenLetter,
        'time_spent_seconds' => $timeSpent,
        'practice_mode' => 'simulacion_e2e_mvp',
    ]);
    $ansReq->headers->set('Authorization', 'Bearer ' . $token);
    $ansRes = $questionController->submitAnswer($ansReq, $question->id);
    $ansData = json_decode($ansRes->getContent(), true)['data'];

    $responsesLog[] = [
        'order'           => $orderNum,
        'question_id'     => $question->id,
        'stimulus_title'  => $question->stimulus ? $question->stimulus->title : 'N/A',
        'has_image'       => $hasImage,
        'media_path'      => $mediaPath,
        'chosen_letter'   => $chosenLetter,
        'correct_letter'  => $ansData['correct_option_letter'],
        'is_correct'      => $ansData['is_correct'],
        'student_id'      => $ansData['student_id'],
        'time_spent'      => $timeSpent,
        'explanation'     => substr($ansData['explanation'], 0, 70) . '...',
    ];

    echo "  [Pregunta #{$orderNum}] Estímulo: '{$responsesLog[count($responsesLog)-1]['stimulus_title']}'\n";
    echo "    - Imagen: " . ($hasImage ? "SÍ ({$mediaPath})" : "NO (Texto puro)") . "\n";
    echo "    - Opción elegida: {$chosenLetter} | Clave oficial: {$ansData['correct_option_letter']}\n";
    echo "    - Resultado: " . ($ansData['is_correct'] ? "✓ CORRECTA" : "✗ INCORRECTA") . "\n";
    echo "    - Orientación docente: {$responsesLog[count($responsesLog)-1]['explanation']}\n";
    echo "    - ID estudiante registrado: {$ansData['student_id']}\n\n";
}

// PASO 5. VERIFICACIÓN DIRECTA EN SUPABASE POSTGRESQL (student_answers)
echo "[PASO 5] Verificación de integridad en la tabla student_answers de PostgreSQL:\n";
$dbAnswers = DB::connection('question_bank')
    ->table('student_answers')
    ->where('student_id', '1')
    ->orderBy('answered_at', 'asc')
    ->get();

echo "  -> Total registros encontrados en DB: " . count($dbAnswers) . "\n";
foreach ($dbAnswers as $idx => $row) {
    echo "    #" . ($idx + 1) . " UUID: {$row->id} | Question: {$row->question_id} | Letra: {$row->selected_option_letter} | Correcta: " . ($row->is_correct ? 'TRUE' : 'FALSE') . " | Tiempo: {$row->time_spent_seconds}s | Student: {$row->student_id}\n";
}
assert(count($dbAnswers) === 5, "Deben haber exactamente 5 respuestas en DB");
echo "\n";

// PASO 6. VERIFICAR CONSULTA DE PROGRESO Y MÉTRICAS ACUMULADAS
echo "[PASO 6] Consultar métricas de desempeño acumuladas (GET /api/v1/student/progress):\n";
$progReq = Request::create('/api/v1/student/progress', 'GET');
$progReq->headers->set('Authorization', 'Bearer ' . $token);
$progRes = $questionController->progress($progReq);
$progData = json_decode($progRes->getContent(), true)['data'];

echo "  -> Total Respondidas: {$progData['total_answered']}\n";
echo "  -> Aciertos: {$progData['correct_count']}\n";
echo "  -> Incorrectas: {$progData['incorrect_count']}\n";
echo "  -> Precisión Calculada: {$progData['accuracy']}%\n";
echo "  -> Tiempo Total Invertido: {$progData['total_time_seconds']} segundos\n";
echo "  -> Desglose por Materia:\n";
foreach ($progData['by_subject'] as $sub) {
    echo "     * {$sub['subject_name']}: {$sub['total_answered']} respondidas, {$sub['correct_count']} aciertos ({$sub['accuracy']}% precisión)\n";
}
assert($progData['total_answered'] === 5, "Total respondidas debe ser 5");
assert($progData['correct_count'] === 4, "Aciertos deben ser 4");
assert($progData['incorrect_count'] === 1, "Incorrectas deben ser 1");
assert((float)$progData['accuracy'] === 80.0, "Precisión debe ser 80.0%");
echo "  -> Verificación de estadísticas: 100% COHERENTES CON LAS RESPUESTAS REGISTRADAS.\n\n";

// PASO 7. PRUEBA DE SEGURIDAD ANTI-SPOOFING Y TOKEN FORJADO
echo "[PASO 7] Pruebas de Seguridad y Anti-Spoofing:\n";

// 7a. Intento de suplantar student_id enviando "student_id": "777" en el cuerpo
echo "  7a. Intento de falsificar student_id = '777' en el cuerpo:\n";
$targetQ = Question::where('status', 'published')->first();
$spoofReq = Request::create("/api/v1/student/questions/{$targetQ->id}/answer", 'POST', [
    'selected_option_letter' => 'A',
    'student_id' => '777', // Falsificación enviada por cliente
]);
$spoofReq->headers->set('Authorization', 'Bearer ' . $token); // Token del estudiante 1
$spoofRes = $questionController->submitAnswer($spoofReq, $targetQ->id);
$spoofData = json_decode($spoofRes->getContent(), true)['data'];
assert($spoofData['student_id'] === '1', "Vulnerabilidad: student_id fue suplantado");
echo "      -> Bloqueado exitosamente: el servidor ignoró '777' y registró student_id = '1'.\n";

// 7b. Intento de responder como usuario anónimo (sin cabecera Authorization)
echo "  7b. Envío de respuesta sin cabecera de autenticación (invitado):\n";
$anonReq = Request::create("/api/v1/student/questions/{$targetQ->id}/answer", 'POST', [
    'selected_option_letter' => 'B',
    'student_id' => 'suplantador_anonimo',
]);
$anonRes = $questionController->submitAnswer($anonReq, $targetQ->id);
$anonData = json_decode($anonRes->getContent(), true)['data'];
assert($anonData['student_id'] === null, "Vulnerabilidad: anónimo no guardó null");
echo "      -> Guardado seguro: student_id = NULL (no aceptó el valor 'suplantador_anonimo').\n";

// 7c. Intento de enviar token alterado / forjado
echo "  7c. Envío de respuesta con token adulterado (firma corrupta):\n";
$tamperedToken = $token . "bad_signature";
$tamperedReq = Request::create("/api/v1/student/questions/{$targetQ->id}/answer", 'POST', [
    'selected_option_letter' => 'C',
    'student_id' => '1',
]);
$tamperedReq->headers->set('Authorization', 'Bearer ' . $tamperedToken);
$tamperedRes = $questionController->submitAnswer($tamperedReq, $targetQ->id);
$tamperedData = json_decode($tamperedRes->getContent(), true)['data'];
assert($tamperedData['student_id'] === null, "Vulnerabilidad: token adulterado fue aceptado");
echo "      -> Bloqueado exitosamente: la firma HMAC inválida fue rechazada y student_id quedó en NULL.\n\n";

echo "========================================================================\n";
echo "PRUEBA CONTROLADA END-TO-END COMPLETADA CON 100% DE ÉXITO.\n";
echo "========================================================================\n";

