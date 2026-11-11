<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = [
    'source_documents',
    'stimuli',
    'questions',
    'question_options',
    'media_assets',
    'student_answers',
    'exam_attempts',
    'exam_attempt_answers',
];

echo "=== AUDITORIA DE ESQUEMA SUPABASE POSTGRESQL ===" . PHP_EOL . PHP_EOL;

foreach ($tables as $t) {
    echo "==================================================" . PHP_EOL;
    echo "TABLA: {$t}" . PHP_EOL;
    echo "==================================================" . PHP_EOL;

    // Check if table exists
    $exists = DB::connection('question_bank')
        ->select("SELECT to_regclass('public.{$t}') as regclass");
    if (!$exists || empty($exists[0]->regclass)) {
        echo "  [ALERTA] La tabla '{$t}' NO EXISTE en la base de datos." . PHP_EOL . PHP_EOL;
        continue;
    }

    // Columns
    $cols = DB::connection('question_bank')
        ->select("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = '{$t}'
            ORDER BY ordinal_position
        ");

    echo "COLUMNAS (" . count($cols) . "):" . PHP_EOL;
    foreach ($cols as $c) {
        $nullStr = $c->is_nullable === 'YES' ? 'NULL' : 'NOT NULL';
        $defStr = $c->column_default ? " DEFAULT {$c->column_default}" : '';
        echo "  - {$c->column_name}: {$c->data_type} {$nullStr}{$defStr}" . PHP_EOL;
    }

    // Constraints & Keys
    $constraints = DB::connection('question_bank')
        ->select("
            SELECT tc.constraint_name, tc.constraint_type, kcu.column_name,
                   ccu.table_name AS foreign_table_name,
                   ccu.column_name AS foreign_column_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
            LEFT JOIN information_schema.constraint_column_usage AS ccu
              ON ccu.constraint_name = tc.constraint_name
              AND ccu.table_schema = tc.table_schema
            WHERE tc.table_schema = 'public' AND tc.table_name = '{$t}'
        ");

    echo "RESTRICCIONES / CLAVES (" . count($constraints) . "):" . PHP_EOL;
    foreach ($constraints as $con) {
        if ($con->constraint_type === 'FOREIGN KEY') {
            echo "  - FK {$con->constraint_name}: {$con->column_name} -> {$con->foreign_table_name}({$con->foreign_column_name})" . PHP_EOL;
        } elseif ($con->constraint_type === 'PRIMARY KEY') {
            echo "  - PK {$con->constraint_name}: {$con->column_name}" . PHP_EOL;
        } else {
            echo "  - {$con->constraint_type} {$con->constraint_name}: {$con->column_name}" . PHP_EOL;
        }
    }

    // Row count
    $cnt = DB::connection('question_bank')->table($t)->count();
    echo "TOTAL REGISTROS: {$cnt}" . PHP_EOL . PHP_EOL;
}

