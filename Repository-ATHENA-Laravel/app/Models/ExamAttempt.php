<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use HasFactory;

    public const MODES = [
        'quick',
        'full',
        'adaptive',
        'practice',
    ];

    public const STATUSES = [
        'in_progress',
        'completed',
        'cancelled',
    ];

    protected $fillable = [
        'user_id',
        'exam_config_id',
        'mode',
        'started_at',
        'finished_at',
        'duration_seconds',
        'score',
        'correct_answers',
        'incorrect_answers',
        'omitted_answers',
        'xp_earned',
        'coins_earned',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_seconds' => 'integer',
        'score' => 'integer',
        'correct_answers' => 'integer',
        'incorrect_answers' => 'integer',
        'omitted_answers' => 'integer',
        'xp_earned' => 'integer',
        'coins_earned' => 'integer',
        'mode' => 'string',
        'status' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function examConfig(): BelongsTo
    {
        return $this->belongsTo(ExamConfig::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
