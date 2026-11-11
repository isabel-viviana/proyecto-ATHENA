<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class IqpeDocumentJob extends Model
{
    protected $connection = 'question_bank';
    protected $table = 'iqpe_document_jobs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'source_document_id',
        'run_number',
        'status',
        'total_questions_detected',
        'total_questions_verified',
        'total_questions_discrepancy',
        'config',
        'started_at',
        'completed_at',
        'duration_seconds',
        'error_log',
    ];

    protected $casts = [
        'run_number' => 'integer',
        'total_questions_detected' => 'integer',
        'total_questions_verified' => 'integer',
        'total_questions_discrepancy' => 'integer',
        'config' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'integer',
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

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class, 'source_document_id');
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(IqpeExecutionLog::class, 'job_id')->orderBy('created_at', 'asc');
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(IqpeQuestionResolution::class, 'job_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(IqpeVerification::class, 'job_id');
    }

    public function qualityAudits(): HasMany
    {
        return $this->hasMany(IqpeQualityAudit::class, 'job_id');
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [
            'queued',
            'extracting',
            'solving_round_1',
            'solving_round_2',
            'arbitrating',
            'auditing',
        ], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}

