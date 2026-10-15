<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\User;
use App\Models\StoreItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store_item_id' => StoreItem::factory(),
            'equipped' => fake()->boolean(20),
            'acquired_at' => fake()->dateTimeBetween('-60 days', 'now'),
        ];
    }
}
