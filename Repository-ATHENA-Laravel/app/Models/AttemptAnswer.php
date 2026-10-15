<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_attempt_id',
        'question_id',
        'order_position',
        'selected_option_id',
        'snapshot_correct_option_id',
        'snapshot_data',
        'is_correct',
        'time_spent_seconds',
        'explanation_viewed',
    ];

    protected $casts = [
        'order_position' => 'integer',
        'snapshot_data' => 'array',
        'is_correct' => 'boolean',
        'time_spent_seconds' => 'integer',
        'explanation_viewed' => 'boolean',
    ];

    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }

    public function snapshotCorrectOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'snapshot_correct_option_id');
    }
}
