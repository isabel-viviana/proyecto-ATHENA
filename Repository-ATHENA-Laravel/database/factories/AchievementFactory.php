<?php

namespace Database\Factories;

use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    public function definition(): array
    {
        $category = fake()->randomElement(['study', 'exams', 'streak', 'special']);

        return [
            'title' => fake()->sentence(3, true),
            'description' => fake()->sentence(12),
            'category' => $category,
            'xp_reward' => fake()->numberBetween(50, 500),
            'coin_reward' => fake()->numberBetween(10, 200),
            'unlock_rule' => fake()->sentence(10, true),
        ];
    }
}
