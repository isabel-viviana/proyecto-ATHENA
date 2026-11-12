<?php

require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require_once __DIR__ . '/generate_math_graphics.php';

use App\Models\SourceDocument;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\MediaAsset;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Str;

$docId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$doc = SourceDocument::find($docId);

if (!$doc) {
    die("Documento no encontrado\n");
}

$storage = app(SupabaseStorageService::class);
$mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');

echo "=== APLICANDO CORRECCIONES EN DOCUMENTO MATEMÁTICAS 2026 ===\n";

// Función auxiliar para subir imagen y registrar MediaAsset
$uploadAsset = function(string $path, string $bytes, int $page, ?string $qId = null) use ($storage, $mediaBucket, $docId): string {
    $storage->upload($mediaBucket, $path, $bytes, ['ContentType' => 'image/png']);
    
    // Registrar o actualizar MediaAsset
    MediaAsset::updateOrCreate(
        ['storage_path' => $path],
        [
            'id' => (string) Str::uuid(),
            'source_document_id' => $docId,
            'question_id' => $qId,
            'page_number' => $page,
            'width' => 460,
            'height' => 280,
            'mime_type' => 'image/png',
            'sha256' => hash('sha256', $bytes),
            'association_status' => 'confirmed',
            'associated_at' => now(),
        ]
    );
    return $path;
};

// -------------------------------------------------------------
// 1. CORRECCIÓN PREGUNTA 24 (Página 18)
// -------------------------------------------------------------
echo "1. Corrigiendo Pregunta 24...\n";
$q24 = Question::where('source_document_id', $docId)->where('order_in_group', 24)->first();
if ($q24) {
    // Generar y subir imagen de Figuras 1, 2 y 3
    $bytes24 = createGeometricSquaresDiagramPng();
    $path24 = "media/2026/b352295c/q24_geometric_squares.png";
    $uploadAsset($path24, $bytes24, 18, $q24->id);

    $stmt24 = <<<STMT
Una profesora de Matemáticas le entrega a sus estudiantes un cuadrado cuya área es 1 dm². En el cuadrado se encuentra la construcción de la Figura 1.
P, Q, R y S son los puntos medios de cada lado.
La profesora le pide a sus estudiantes que calculen el área del paralelogramo sombreado, para lo cual Eloísa razona de la siguiente manera: “Si se mueven los triángulos de las esquinas es posible obtener una figura con cuatro paralelogramos congruentes al sombreado”. En la Figura 2 se muestra la construcción hecha por Eloísa.
Eloísa concluye: “Como el área de esta figura es igual al área del cuadrado inicial, cada paralelogramo tiene un área de 1/4 dm²”.
Al ver la solución de Eloísa, la profesora le dice: “Excelente idea; usa una estrategia similar para hallar fácilmente el área del cuadrado sombreado en la Figura 3. Recuerda que el cuadrado grande tiene 1 dm² de área”.

¿Cuál es el área del cuadrado sombreado en la Figura 3?
STMT;

    $q24->update([
        'statement' => trim($stmt24),
        'question_image_path' => $path24,
        'visual_description' => 'Construcción geométrica de figuras 1, 2 y 3 sobre cuadrado de 1 dm² con puntos medios P, Q, R, S.',
        'visual_transcription' => 'Figura 1: paralelogramo sombreado central | Figura 2: 4 paralelogramos congruentes de 1/4 dm² | Figura 3: cuadrado sombreado central de área 1/5 dm²',
    ]);
    echo "   ✓ Pregunta 24 actualizada con contexto pedagógico completo e imagen.\n";
}

// -------------------------------------------------------------
// 2. CORRECCIÓN PREGUNTA 12 (Página 12)
// -------------------------------------------------------------
echo "2. Corrigiendo Pregunta 12 (4 gráficas de barras)...\n";
$q12 = Question::where('source_document_id', $docId)->where('order_in_group', 12)->first();
if ($q12) {
    $q12->update([
        'visual_description' => 'Gráficas de barras comparativas de costos por ciudad (Transporte camiones y Pago trabajadores)',
        'visual_transcription' => 'Ciudad W (7 ton fruta): 2 camiones ($300k transporte), 4 trabajadores ($720k) | Ciudad Z (10 ton fruta): 2 camiones ($360k transporte), 4 trabajadores ($800k)',
    ]);

    // Opciones A, B, C, D con imágenes y datos exactos
    $chartConfigs = [
        'A' => [
            'transW' => 150000, 'trabW' => 360000, 'transZ' => 180000, 'trabZ' => 400000, 'maxY' => 450000,
            'text' => 'Gráfica A — Ciudad W (Transporte: $ 150.000, Trabajadores: $ 360.000); Ciudad Z (Transporte: $ 180.000, Trabajadores: $ 400.000)'
        ],
        'B' => [
            'transW' => 300000, 'trabW' => 720000, 'transZ' => 360000, 'trabZ' => 800000, 'maxY' => 900000,
            'text' => 'Gráfica B — Ciudad W (Transporte: $ 300.000, Trabajadores: $ 720.000); Ciudad Z (Transporte: $ 360.000, Trabajadores: $ 800.000)'
        ],
        'C' => [
            'transW' => 300000, 'trabW' => 360000, 'transZ' => 360000, 'trabZ' => 400000, 'maxY' => 450000,
            'text' => 'Gráfica C — Ciudad W (Transporte: $ 300.000, Trabajadores: $ 360.000); Ciudad Z (Transporte: $ 360.000, Trabajadores: $ 400.000)'
        ],
        'D' => [
            'transW' => 300000, 'trabW' => 800000, 'transZ' => 300000, 'trabZ' => 720000, 'maxY' => 900000,
            'text' => 'Gráfica D — Ciudad W (Transporte: $ 300.000, Trabajadores: $ 800.000); Ciudad Z (Transporte: $ 300.000, Trabajadores: $ 720.000)'
        ],
    ];

    foreach ($chartConfigs as $letter => $cfg) {
        $bytes = createBarChartPng($letter, $cfg['transW'], $cfg['trabW'], $cfg['transZ'], $cfg['trabZ'], $cfg['maxY']);
        $path = "media/2026/b352295c/q12_chart_{$letter}.png";
        $uploadAsset($path, $bytes, 12, $q12->id);

        QuestionOption::where('question_id', $q12->id)
            ->where('option_letter', $letter)
            ->update([
                'option_text' => $cfg['text'],
                'option_image_path' => $path,
            ]);
    }
    echo "   ✓ Pregunta 12 actualizada con 4 gráficas e información exacta.\n";
}

// -------------------------------------------------------------
// 3. CORRECCIÓN PREGUNTA 42 (Página 27)
// -------------------------------------------------------------
echo "3. Corrigiendo Pregunta 42 (Cancha de fútbol y maquetas)...\n";
$q42 = Question::where('source_document_id', $docId)->where('order_in_group', 42)->first();
if ($q42) {
    // Imagen principal de la cancha original
    $origFieldBytes = createFieldDiagramPng('Original', 'Ancho: 60 m', '100 m');
    $origFieldPath = "media/2026/b352295c/q42_field_original.png";
    $uploadAsset($origFieldPath, $origFieldBytes, 27, $q42->id);

    $q42->update([
        'question_image_path' => $origFieldPath,
        'visual_description' => 'Cancha de fútbol con medidas originales: largo 100 m y ancho 60 m.',
        'visual_transcription' => 'Medidas originales: Largo = 100 m, Ancho = 60 m | Maqueta a escala 1:100 (100 veces menor): Largo = 1 m = 100 cm = 1x10² cm, Ancho = 0.6 m = 60 cm = 6x10¹ cm',
    ]);

    $fieldConfigs = [
        'A' => ['ancho' => 'Ancho: 6x10^-2 m', 'largo' => '1x10^-2 m', 'text' => 'Diseño A: Ancho = 6 × 10⁻² metros; Largo = 1 × 10⁻² metros'],
        'B' => ['ancho' => 'Ancho: 6x10^-2 cm', 'largo' => '1x10^-2 cm', 'text' => 'Diseño B: Ancho = 6 × 10⁻² centímetros; Largo = 1 × 10⁻² centímetros'],
        'C' => ['ancho' => 'Ancho: 6x10^1 m', 'largo' => '1x10^2 m', 'text' => 'Diseño C: Ancho = 6 × 10¹ metros; Largo = 1 × 10² metros'],
        'D' => ['ancho' => 'Ancho: 6x10^1 cm', 'largo' => '1x10^2 cm', 'text' => 'Diseño D: Ancho = 6 × 10¹ centímetros (60 cm); Largo = 1 × 10² centímetros (100 cm)'],
    ];

    foreach ($fieldConfigs as $letter => $cfg) {
        $bytes = createFieldDiagramPng($letter, $cfg['ancho'], $cfg['largo']);
        $path = "media/2026/b352295c/q42_design_{$letter}.png";
        $uploadAsset($path, $bytes, 27, $q42->id);

        QuestionOption::where('question_id', $q42->id)
            ->where('option_letter', $letter)
            ->update([
                'option_text' => $cfg['text'],
                'option_image_path' => $path,
            ]);
    }
    echo "   ✓ Pregunta 42 actualizada con diagrama de cancha y 4 opciones visuales.\n";
}

// -------------------------------------------------------------
// 4. CORRECCIÓN PREGUNTA 43 (Página 28)
// -------------------------------------------------------------
echo "4. Corrigiendo Pregunta 43 (Gráficas de funciones plano xy)...\n";
$q43 = Question::where('source_document_id', $docId)->where('order_in_group', 43)->first();
if ($q43) {
    $q43->update([
        'visual_description' => 'Gráficas en el plano cartesiano xy para la función de área Ax = x * f(x) = 5 (constante para x > 0)',
        'visual_transcription' => 'Función f(x) = 5/x para x > 0 | Área rectángulos: Ax = x * (5/x) = 5 (recta horizontal constante en y = 5)',
    ]);

    $funcConfigs = [
        'A' => ['desc' => 'Recta creciente desde origen (0,0)', 'text' => 'Gráfica A: Recta lineal con pendiente positiva que pasa por el origen (0,0) y el punto (2,10)'],
        'B' => ['desc' => 'Recta horizontal constante en y=5', 'text' => 'Gráfica B: Línea recta horizontal constante en y = 5 para x > 0 (paralela al eje X)'],
        'C' => ['desc' => 'Curva convexa creciente', 'text' => 'Gráfica C: Curva creciente no lineal convexa que inicia en (0,0) y pasa por (18,10)'],
        'D' => ['desc' => 'Parabola concava hacia abajo', 'text' => 'Gráfica D: Parábola cóncava hacia abajo con vértice/corte en (0,5) y corte en x = 5'],
    ];

    foreach ($funcConfigs as $letter => $cfg) {
        $bytes = createFunctionGraphPng($letter, $cfg['desc']);
        $path = "media/2026/b352295c/q43_func_{$letter}.png";
        $uploadAsset($path, $bytes, 28, $q43->id);

        QuestionOption::where('question_id', $q43->id)
            ->where('option_letter', $letter)
            ->update([
                'option_text' => $cfg['text'],
                'option_image_path' => $path,
            ]);
    }
    echo "   ✓ Pregunta 43 actualizada con 4 gráficas de funciones.\n";
}

// -------------------------------------------------------------
// 5. CORRECCIÓN PREGUNTA 50 (Página 32)
// -------------------------------------------------------------
echo "5. Corrigiendo Pregunta 50 (Empaques y dimensiones)...\n";
$q50 = Question::where('source_document_id', $docId)->where('order_in_group', 50)->first();
if ($q50) {
    $bytes50 = createPackagesDiagramPng();
    $path50 = "media/2026/b352295c/q50_packages.png";
    $uploadAsset($path50, $bytes50, 32, $q50->id);

    $stmt50 = <<<STMT
Alfonso tiene tres empaques para almacenar dulces, cuyas medidas se muestran en la figura:
1. Cilindro: altura h = 2 y radio de la base r = 3/2.
2. Caja de base cuadrada: altura h = 2 y lado de la base L = 3/2.
3. Esfera: radio r = 3/2.

¿Cuál de las siguientes afirmaciones es verdadera respecto al volumen de los tres empaques?
STMT;

    $q50->update([
        'statement' => trim($stmt50),
        'question_image_path' => $path50,
        'visual_description' => 'Figura con las tres geometrías de empaque: cilindro (r = 3/2, h = 2), caja cuadrada (L = 3/2, h = 2) y esfera (r = 3/2).',
        'visual_transcription' => 'V(cilindro) = π·(3/2)²·2 = 9π/2 ≈ 14.14 | V(caja) = (3/2)²·2 = 9/2 = 4.5 | V(esfera) = 4/3·π·(3/2)³ = 9π/2 ≈ 14.14 -> V(cilindro) > V(caja) y V(esfera) = V(cilindro)',
    ]);
    echo "   ✓ Pregunta 50 actualizada con dimensiones de empaques e imagen asociada.\n";
}

echo "\n=============================================================\n";
echo "TODAS LAS 5 PREGUNTAS OBSERVADAS HAN SIDO CORREGIDAS CON ÉXITO!\n";
echo "=============================================================\n";
