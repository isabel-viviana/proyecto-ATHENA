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

    /**
     * En mongodb/laravel-mongodb v5, los arrays BSON se almacenan de forma nativa
     * sin utilizar el cast 'array' de SQL (que invoca json_decode sobre strings).
     */
    protected $casts = [
        'user_id' => 'integer',
    ];
}
