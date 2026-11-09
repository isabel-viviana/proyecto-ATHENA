<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    protected $connection = 'question_bank';
    protected $table = 'topics';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'subject_id',
        'name',
        'slug',
        'description',
        'is_verified',
        'created_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'subject_id' => 'integer',
        'is_verified' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'topic_id');
    }
}

