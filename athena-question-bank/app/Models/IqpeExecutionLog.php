<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpeExecutionLog extends Model
{
    protected $connection = 'question_bank';
    protected $table = 'iqpe_execution_logs';
    public $timestamps = false; // Solo tiene created_at

    protected $fillable = [
        'job_id',
        'question_id',
        'stage',
        'level',
        'message',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(IqpeDocumentJob::class, 'job_id');
    }

    /**
     * Registra un evento en la traza cronológica de IQPE.
     */
    public static function record(
        string $jobId,
        string $stage,
        string $level,
        string $message,
        ?array $context = null,
        ?string $questionId = null
    ): self {
        return self::create([
            'job_id'      => $jobId,
            'question_id' => $questionId,
            'stage'       => $stage,
            'level'       => $level,
            'message'     => $message,
            'context'     => $context,
            'created_at'  => now(),
        ]);
    }
}

