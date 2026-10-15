<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory;

    public const PROVIDERS = [
        'stripe',
        'paypal',
        'mercado_pago',
    ];

    public const STATUSES = [
        'pending',
        'completed',
        'failed',
        'refunded',
    ];

    protected $fillable = [
        'user_id',
        'subscription_id',
        'provider',
        'provider_transaction_id',
        'amount',
        'currency',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'provider' => 'string',
        'status' => 'string',
        'currency' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
