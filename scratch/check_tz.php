<?php
require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\StudentTokenService;
use App\Http\Controllers\Api\V1\Student\StudentExamController;
use Illuminate\Http\Request;

$tokenService = app(StudentTokenService::class);
$token = $tokenService->generateToken(['sub' => 1, 'email' => 'juan.acevedo@athena.edu.co', 'name' => 'Juan']);

$controller = app(StudentExamController::class);
$req = Request::create('/api/v1/student/exams/active', 'GET');
$req->headers->set('Authorization', 'Bearer ' . $token);
$res = $controller->active($req);

echo "Response status: " . $res->getStatusCode() . "\n";
echo "Response JSON:\n" . $res->getContent() . "\n";
