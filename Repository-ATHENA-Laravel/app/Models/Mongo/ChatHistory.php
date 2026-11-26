<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class ChatHistory extends Model
{
    /**
     * Conexión explícita a MongoDB.
     * El modelo User permanece intacto en PostgreSQL.
     */
    protected $connection = 'mongodb';

    /**
     * Colección en MongoDB.
     */
    protected $collection = 'chat_histories';

    /**
     * Atributos asignables:
     * - user_id: ID del estudiante en PostgreSQL
     * - conversation_id: Identificador de la conversación
     * - role: 'user' | 'model'
     * - content: Contenido del mensaje
     */
    protected $fillable = [
        'user_id',
        'conversation_id',
        'role',
        'content',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];
}

