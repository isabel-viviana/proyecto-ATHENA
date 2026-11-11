<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SourceDocument;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Facades\Http;

$doc = SourceDocument::find('b352295c-6b85-480e-a635-37ed53ca134c');
$storage = app(SupabaseStorageService::class);
$bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$pdfBytes = $storage->get($bucket, $doc->storage_path);
$base64Pdf = base64_encode($pdfBytes);

$apiKey = env('GEMINI_API_KEY');
$model = 'gemini-3.5-flash';

echo "Consultando Gemini para transcripción detallada de opciones visuales y figuras (Págs 12, 27, 28, 32)...\n";

$prompt = <<<PROMPT
Analiza detalladamente las siguientes páginas del PDF de Matemáticas adjunto y proporciona la información exacta, transcrita y detallada para cada una:

1. PÁGINA 12 (Pregunta 12):
   - Transcribe detalladamente qué muestra cada una de las 4 gráficas de barras de las opciones A, B, C y D.
   - Para cada gráfica (A, B, C, D) indica: título o etiqueta, eje X (Ciudad W y Ciudad Z), eje Y (Costo en pesos o millones), y los valores/alturas exactas de cada barra de costo (compra, transporte, pago de trabajadores).

2. PÁGINA 27 (Pregunta 42):
   - Transcribe las medidas exactas de la cancha de fútbol original en el enunciado (largo y ancho en metros).
   - Para cada diseño de las opciones A, B, C y D, indica las medidas exactas que aparecen rotuladas en la maqueta (largo y ancho, con sus unidades: cm o m).

3. PÁGINA 28 (Pregunta 43):
   - Transcribe detalladamente la función y el enunciado.
   - Para cada gráfica de las opciones A, B, C y D, describe la curva en el plano cartesiano xy: tipo de curva (parábola, recta, hipérbola), punto máximo/vértice, cortes con el eje X y con el eje Y.

4. PÁGINA 32 (Pregunta 50):
   - Transcribe todas las medidas y dimensiones rotuladas en la figura para los tres empaques:
     * Cilindro: radio o diámetro de la base, altura h.
     * Caja de base cuadrada: lado de la base, altura h.
     * Esfera: radio o diámetro.
   - Muestra las fórmulas o relaciones que permitan comparar sus volúmenes.

Responde en formato estructurado JSON o texto claro y ordenado.
PROMPT;

$res = Http::timeout(120)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
    'contents' => [
        [
            'parts' => [
                [
                    'inlineData' => [
                        'mimeType' => 'application/pdf',
                        'data'     => $base64Pdf,
                    ],
                ],
                [
                    'text' => $prompt,
                ],
            ],
        ],
    ],
]);

if ($res->successful()) {
    $out = $res->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
    echo $out . "\n";
    file_put_contents(__DIR__ . '/visual_questions_details.txt', $out);
} else {
    echo "Error: " . $res->body() . "\n";
}

