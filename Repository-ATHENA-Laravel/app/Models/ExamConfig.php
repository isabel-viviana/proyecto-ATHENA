<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Models\ExamAttempt;

class ExamConfig extends Model
{
    use HasFactory;

    public const TYPES = [
        'quick',
        'full',
        'practice',
    ];

    public const DIFFICULTIES = [
        'easy',
        'medium',
        'hard',
    ];

    protected $fillable = [
        'user_id',
        'topic_id',
        'exam_type',
        'difficulty',
        'questions_count',
    ];

    protected $casts = [
        'exam_type' => 'string',
        'difficulty' => 'string',
        'questions_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'exam_config_subjects');
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
