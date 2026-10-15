<?php

namespace Database\Factories;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    public function definition(): array
    {
        return [
            'session_id' => AiChatSession::factory(),
            'sender_type' => fake()->randomElement(['user', 'ai']),
            'message' => fake()->paragraph(2),
            'sent_at' => fake()->dateTimeBetween('-14 days', 'now'),
        ];
    }
}
