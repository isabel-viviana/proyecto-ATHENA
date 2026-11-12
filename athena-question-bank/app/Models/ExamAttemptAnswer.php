<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttemptAnswer extends Model
{
    use HasUuids;

    protected $connection = 'question_bank';
    protected $table = 'exam_attempt_answers';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'id',
        'attempt_id',
        'question_id',
        'order_position',
        'selected_option_id',
        'selected_option_letter',
        'is_correct',
        'time_spent_seconds',
        'flagged',
    ];

    protected $casts = [
        'is_correct'         => 'boolean',
        'time_spent_seconds' => 'integer',
        'order_position'     => 'integer',
        'flagged'            => 'boolean',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }
}

