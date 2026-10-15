<?php

namespace Database\Factories;

use App\Models\UserAchievement;
use App\Models\User;
use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAchievement>
 */
class UserAchievementFactory extends Factory
{
    protected $model = UserAchievement::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'achievement_id' => Achievement::factory(),
            'unlocked_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'progress' => fake()->numberBetween(0, 100),
        ];
    }
}
