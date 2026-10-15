<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d');
        $endDate = now()->addDays(30)->format('Y-m-d');

        return [
            'user_id' => User::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => fake()->randomElement(['active', 'pending']),
            'auto_renew' => fake()->boolean(60),
        ];
    }
}
