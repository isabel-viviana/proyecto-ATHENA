<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\WompiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    private const PLANS = [
        'monthly' => [
            'storage_name' => 'ATHENA Pro',
            'display_name' => 'ATHENA Premium mensual',
            'duration_days' => 30,
        ],
        'quarterly' => [
            'storage_name' => 'ATHENA Premium trimestral',
            'display_name' => 'ATHENA Premium trimestral',
            'duration_days' => 90,
        ],
        'annual' => [
            'storage_name' => 'ATHENA Anual',
            'display_name' => 'ATHENA Premium anual',
            'duration_days' => 365,
        ],
    ];

    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->whereIn('name', array_column(self::PLANS, 'storage_name'))
            ->get()
            ->keyBy('name');

        return response()->json([
            'data' => collect(self::PLANS)->map(function (array $definition, string $key) use ($plans): array {
                $plan = $plans->get($definition['storage_name']);
                $price = $plan?->price;

                return [
                    'plan_key' => $key,
                    'name' => $definition['display_name'],
                    'price_cop' => $price,
                    'duration_days' => $plan?->duration_days ?? $definition['duration_days'],
                    'purchasable' => $plan !== null
                        && $plan->is_active
                        && $price !== null
                        && (float) $price > 0,
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, WompiPaymentService $wompi): JsonResponse
    {
        $data = $request->validate([
            'plan_key' => ['required', 'string', 'in:monthly,quarterly,annual'],
        ]);
        $definition = self::PLANS[$data['plan_key']];

        $result = DB::transaction(function () use ($definition, $request, $wompi): array {
            $plan = SubscriptionPlan::query()
                ->where('name', $definition['storage_name'])
                ->lockForUpdate()
                ->first();

            if (! $plan || ! $plan->is_active || (float) $plan->price <= 0) {
                throw ValidationException::withMessages([
                    'plan_key' => 'Este plan no está disponible para compra.',
                ]);
            }

            $amountInCents = $wompi->amountInCents((string) $plan->price);

            if ($amountInCents < 1) {
                throw ValidationException::withMessages([
                    'plan_key' => 'El plan debe tener un precio mayor a cero para aceptar pagos.',
                ]);
            }

            $user = $request->user();
            $subscription = Subscription::query()->create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => now()->toDateString(),
                'end_date' => null,
                'status' => 'pending',
                'auto_renew' => false,
            ]);

            $reference = $wompi->subscriptionReference($subscription, $amountInCents);
            $checkoutUrl = $wompi->checkoutUrl($reference, $amountInCents, $user);

            return [
                'subscription_id' => $subscription->id,
                'reference' => $reference,
                'status' => $subscription->status,
                'checkout_url' => $checkoutUrl,
            ];
        });

        return response()->json(['data' => $result], 201);
    }

    public function show(Request $request, int $subscriptionId): JsonResponse
    {
        $subscription = Subscription::query()
            ->with('subscriptionPlan')
            ->where('user_id', $request->user()->id)
            ->findOrFail($subscriptionId);

        return response()->json([
            'data' => [
                'id' => $subscription->id,
                'plan' => $subscription->subscriptionPlan->name,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
            ],
        ]);
    }
}
