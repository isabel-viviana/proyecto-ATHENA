<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\User;
use App\Models\StoreItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        $item = StoreItem::factory()->create();

        return [
            'user_id' => User::factory(),
            'store_item_id' => $item->id,
            'coins_spent' => $item->price_coins,
        ];
    }
}
