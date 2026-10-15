<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class AiConversation extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'ai_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'messages',
        'context',
    ];

    protected $casts = [
        'messages' => 'array',
        'context' => 'array',
    ];
}
