<?php

namespace Database\Factories;

use App\Models\StoreItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreItem>
 */
class StoreItemFactory extends Factory
{
    protected $model = StoreItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word() . ' ' . fake()->randomElement(['avatar', 'theme', 'effect', 'booster']),
            'description' => fake()->sentence(12),
            'category' => fake()->randomElement(['avatar', 'theme', 'effect', 'booster']),
            'price_coins' => fake()->numberBetween(50, 2000),
            'image_url' => fake()->optional()->imageUrl(320, 240),
            'is_active' => true,
        ];
    }
}
