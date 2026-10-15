<?php

namespace Database\Factories;

use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['stripe', 'paypal', 'mercado_pago']),
            'provider_transaction_id' => fake()->uuid(),
            'amount' => fake()->randomFloat(2, 4.99, 99.99),
            'currency' => 'USD',
            'status' => fake()->randomElement(['completed', 'failed', 'pending', 'refunded']),
            'paid_at' => fake()->optional()->dateTimeBetween('-10 days', 'now'),
        ];
    }
}
