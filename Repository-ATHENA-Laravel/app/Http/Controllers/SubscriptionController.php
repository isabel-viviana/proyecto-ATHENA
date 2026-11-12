<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubscriptionController extends Controller
{
    /**
     * Catálogo oficial de Planes ATHENA Premium en COP con soporte Wompi
     */
    public const DEFAULT_PLANS = [
        [
            'id' => 'premium_monthly',
            'name' => 'Premium Mensual',
            'slug' => 'mensual',
            'period' => 'Mensual',
            'duration_days' => 30,
            'price' => 29900.00,
            'formatted_price' => '$29.900 COP',
            'billing_text' => 'Facturación cada 30 días',
            'popular' => false,
            'savings' => null,
            'description' => 'Ideal para iniciar y probar a fondo tu preparación ICFES Saber 11.',
        ],
        [
            'id' => 'premium_quarterly',
            'name' => 'Premium Trimestral',
            'slug' => 'trimestral',
            'period' => 'Trimestral',
            'duration_days' => 90,
            'price' => 79900.00,
            'formatted_price' => '$79.900 COP',
            'billing_text' => 'Facturación cada 3 meses',
            'popular' => true,
            'savings' => 'Ahorra 11%',
            'description' => 'La duración ideal recomendada por docentes para un ciclo completo de entrenamiento.',
        ],
        [
            'id' => 'premium_annual',
            'name' => 'Premium Anual',
            'slug' => 'anual',
            'period' => 'Anual',
            'duration_days' => 365,
            'price' => 219900.00,
            'formatted_price' => '$219.900 COP',
            'billing_text' => 'Facturación anual única',
            'popular' => false,
            'savings' => 'Ahorra 38% - Mejor Valor',
            'description' => 'Preparación total garantizada durante 1 año completo hasta el día de tu examen ICFES.',
        ],
    ];

    /**
     * Retorna los planes disponibles y la configuración Wompi Sandbox
     */
    public function getPlans(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'plans' => self::DEFAULT_PLANS,
            'wompi' => [
                'sandbox' => true,
                'public_key' => env('WOMPI_PUBLIC_KEY', 'pub_test_DKop0lp8lllfzuVKFcyHJrh2LRsXTMSn'),
                'currency' => 'COP',
                'integrity_secret_present' => !empty(env('WOMPI_INTEGRITY_SECRET')),
                'test_numbers' => [
                    'nequi_approved' => '3991111111',
                    'nequi_declined' => '3992222222',
                    'nequi_error' => '3993333333',
                ],
            ],
        ]);
    }

    /**
     * Retorna la suscripción actual del usuario autenticado
     */
    public function getMySubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => true,
                'subscription' => [
                    'plan_name' => 'Premium Mensual',
                    'status' => 'active',
                    'period' => 'Mensual',
                    'price' => 29900,
                    'formatted_price' => '$29.900 COP',
                    'payment_method' => 'Nequi (Wompi Sandbox)',
                    'end_date' => Carbon::now()->addDays(25)->toDateString(),
                ],
                'transactions' => [],
            ]);
        }

        $subscription = Subscription::with('subscriptionPlan')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        $transactions = PaymentTransaction::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'subscription' => $subscription,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Registra o actualiza la suscripción tras un pago aprobado con Wompi
     */
    public function wompiCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|string',
            'transaction_id' => 'required|string',
            'reference' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'amount' => 'nullable|numeric',
        ]);

        $user = $request->user();
        $planDetails = collect(self::DEFAULT_PLANS)->firstWhere('id', $validated['plan_id'])
            ?? self::DEFAULT_PLANS[0];

        $durationDays = $planDetails['duration_days'] ?? 30;
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addDays($durationDays);

        // Si hay base de datos y usuario autenticado, persistimos
        if ($user) {
            $plan = SubscriptionPlan::firstOrCreate(
                ['name' => $planDetails['name']],
                [
                    'price' => $planDetails['price'],
                    'duration_days' => $durationDays,
                    'description' => $planDetails['description'],
                    'is_active' => true,
                ]
            );

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => 'active',
                'auto_renew' => true,
            ]);

            PaymentTransaction::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'provider' => 'wompi',
                'provider_transaction_id' => $validated['transaction_id'],
                'amount' => $planDetails['price'],
                'currency' => 'COP',
                'status' => 'completed',
                'paid_at' => Carbon::now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Suscripción activada con éxito mediante Wompi Nequi.',
            'plan' => $planDetails,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'formatted_end_date' => $endDate->locale('es')->isoFormat('D [de] MMMM, YYYY'),
            'transaction_id' => $validated['transaction_id'],
            'reference' => $validated['reference'] ?? 'ATH-WOMPI-' . time(),
        ]);
    }

    /**
     * Webhook para recibir eventos en tiempo real desde Wompi
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $data = $payload['data']['transaction'] ?? null;

        if ($data && ($data['status'] ?? null) === 'APPROVED') {
            $transactionId = $data['id'] ?? null;

            if ($transactionId) {
                PaymentTransaction::where('provider_transaction_id', $transactionId)
                    ->update([
                        'status' => 'completed',
                        'paid_at' => Carbon::now(),
                    ]);
            }
        }

        return response()->json(['status' => 'received']);
    }
}
