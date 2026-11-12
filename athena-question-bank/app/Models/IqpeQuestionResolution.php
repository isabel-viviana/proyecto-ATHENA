<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IqpeQuestionResolution extends Model
{
    protected $connection = 'question_bank';
    protected $table = 'iqpe_question_resolutions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'job_id',
        'question_id',
        'round_number',
        'ai_model',
        'prompt_tokens',
        'completion_tokens',
        'latency_ms',
        'solved_option_letter',
        'confidence_score',
        'solution_explanation',
        'visual_interpretation',
        'status',
        'raw_metadata',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'latency_ms' => 'integer',
        'confidence_score' => 'float',
        'raw_metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(IqpeDocumentJob::class, 'job_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}

