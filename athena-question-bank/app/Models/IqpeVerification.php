<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IqpeVerification extends Model
{
    protected $connection = 'question_bank';
    protected $table = 'iqpe_verifications';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'job_id',
        'question_id',
        'has_official_key',
        'official_key_letter',
        'resolution_1_id',
        'resolution_1_letter',
        'resolution_2_id',
        'resolution_2_letter',
        'resolution_3_id',
        'resolution_3_letter',
        'verdict',
        'recommended_status',
        'requires_admin_review',
        'is_approved_by_admin',
        'confidence_index',
        'discrepancy_details',
    ];

    protected $casts = [
        'has_official_key' => 'boolean',
        'requires_admin_review' => 'boolean',
        'is_approved_by_admin' => 'boolean',
        'confidence_index' => 'float',
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

    public function resolution1(): BelongsTo
    {
        return $this->belongsTo(IqpeQuestionResolution::class, 'resolution_1_id');
    }

    public function resolution2(): BelongsTo
    {
        return $this->belongsTo(IqpeQuestionResolution::class, 'resolution_2_id');
    }

    public function resolution3(): BelongsTo
    {
        return $this->belongsTo(IqpeQuestionResolution::class, 'resolution_3_id');
    }
}

