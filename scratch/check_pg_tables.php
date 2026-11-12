<?php
require __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::connection('question_bank')->select("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
echo "=== SUPABASE POSTGRESQL TABLES ===\n";
foreach ($tables as $t) {
    echo "- " . $t->table_name . "\n";
}

