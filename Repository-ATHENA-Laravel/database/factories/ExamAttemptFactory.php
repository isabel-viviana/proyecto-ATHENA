<?php

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\User;
use App\Models\ExamConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', 'now');
        $duration = fake()->numberBetween(300, 3600);
        $finishedAt = (clone $startedAt)->modify("+{$duration} seconds");
        $correct = fake()->numberBetween(0, 20);
        $incorrect = fake()->numberBetween(0, 20);
        $omitted = fake()->numberBetween(0, 10);
        $score = max(0, $correct);

        return [
            'user_id' => User::factory(),
            'exam_config_id' => ExamConfig::factory(),
            'mode' => fake()->randomElement(['quick', 'full', 'adaptive', 'practice']),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_seconds' => $duration,
            'score' => $score,
            'correct_answers' => $correct,
            'incorrect_answers' => $incorrect,
            'omitted_answers' => $omitted,
            'xp_earned' => $correct * 10,
            'coins_earned' => $correct * 5,
            'status' => fake()->randomElement(['completed', 'in_progress', 'cancelled']),
        ];
    }
}
