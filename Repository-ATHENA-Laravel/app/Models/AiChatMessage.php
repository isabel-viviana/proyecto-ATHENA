<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    use HasFactory;

    public const SENDER_TYPES = [
        'user',
        'ai',
    ];

    protected $fillable = [
        'session_id',
        'sender_type',
        'message',
        'sent_at',
    ];

    protected $casts = [
        'sender_type' => 'string',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }
}
