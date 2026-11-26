<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WompiPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.wompi.environment' => 'test',
            'services.wompi.public_key' => 'pub_test_example',
            'services.wompi.integrity_secret' => 'test_integrity_secret',
            'services.wompi.events_secret' => 'test_events_secret',
            'services.wompi.checkout_url' => 'https://checkout.wompi.co/p/',
            'services.wompi.redirect_url' => 'https://athena.test/payments/return',
        ]);
    }

    public function test_checkout_uses_catalog_price_and_leaves_subscription_pending(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'ATHENA Pro',
            'price' => 15000,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/checkout', ['plan_key' => 'monthly']);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $parameters = [];
        parse_str(parse_url($response->json('data.checkout_url'), PHP_URL_QUERY), $parameters);

        $this->assertSame('1500000', (string) $parameters['amount-in-cents']);
        $this->assertSame('COP', $parameters['currency']);
        $this->assertSame('pub_test_example', $parameters['public-key']);
        $this->assertSame(
            hash('sha256', $parameters['reference'].'1500000COPtest_integrity_secret'),
            $parameters['signature:integrity'],
        );

        $subscription = Subscription::query()->findOrFail($response->json('data.subscription_id'));
        $this->assertSame('pending', $subscription->status);
        $this->assertFalse($subscription->auto_renew);
    }

    public function test_quarterly_plan_is_unavailable_until_it_is_configured(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payments/plans')
            ->assertOk()
            ->assertJsonPath('data.1.plan_key', 'quarterly')
            ->assertJsonPath('data.1.price_cop', null)
            ->assertJsonPath('data.1.purchasable', false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/checkout', ['plan_key' => 'quarterly'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_key');

        $this->assertDatabaseMissing('subscriptions', ['user_id' => $user->id]);
    }

    public function test_signed_approval_activates_a_subscription_once(): void
    {
        $user = User::factory()->create();
        SubscriptionPlan::factory()->create([
            'name' => 'ATHENA Pro',
            'price' => 15000,
            'duration_days' => 30,
            'is_active' => true,
        ]);
        $checkout = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments/checkout', ['plan_key' => 'monthly'])
            ->assertCreated();
        $subscription = Subscription::query()->findOrFail($checkout->json('data.subscription_id'));
        $event = $this->transactionEvent($checkout->json('data.reference'), 'APPROVED');

        $this->postJson('/api/payments/webhook', [
            ...$event,
            'signature' => [...$event['signature'], 'checksum' => str_repeat('0', 64)],
        ])->assertForbidden();

        $this->postJson('/api/payments/webhook', $event)->assertOk();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $endDate = $subscription->end_date->toDateString();

        $this->postJson('/api/payments/webhook', $event)->assertOk();

        $this->assertSame($endDate, $subscription->fresh()->end_date->toDateString());
        $this->assertSame(1, Subscription::query()->where('user_id', $user->id)->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionEvent(string $reference, string $status): array
    {
        $transaction = [
            'id' => 'wompi-transaction-123',
            'reference' => $reference,
            'amount_in_cents' => 1500000,
            'currency' => 'COP',
            'status' => $status,
        ];
        $timestamp = 1760000000;
        $properties = ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'];
        $signedValues = array_map(
            fn (string $property) => (string) data_get(['transaction' => $transaction], $property),
            $properties,
        );

        return [
            'event' => 'transaction.updated',
            'data' => ['transaction' => $transaction],
            'environment' => 'test',
            'signature' => [
                'properties' => $properties,
                'checksum' => hash(
                    'sha256',
                    implode('', $signedValues).$timestamp.'test_events_secret',
                ),
            ],
            'timestamp' => $timestamp,
        ];
    }
}
