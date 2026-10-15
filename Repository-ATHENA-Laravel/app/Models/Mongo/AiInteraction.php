<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class AiInteraction extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'ai_interactions';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'type',
        'question_id',
        'topic_id',
        'user_message',
        'ai_response',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
