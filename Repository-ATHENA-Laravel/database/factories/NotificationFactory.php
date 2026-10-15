<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(5, true),
            'message' => fake()->sentence(20, true),
            'type' => fake()->randomElement(['achievement', 'reminder', 'premium', 'exam', 'ai', 'system']),
            'is_read' => fake()->boolean(40),
        ];
    }
}
