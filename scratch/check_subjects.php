<?php
require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subject;

$subjects = Subject::orderBy('id', 'asc')->get();
foreach ($subjects as $s) {
    echo "ID: {$s->id} | Name: {$s->name} | Code: {$s->code}\n";
}

