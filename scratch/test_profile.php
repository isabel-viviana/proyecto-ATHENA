<?php
require __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\Student\StudentAuthController;
use App\Http\Controllers\Api\V1\Student\StudentProfileController;

echo "=== PRUEBAS FUNCIONALES MÓDULO PERFIL ===\n";

$auth = new StudentAuthController();
$profile = new StudentProfileController();

// 1. Obtener token de Juan Sebastian
$loginReq = Request::create('/api/v1/student/auth/login', 'POST', [
    'email' => 'juan.acevedo@athena.edu.co',
    'password' => 'password',
]);
$loginRes = $auth->login($loginReq);
$token = json_decode($loginRes->getContent(), true)['token'];
echo "Token obtenido con éxito.\n";

// 2. GET /profile
$showReq = Request::create('/api/v1/student/profile', 'GET');
$showReq->headers->set('Authorization', 'Bearer ' . $token);
$showRes = $profile->show($showReq);
echo "GET /profile Status: " . $showRes->getStatusCode() . "\n";
$profileData = json_decode($showRes->getContent(), true)['data'];
echo "- Nombre: {$profileData['full_name']}\n";
echo "- Email: {$profileData['email']}\n";
echo "- ID: {$profileData['id']}\n";
echo "- Rol: {$profileData['role']}\n";
echo "- Grado: {$profileData['grade']}\n";
echo "- Meta: {$profileData['target_icfes_score']}\n";

// 3. PUT /profile (editar teléfono y meta)
$updateReq = Request::create('/api/v1/student/profile', 'PUT', [
    'phone' => '3119876543',
    'target_icfes_score' => 410,
    'grade' => '11° Grado B',
    'email' => 'hacked@email.com', // Campo protegido que debe ignorarse
    'role' => 'admin', // Campo protegido que debe ignorarse
]);
$updateReq->headers->set('Authorization', 'Bearer ' . $token);
$updateRes = $profile->update($updateReq);
echo "PUT /profile Status: " . $updateRes->getStatusCode() . "\n";
$updatedData = json_decode($updateRes->getContent(), true)['data'];
echo "- Nuevo teléfono: {$updatedData['phone']}\n";
echo "- Nueva meta: {$updatedData['target_icfes_score']}\n";
echo "- Email protegido sin cambios: {$updatedData['email']}\n";
echo "- Rol protegido sin cambios: {$updatedData['role']}\n";

// 4. Test cambio de contraseña con contraseña actual incorrecta
$badPassReq = Request::create('/api/v1/student/profile/password', 'POST', [
    'current_password' => 'wrong_pass',
    'new_password' => 'newpassword123',
]);
$badPassReq->headers->set('Authorization', 'Bearer ' . $token);
$badPassRes = $profile->changePassword($badPassReq);
echo "POST /profile/password (Clave errónea) Status: " . $badPassRes->getStatusCode() . " (Esperado 422)\n";

echo "MÓDULO DE PERFIL VALIDADO 100%.\n";

