<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreItem extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'avatar',
        'theme',
        'effect',
        'booster',
    ];

    protected $fillable = [
        'name',
        'description',
        'category',
        'price_coins',
        'image_url',
        'is_active',
    ];

    protected $casts = [
        'price_coins' => 'integer',
        'is_active' => 'boolean',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
