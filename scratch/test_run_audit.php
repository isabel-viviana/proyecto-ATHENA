<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;
use App\Services\GeminiQuestionExtractionService;

$docId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$doc = SourceDocument::find($docId);

echo "Ejecutando runAutomatedQualityAudit para {$docId}...\n";
$service = app(GeminiQuestionExtractionService::class);
$report = $service->runAutomatedQualityAudit($doc);

echo "Resultado del informe de calidad:\n";
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

