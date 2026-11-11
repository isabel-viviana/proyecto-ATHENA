<?php
require __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\Student\StudentAuthController;
use App\Http\Controllers\Api\V1\Student\StudentProfileController;
use App\Http\Controllers\Api\V1\Student\StudentQuestionController;
use App\Http\Controllers\Api\V1\Student\StudentRankingController;
use App\Http\Controllers\Api\V1\Student\StudentNotificationController;
use Illuminate\Support\Facades\DB;

echo "========================================================================\n";
echo "SUITE DE PRUEBAS FUNCIONALES INTEGRALES — 4 MÓDULOS DEL ESTUDIANTE\n";
echo "========================================================================\n\n";

$authCtrl = new StudentAuthController();
$profileCtrl = new StudentProfileController();
$questionCtrl = new StudentQuestionController();
$rankingCtrl = new StudentRankingController();
$notifCtrl = new StudentNotificationController();

// 1. AUTENTICACIÓN INSTITUCIONAL
echo "[1. AUTENTICACIÓN] Iniciar sesión con Juan Sebastian Acevedo Medina:\n";
$loginReq = Request::create('/api/v1/student/auth/login', 'POST', [
    'email' => 'juan.acevedo@athena.edu.co',
    'password' => 'password',
]);
$loginRes = $authCtrl->login($loginReq);
assert($loginRes->getStatusCode() === 200, "Login falló");
$token = json_decode($loginRes->getContent(), true)['token'];
echo "  -> Token JWT generado correctamente.\n\n";

// 2. MÓDULO DE PERFIL
echo "[2. MÓDULO DE PERFIL]\n";
// 2a. Consultar perfil
$profReq = Request::create('/api/v1/student/profile', 'GET');
$profReq->headers->set('Authorization', 'Bearer ' . $token);
$profRes = $profileCtrl->show($profReq);
assert($profRes->getStatusCode() === 200, "GET /profile falló");
$profData = json_decode($profRes->getContent(), true)['data'];
echo "  2a. Perfil consultado exitosamente:\n";
echo "      - Nombre: {$profData['full_name']}\n";
echo "      - Correo institucional: {$profData['email']}\n";
echo "      - Identificación: ATHENA-EST-{$profData['id']}\n";
echo "      - Grado: {$profData['grade']}\n";
echo "      - Meta ICFES: {$profData['target_icfes_score']} pts\n";
echo "      - Estado: {$profData['status']}\n";

// 2b. Editar perfil (datos permitidos) y verificar protección de campos restringidos
echo "  2b. Actualizar datos permitidos (teléfono y meta) e intentar vulnerar campos protegidos:\n";
$updateReq = Request::create('/api/v1/student/profile', 'PUT', [
    'phone' => '3009988776',
    'target_icfes_score' => 420,
    'grade' => '11° Grado Oficial',
    // Intentos de inyección/modificación de campos restringidos:
    'id' => 999,
    'email' => 'spoofed_email@hacker.com',
    'role' => 'administrator',
    'status' => 'Inactivo',
]);
$updateReq->headers->set('Authorization', 'Bearer ' . $token);
$updateRes = $profileCtrl->update($updateReq);
assert($updateRes->getStatusCode() === 200, "PUT /profile falló");
$updated = json_decode($updateRes->getContent(), true)['data'];

assert($updated['phone'] === '3009988776', "Teléfono no actualizado");
assert((int)$updated['target_icfes_score'] === 420, "Meta no actualizada");
assert($updated['email'] === 'juan.acevedo@athena.edu.co', "VULNERABILIDAD: Email fue modificado");
assert($updated['role'] === 'student', "VULNERABILIDAD: Rol fue modificado");
assert($updated['id'] == 1, "VULNERABILIDAD: ID fue modificado");
echo "      -> Datos permitidos actualizados con éxito. Campos protegidos (email, role, id) intactos.\n";

// 2c. Probar validación de cambio de contraseña con clave errónea
$badPassReq = Request::create('/api/v1/student/profile/password', 'POST', [
    'current_password' => 'clave_falsa_999',
    'new_password' => 'nuevaclave123',
]);
$badPassReq->headers->set('Authorization', 'Bearer ' . $token);
$badPassRes = $profileCtrl->changePassword($badPassReq);
assert($badPassRes->getStatusCode() === 422, "Debe rechazar con 422 contraseña actual incorrecta");
echo "      -> Seguridad de contraseña verificada: Clave incorrecta rechazada con código 422.\n\n";

// 3. MÓDULO DE ESTADÍSTICAS
echo "[3. MÓDULO DE ESTADÍSTICAS]\n";
$statAllReq = Request::create('/api/v1/student/progress', 'GET', ['period' => 'all']);
$statAllReq->headers->set('Authorization', 'Bearer ' . $token);
$statAllRes = $questionCtrl->progress($statAllReq);
assert($statAllRes->getStatusCode() === 200, "GET /progress falló");
$stats = json_decode($statAllRes->getContent(), true)['data'];

echo "  3a. Indicadores Principales calculados en tiempo real:\n";
echo "      - Total respondidas: {$stats['summary']['total_answered']}\n";
echo "      - Aciertos: {$stats['summary']['correct_count']}\n";
echo "      - Errores: {$stats['summary']['incorrect_count']}\n";
echo "      - Efectividad global: {$stats['summary']['accuracy']}%\n";
echo "      - Tiempo invertido: {$stats['summary']['total_time_seconds']} seg\n";
echo "      - Racha real de estudio: {$stats['summary']['streak_days']} día(s)\n";

echo "  3b. Desglose oficial de las 5 Áreas del ICFES:\n";
assert(count($stats['by_subject']) === 5, "Deben estar las 5 áreas oficiales");
foreach ($stats['by_subject'] as $sub) {
    echo "      * {$sub['subject_name']}: {$sub['status']} ({$sub['published_in_bank']} en banco) | {$sub['total_answered']} respondidas ({$sub['accuracy']}%)\n";
}

echo "  3c. Filtros temporales (7d, 30d, all):\n";
$stat7Req = Request::create('/api/v1/student/progress', 'GET', ['period' => '7d']);
$stat7Req->headers->set('Authorization', 'Bearer ' . $token);
$stat7Res = $questionCtrl->progress($stat7Req);
assert($stat7Res->getStatusCode() === 200, "Filtro 7d falló");
echo "      - Filtro 7 días procesado con éxito.\n";

$stat30Req = Request::create('/api/v1/student/progress', 'GET', ['period' => '30d']);
$stat30Req->headers->set('Authorization', 'Bearer ' . $token);
$stat30Res = $questionCtrl->progress($stat30Req);
assert($stat30Res->getStatusCode() === 200, "Filtro 30d falló");
echo "      - Filtro 30 días procesado con éxito.\n";

echo "  3d. Análisis Pedagógico Institucional:\n";
echo "      - Orientación docente: {$stats['academic_analysis']['pedagogical_advice']}\n\n";

// 4. MÓDULO DE RANKING
echo "[4. MÓDULO DE RANKING]\n";
$rankReq = Request::create('/api/v1/student/ranking', 'GET');
$rankReq->headers->set('Authorization', 'Bearer ' . $token);
$rankRes = $rankingCtrl->index($rankReq);
assert($rankRes->getStatusCode() === 200, "GET /ranking falló");
$rankingData = json_decode($rankRes->getContent(), true)['data'];

echo "  4a. Participantes oficiales evaluados: {$rankingData['total_participants']}\n";
echo "  4b. Top 3 Destacado:\n";
foreach ($rankingData['top_3'] as $idx => $top) {
    echo "      #" . ($idx + 1) . " {$top['public_name']} | Puntos: {$top['score']} | Aciertos: {$top['distinct_correct']} | Efectividad: {$top['accuracy']}%\n";
}

echo "  4c. Posición personal del estudiante autenticado:\n";
$myR = $rankingData['my_rank'];
assert($myR !== null, "my_rank no debe ser nulo");
assert($myR['student_id'] == '1', "my_rank debe corresponder a Juan Sebastian");
echo "      -> Posición: #{$myR['rank']} | {$myR['full_name']} | Puntos: {$myR['score']} | Efectividad: {$myR['accuracy']}%\n";

echo "  4d. Verificación de privacidad:\n";
foreach ($rankingData['ranking'] as $item) {
    assert(!isset($item['email']), "VULNERABILIDAD: Email expuesto en ranking");
    assert(!isset($item['phone']), "VULNERABILIDAD: Teléfono expuesto en ranking");
    assert(!isset($item['password']), "VULNERABILIDAD: Password expuesto en ranking");
}
echo "      -> Privacidad confirmada: 0 datos sensibles expuestos en el ranking público.\n\n";

// 5. MÓDULO DE NOTIFICACIONES
echo "[5. MÓDULO DE NOTIFICACIONES]\n";

// Asegurar al menos una notificación de prueba para el estudiante 1
DB::connection('athena_core')->table('notifications')->updateOrInsert(
    ['id' => 101, 'user_id' => 1],
    [
        'title'      => 'Nueva Práctica ICFES disponible',
        'message'    => 'Ya puedes resolver las 49 preguntas de Lectura Crítica con recursos visuales.',
        'type'       => 'exam',
        'is_read'    => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]
);

// 5a. Conteo de no leídas
$countReq = Request::create('/api/v1/student/notifications/unread-count', 'GET');
$countReq->headers->set('Authorization', 'Bearer ' . $token);
$countRes = $notifCtrl->unreadCount($countReq);
$unreadCount = json_decode($countRes->getContent(), true)['count'];
echo "  5a. Conteo de notificaciones no leídas para badge del menú: {$unreadCount}\n";
assert($unreadCount > 0, "Debe haber al menos 1 notificación no leída");

// 5b. Listar notificaciones
$listReq = Request::create('/api/v1/student/notifications', 'GET', ['filter' => 'all']);
$listReq->headers->set('Authorization', 'Bearer ' . $token);
$listRes = $notifCtrl->index($listReq);
$notifs = json_decode($listRes->getContent(), true)['data'];
echo "  5b. Total de notificaciones recibidas: " . count($notifs) . "\n";
foreach ($notifs as $n) {
    echo "      * [ID: {$n['id']}, Tipo: {$n['type']}, Leída: " . ($n['is_read'] ? 'SÍ' : 'NO') . "] {$n['title']}\n";
}

// 5c. Marcar una como leída
echo "  5c. Marcar notificación #101 como leída:\n";
$markReq = Request::create('/api/v1/student/notifications/101/read', 'PATCH');
$markReq->headers->set('Authorization', 'Bearer ' . $token);
$markRes = $notifCtrl->markAsRead($markReq, 101);
assert($markRes->getStatusCode() === 200, "PATCH /read falló");
echo "      -> Notificación #101 marcada como leída exitosamente.\n";

// 5d. Prueba de aislamiento y seguridad (intentar modificar notificación de otro estudiante)
echo "  5d. Prueba de aislamiento entre estudiantes (intentar marcar notificación de user 2):\n";
$isolationReq = Request::create('/api/v1/student/notifications/2/read', 'PATCH');
$isolationReq->headers->set('Authorization', 'Bearer ' . $token);
$isolationRes = $notifCtrl->markAsRead($isolationReq, 2);
assert($isolationRes->getStatusCode() === 404, "VULNERABILIDAD: Debe rechazar con 404 notificaciones ajenas");
echo "      -> Aislamiento confirmado: El servidor rechazó con 404 el acceso a la notificación del usuario 2.\n\n";

echo "========================================================================\n";
echo "TODAS LAS PRUEBAS FUNCIONALES DE LOS 4 MÓDULOS FINALIZADAS CON 100% ÉXITO.\n";
echo "========================================================================\n";

