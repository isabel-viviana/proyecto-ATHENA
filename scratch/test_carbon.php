<?php
require_once __DIR__ . '/../athena-question-bank/vendor/autoload.php';
use Carbon\Carbon;

$attempt = new stdClass();
$attempt->expires_at = Carbon::now()->addMinutes(20);
$isInProgress = true;

$v1 = Carbon::parse($attempt->expires_at)->diffInSeconds(Carbon::now());
$v2 = Carbon::now()->diffInSeconds($attempt->expires_at, false);
$v3 = $attempt->expires_at->timestamp - Carbon::now()->timestamp;

echo "v1: " . var_export($v1, true) . "\n";
echo "v2: " . var_export($v2, true) . "\n";
echo "v3: " . var_export($v3, true) . "\n";

// Let's test with actual ExamAttempt model from DB
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dbAttempt = \App\Models\ExamAttempt::orderBy('created_at', 'desc')->first();
echo "From DB:\n";
echo "expires_at type: " . get_class($dbAttempt->expires_at) . "\n";
echo "expires_at string: " . $dbAttempt->expires_at->toIso8601String() . "\n";
echo "now string: " . now()->toIso8601String() . "\n";
$calc1 = max(0, Carbon::parse($dbAttempt->expires_at)->diffInSeconds(now()));
echo "calc1: " . var_export($calc1, true) . "\n";
$calc2 = max(0, $dbAttempt->expires_at->timestamp - now()->timestamp);
echo "calc2: " . var_export($calc2, true) . "\n";

