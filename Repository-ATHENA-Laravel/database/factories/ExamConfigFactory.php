<?php

namespace Database\Factories;

use App\Models\ExamConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamConfig>
 */
class ExamConfigFactory extends Factory
{
    protected $model = ExamConfig::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exam_type' => fake()->randomElement(['quick', 'full', 'practice']),
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
        ];
    }
}
