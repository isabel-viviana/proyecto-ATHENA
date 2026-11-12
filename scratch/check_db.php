<?php
require __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Supabase PostgreSQL Tables ===\n";
$tables = DB::connection('question_bank')->select("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
foreach ($tables as $t) {
    echo "- " . $t->table_name . "\n";
}

echo "\n=== Check users table in PostgreSQL ===\n";
try {
    $users = DB::connection('question_bank')->select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name='users'");
    foreach ($users as $u) {
        echo "  " . $u->column_name . " (" . $u->data_type . ")\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

