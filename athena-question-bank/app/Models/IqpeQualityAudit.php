<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IqpeQualityAudit extends Model
{
    protected $connection = 'question_bank';
    protected $table = 'iqpe_quality_audits';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'job_id',
        'question_id',
        'statement_integrity_score',
        'options_integrity_score',
        'media_integrity_status',
        'duplicate_risk_flag',
        'duplicate_candidate_id',
        'audit_warnings',
        'passed_quality_check',
    ];

    protected $casts = [
        'statement_integrity_score' => 'float',
        'options_integrity_score' => 'float',
        'duplicate_risk_flag' => 'boolean',
        'audit_warnings' => 'array',
        'passed_quality_check' => 'boolean',
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

