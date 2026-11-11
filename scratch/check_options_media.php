<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SupabaseStorageService;
use App\Models\QuestionOption;

$storage = app(SupabaseStorageService::class);
$mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');

$optionsWithMedia = QuestionOption::whereNotNull('option_image_path')->get();
echo "Opciones con option_image_path: " . $optionsWithMedia->count() . PHP_EOL;
$missing = 0;
foreach ($optionsWithMedia as $opt) {
    $exists = $storage->exists($mediaBucket, $opt->option_image_path);
    if (!$exists) {
        $missing++;
        echo "  [FALTA] {$opt->option_image_path}" . PHP_EOL;
    } else {
        echo "  [OK] {$opt->option_image_path}" . PHP_EOL;
    }
}
echo "Total verificadas existentes: " . ($optionsWithMedia->count() - $missing) . " | Faltantes: {$missing}" . PHP_EOL;

