<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    use HasUuids;

    protected $connection = 'question_bank';
    protected $table = 'student_answers';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'id',
        'student_id',
        'question_id',
        'selected_option_id',
        'selected_option_letter',
        'is_correct',
        'practice_mode',
        'time_spent_seconds',
        'answered_at',
    ];

    protected $casts = [
        'is_correct'         => 'boolean',
        'time_spent_seconds' => 'integer',
        'answered_at'        => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }
}

