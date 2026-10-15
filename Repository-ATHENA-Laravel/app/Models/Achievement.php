<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'study',
        'exams',
        'streak',
        'special',
    ];

    protected $fillable = [
        'title',
        'description',
        'category',
        'xp_reward',
        'coin_reward',
        'unlock_rule',
    ];

    protected $casts = [
        'xp_reward' => 'integer',
        'coin_reward' => 'integer',
    ];

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }
}
