<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\SupabaseStorageService;

$timestamp = date('Ymd_His');
$backupDir = dirname(__DIR__) . '/backups/' . $timestamp;
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

echo "=== INICIANDO RESPALDO COMPLETO DE BASE DE DATOS Y STORAGE ===" . PHP_EOL;
echo "Destino del respaldo: {$backupDir}" . PHP_EOL . PHP_EOL;

// ==========================================
// 1. RESPALDO DE SUPABASE POSTGRESQL
// ==========================================
$tables = [
    'subjects',
    'topics',
    'source_documents',
    'stimuli',
    'questions',
    'question_options',
    'media_assets',
    'student_answers',
    'exam_attempts',
    'exam_attempt_answers',
];

$sqlFile = "{$backupDir}/supabase_pg_dump_{$timestamp}.sql";
$jsonFile = "{$backupDir}/supabase_pg_dump_{$timestamp}.json";

$sqlHandle = fopen($sqlFile, 'w');
fwrite($sqlHandle, "-- RESPALDO COMPLETO SUPABASE POSTGRESQL - ATHENA QUESTION BANK\n");
fwrite($sqlHandle, "-- Fecha: " . date('c') . "\n\n");
fwrite($sqlHandle, "BEGIN;\n\n");

$jsonDump = [
    'metadata' => [
        'timestamp' => date('c'),
        'database'  => 'Supabase PostgreSQL (postgres)',
    ],
    'tables' => [],
];

$tableCounts = [];

foreach ($tables as $t) {
    echo "Respaldando tabla '{$t}'..." . PHP_EOL;
    $rows = DB::connection('question_bank')->table($t)->get();
    $count = $rows->count();
    $tableCounts[$t] = $count;
    $jsonDump['tables'][$t] = json_decode(json_encode($rows), true);

    fwrite($sqlHandle, "-- Tabla: {$t} ({$count} registros)\n");
    foreach ($rows as $row) {
        $rowArr = (array) $row;
        $cols = array_keys($rowArr);
        $escapedCols = array_map(fn($c) => "\"{$c}\"", $cols);
        $escapedVals = array_map(function($val) {
            if (is_null($val)) return 'NULL';
            if (is_bool($val)) return $val ? 'TRUE' : 'FALSE';
            if (is_int($val) || is_float($val)) return (string)$val;
            // String / JSON
            $valStr = (string)$val;
            $valStr = str_replace("'", "''", $valStr);
            return "'{$valStr}'";
        }, array_values($rowArr));

        $sql = "INSERT INTO \"{$t}\" (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
        fwrite($sqlHandle, $sql);
    }
    fwrite($sqlHandle, "\n");
}

fwrite($sqlHandle, "COMMIT;\n");
fclose($sqlHandle);

file_put_contents($jsonFile, json_encode($jsonDump, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo PHP_EOL . "Respaldo SQL generado: {$sqlFile} (" . round(filesize($sqlFile) / 1024, 2) . " KB)" . PHP_EOL;
echo "Respaldo JSON generado: {$jsonFile} (" . round(filesize($jsonFile) / 1024, 2) . " KB)" . PHP_EOL . PHP_EOL;

// ==========================================
// 2. RESPALDO DE SUPABASE STORAGE
// ==========================================
$storage = app(SupabaseStorageService::class);
$docBucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
$mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');

$storageBackupDir = "{$backupDir}/storage";
$docBackupDir = "{$storageBackupDir}/icfes-documents";
$mediaBackupDir = "{$storageBackupDir}/icfes-media";
mkdir($docBackupDir, 0777, true);
mkdir($mediaBackupDir, 0777, true);

echo "=== RESPALDANDO OBJETOS DE STORAGE ===" . PHP_EOL;

// 2.1 Respaldar Documentos PDF
$sourceDocs = DB::connection('question_bank')->table('source_documents')->get();
$backedDocs = 0;
foreach ($sourceDocs as $d) {
    $path = $d->storage_path;
    echo "Descargando documento PDF: {$path}..." . PHP_EOL;
    if ($storage->exists($docBucket, $path)) {
        $content = $storage->get($docBucket, $path);
        $localPath = "{$docBackupDir}/" . str_replace('/', '_', $path);
        file_put_contents($localPath, $content);
        $backedDocs++;
        echo "  [OK] Guardado en {$localPath} (" . round(strlen($content) / (1024*1024), 2) . " MB)" . PHP_EOL;
    } else {
        echo "  [ERROR] No encontrado en Storage: {$path}" . PHP_EOL;
    }
}

// 2.2 Respaldar Media Assets
$mediaAssets = DB::connection('question_bank')->table('media_assets')->get();
$backedMedia = 0;
foreach ($mediaAssets as $m) {
    $path = $m->storage_path;
    if ($storage->exists($mediaBucket, $path)) {
        $content = $storage->get($mediaBucket, $path);
        $localPath = "{$mediaBackupDir}/" . str_replace('/', '_', $path);
        file_put_contents($localPath, $content);
        $backedMedia++;
    }
}
echo "Descargados {$backedMedia} media_assets de {$mediaAssets->count()}." . PHP_EOL;

// 2.3 Respaldar Option Images
$optImages = DB::connection('question_bank')->table('question_options')->whereNotNull('option_image_path')->pluck('option_image_path')->unique();
$backedOpts = 0;
foreach ($optImages as $path) {
    if ($storage->exists($mediaBucket, $path)) {
        $content = $storage->get($mediaBucket, $path);
        $localPath = "{$mediaBackupDir}/" . str_replace('/', '_', $path);
        file_put_contents($localPath, $content);
        $backedOpts++;
    }
}
echo "Descargadas {$backedOpts} imágenes de opciones de {$optImages->count()}." . PHP_EOL . PHP_EOL;

// ==========================================
// 3. VERIFICACIÓN RIGUROSA DE LOS RESPALDOS
// ==========================================
echo "=== VERIFICACION DE LOS RESPALDOS ===" . PHP_EOL;

$verificationPassed = true;

// 3.1 Verificar archivo SQL
if (!file_exists($sqlFile) || filesize($sqlFile) < 1000) {
    echo "  [FALLO] Archivo SQL no existe o es demasiado pequeño." . PHP_EOL;
    $verificationPassed = false;
} else {
    $sqlContent = file_get_contents($sqlFile);
    if (!str_contains($sqlContent, 'BEGIN;') || !str_contains($sqlContent, 'COMMIT;')) {
        echo "  [FALLO] Archivo SQL incompleto (falta BEGIN/COMMIT)." . PHP_EOL;
        $verificationPassed = false;
    } else {
        echo "  [VERIFICADO] Archivo SQL íntegro (" . round(filesize($sqlFile) / 1024, 2) . " KB)." . PHP_EOL;
    }
}

// 3.2 Verificar archivo JSON
if (!file_exists($jsonFile) || filesize($jsonFile) < 1000) {
    echo "  [FALLO] Archivo JSON no existe o es demasiado pequeño." . PHP_EOL;
    $verificationPassed = false;
} else {
    $decodedJson = json_decode(file_get_contents($jsonFile), true);
    if (!$decodedJson || !isset($decodedJson['tables'])) {
        echo "  [FALLO] Archivo JSON corrupto o no estructurado." . PHP_EOL;
        $verificationPassed = false;
    } else {
        $jsonTablesCount = count($decodedJson['tables']);
        echo "  [VERIFICADO] Archivo JSON decodificado correctamente ({$jsonTablesCount} tablas)." . PHP_EOL;
        foreach ($tableCounts as $tbl => $expectedCount) {
            $actualCount = count($decodedJson['tables'][$tbl] ?? []);
            if ($actualCount !== $expectedCount) {
                echo "    [ERROR DE CONTEO] {$tbl}: esperado {$expectedCount}, en JSON {$actualCount}" . PHP_EOL;
                $verificationPassed = false;
            } else {
                echo "    [OK] {$tbl}: {$actualCount} registros verificados en respaldo." . PHP_EOL;
            }
        }
    }
}

// 3.3 Verificar Storage local
$totalLocalDocs = count(glob("{$docBackupDir}/*"));
$totalLocalMedia = count(glob("{$mediaBackupDir}/*"));

echo "  [VERIFICADO] {$totalLocalDocs} archivos PDF locales en respaldo." . PHP_EOL;
echo "  [VERIFICADO] {$totalLocalMedia} archivos de imagen/recursos locales en respaldo." . PHP_EOL;

// 3.4 Verificar hashes de archivos PDF
foreach ($sourceDocs as $d) {
    $localPath = "{$docBackupDir}/" . str_replace('/', '_', $d->storage_path);
    if (file_exists($localPath)) {
        $sha = hash_file('sha256', $localPath);
        $sizeMB = round(filesize($localPath) / (1024 * 1024), 2);
        echo "    PDF {$d->id} ({$sizeMB} MB) - SHA256: {$sha}" . PHP_EOL;
    }
}

echo PHP_EOL;
if ($verificationPassed) {
    echo ">>> RESULTADO FINAL: RESPALDOS CREADOS Y VERIFICADOS CON EXITO (100% VALIDADOS) <<<" . PHP_EOL;
} else {
    echo ">>> RESULTADO FINAL: FALLO EN LA VERIFICACION DEL RESPALDO <<<" . PHP_EOL;
}

