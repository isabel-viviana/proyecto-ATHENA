<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word() . ' Plan',
            'price' => fake()->randomFloat(2, 4.99, 29.99),
            'duration_days' => 30,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
