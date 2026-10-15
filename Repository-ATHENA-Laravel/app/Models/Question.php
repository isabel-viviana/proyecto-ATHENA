<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    public const DIFFICULTIES = [
        'easy',
        'medium',
        'hard',
    ];

    public const STATUSES = [
        'draft',
        'review',
        'published',
        'archived',
    ];

    protected $fillable = [
        'subject_id',
        'topic_id',
        'stimulus_id',
        'order_in_group',
        'statement',
        'question_image_url',
        'difficulty',
        'explanation',
        'source',
        'source_pdf',
        'source_page',
        'original_question_number',
        'status',
    ];

    protected $casts = [
        'difficulty' => 'string',
        'status' => 'string',
        'order_in_group' => 'integer',
        'source_page' => 'integer',
        'original_question_number' => 'integer',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function stimulus(): BelongsTo
    {
        return $this->belongsTo(Stimulus::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    /**
     * Scope para filtrar únicamente reactivos publicados aptos para pruebas.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
