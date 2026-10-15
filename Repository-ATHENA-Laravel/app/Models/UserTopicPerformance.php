<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTopicPerformance extends Model
{
    use HasFactory;

    protected $table = 'user_topic_performance';

    protected $fillable = [
        'user_id',
        'topic_id',
        'correct_count',
        'incorrect_count',
        'omitted_count',
        'mastery_score',
    ];

    protected $casts = [
        'correct_count' => 'integer',
        'incorrect_count' => 'integer',
        'omitted_count' => 'integer',
        'mastery_score' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
