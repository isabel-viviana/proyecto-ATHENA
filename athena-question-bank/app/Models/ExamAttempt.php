<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use HasUuids;

    protected $connection = 'question_bank';
    protected $table = 'exam_attempts';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'id',
        'student_id',
        'subject_id',
        'title',
        'mode',
        'questions_count',
        'duration_minutes',
        'started_at',
        'expires_at',
        'finished_at',
        'status',
        'score',
        'correct_answers',
        'incorrect_answers',
        'omitted_answers',
        'accuracy',
        'total_time_seconds',
        'question_ids',
        'flagged_question_ids',
    ];

    protected $casts = [
        'question_ids'         => 'array',
        'flagged_question_ids' => 'array',
        'started_at'           => 'datetime',
        'expires_at'           => 'datetime',
        'finished_at'          => 'datetime',
        'accuracy'             => 'float',
        'score'                => 'integer',
        'correct_answers'      => 'integer',
        'incorrect_answers'    => 'integer',
        'omitted_answers'      => 'integer',
        'total_time_seconds'   => 'integer',
        'duration_minutes'     => 'integer',
        'questions_count'      => 'integer',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAttemptAnswer::class, 'attempt_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}

