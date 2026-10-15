<?php

namespace Database\Factories;

use App\Models\AiChatSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatSession>
 */
class AiChatSessionFactory extends Factory
{
    protected $model = AiChatSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'started_at' => fake()->dateTimeBetween('-14 days', 'now'),
            'ended_at' => fake()->optional()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
