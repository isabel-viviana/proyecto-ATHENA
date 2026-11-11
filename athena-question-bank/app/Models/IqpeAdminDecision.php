<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IqpeAdminDecision extends Model
{
    protected $connection = 'question_bank';
    protected $table = 'iqpe_admin_decisions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'question_id',
        'job_id',
        'action',
        'previous_status',
        'new_status',
        'previous_key_status',
        'new_key_status',
        'previous_correct_letter',
        'new_correct_letter',
        'manual_key_assigned',
        'admin_id',
        'admin_name',
        'admin_email',
        'admin_role',
        'reason',
        'notes',
        'changes_diff',
        'snapshot',
        'created_at',
    ];

    protected $casts = [
        'manual_key_assigned' => 'boolean',
        'changes_diff'        => 'array',
        'snapshot'            => 'array',
        'created_at'          => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(IqpeDocumentJob::class, 'job_id');
    }
}

