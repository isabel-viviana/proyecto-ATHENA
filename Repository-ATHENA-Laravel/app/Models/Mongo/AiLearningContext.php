<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class AiLearningContext extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'ai_learning_contexts';

    protected $fillable = [
        'user_id',
        'strengths',
        'weaknesses',
        'recommended_topics',
        'recent_performance',
        'recommendations',
    ];

    protected $casts = [
        'strengths' => 'array',
        'weaknesses' => 'array',
        'recommended_topics' => 'array',
        'recent_performance' => 'array',
        'recommendations' => 'array',
    ];
}
