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
use Throwable;

class PaymentController extends Controller
{
    private const PLANS = [
        'monthly' => [
            'storage_name' => 'ATHENA Pro',
            'display_name' => 'ATHENA Premium mensual',
            'period_label' => 'Mensual',
            'duration_days' => 30,
            'badge' => null,
            'highlight' => 'standard',
            'description' => 'Preparación intensiva mes a mes con acceso completo a simulacros y Tutor IA.',
            'features' => [
                'Simulacros ilimitados Saber 11 en todas las áreas',
                'Tutor IA ATHENA 24/7 con explicaciones paso a paso',
                'Estadísticas y analítica detallada por competencias',
                'Banco oficial de preguntas contextualizadas ICFES',
            ],
        ],
        'quarterly' => [
            'storage_name' => 'ATHENA Premium trimestral',
            'display_name' => 'ATHENA Premium trimestral',
            'period_label' => 'Trimestral',
            'duration_days' => 90,
            'badge' => 'MÁS POPULAR',
            'highlight' => 'popular',
            'description' => 'Ciclo recomendado de 90 días para consolidar puntaje global y dominar las 5 áreas.',
            'features' => [
                'Todo lo incluido en el Plan Mensual durante 90 días',
                'Entrenamiento continuo con seguimiento de progreso',
                'Simulacros completos cronometrados con percentil',
                'Prioridad en respuestas y análisis de debilidades con IA',
            ],
        ],
        'annual' => [
            'storage_name' => 'ATHENA Anual',
            'display_name' => 'ATHENA Premium anual',
            'period_label' => 'Anual',
            'duration_days' => 365,
            'badge' => 'MEJOR VALOR',
            'highlight' => 'best',
            'description' => 'Preparación total durante 365 días hasta presentar tu examen oficial ICFES Saber 11.',
            'features' => [
                'Acceso total e ilimitado durante 365 días',
                'Todas las herramientas de Tutor IA, Prácticas y Simulacros',
                'Histórico completo de evolución y proyección de puntaje',
                'Máximo ahorro por mes frente a la tarifa mensual',
            ],
        ],
    ];

    private function resolvePlanKey(?string $storageName): string
    {
        foreach (self::PLANS as $key => $def) {
            if ($def['storage_name'] === $storageName || $def['display_name'] === $storageName) {
                return $key;
            }
        }

        return 'monthly';
    }

    private function resolveDisplayName(?string $storageName): string
    {
        foreach (self::PLANS as $def) {
            if ($def['storage_name'] === $storageName) {
                return $def['display_name'];
            }
        }

        return $storageName ?: 'Plan ATHENA';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Aprobada',
            'pending' => 'Pendiente',
            'cancelled' => 'Rechazada',
            'expired' => 'Expirada',
            default => ucfirst($status),
        };
    }

    private function formatSubscription(Subscription $subscription, WompiPaymentService $wompi): array
    {
        $plan = $subscription->subscriptionPlan;
        $storageName = $plan?->name;
        $priceCop = $plan ? (float) $plan->price : 0.0;
        $amountInCents = 0;
        $reference = "ATHENA-{$subscription->id}";

        try {
            $amountInCents = $wompi->amountInCents((string) $priceCop);
            if ($amountInCents > 0) {
                $reference = $wompi->subscriptionReference($subscription, $amountInCents);
            }
        } catch (Throwable) {
            // En caso de que WOMPI_INTEGRITY_SECRET no esté definido en algún entorno aislado
        }

        return [
            'id' => $subscription->id,
            'plan_key' => $this->resolvePlanKey($storageName),
            'plan' => $storageName ?: 'Plan ATHENA',
            'plan_name' => $this->resolveDisplayName($storageName),
            'price_cop' => $priceCop,
            'amount_in_cents' => $amountInCents,
            'currency' => 'COP',
            'duration_days' => $plan?->duration_days ?? 30,
            'reference' => $reference,
            'status' => $subscription->status,
            'status_label' => $this->statusLabel((string) $subscription->status),
            'start_date' => $subscription->start_date?->toDateString(),
            'end_date' => $subscription->end_date?->toDateString(),
            'created_at' => $subscription->created_at?->toIso8601String(),
            'updated_at' => $subscription->updated_at?->toIso8601String(),
        ];
    }

    /**
     * GET /api/payments/plans
     */
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
                    'storage_name' => $definition['storage_name'],
                    'period_label' => $definition['period_label'],
                    'price_cop' => $price,
                    'duration_days' => $plan?->duration_days ?? $definition['duration_days'],
                    'badge' => $definition['badge'],
                    'highlight' => $definition['highlight'],
                    'description' => $plan?->description ?: $definition['description'],
                    'features' => $definition['features'],
                    'purchasable' => $plan !== null
                        && $plan->is_active
                        && $price !== null
                        && (float) $price > 0,
                ];
            })->values(),
        ]);
    }

    /**
     * GET /api/payments/history
     * Devuelve la suscripción activa actual y el historial real de suscripciones/pagos del usuario en PostgreSQL.
     */
    public function history(Request $request, WompiPaymentService $wompi): JsonResponse
    {
        $subscriptions = Subscription::query()
            ->with('subscriptionPlan')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $formatted = $subscriptions
            ->map(fn (Subscription $sub) => $this->formatSubscription($sub, $wompi))
            ->values();

        $activeSubscription = $formatted->first(function (array $item): bool {
            if ($item['status'] !== 'active') {
                return false;
            }
            if (empty($item['end_date'])) {
                return true;
            }
            return $item['end_date'] >= now()->toDateString();
        });

        return response()->json([
            'data' => [
                'active_subscription' => $activeSubscription,
                'latest_subscription' => $formatted->first(),
                'history' => $formatted,
            ],
        ]);
    }

    /**
     * POST /api/payments/checkout
     * Crea la suscripción en estado pending y devuelve todos los parámetros firmados
     * tanto para el Widget Oficial de Wompi como para Web Checkout (checkout_url).
     */
    public function store(Request $request, WompiPaymentService $wompi): JsonResponse
    {
        $data = $request->validate([
            'plan_key' => ['required', 'string', 'in:monthly,quarterly,annual'],
        ]);
        $definition = self::PLANS[$data['plan_key']];

        $result = DB::transaction(function () use ($data, $definition, $request, $wompi): array {
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
            $publicKey = $wompi->publicKey();
            $integritySignature = $wompi->integritySignature($reference, $amountInCents, 'COP');

            return [
                'subscription_id' => $subscription->id,
                'plan_key' => $data['plan_key'],
                'plan_name' => $definition['display_name'],
                'price_cop' => (float) $plan->price,
                'duration_days' => (int) $plan->duration_days,
                'reference' => $reference,
                'status' => $subscription->status,
                'public_key' => $publicKey,
                'currency' => 'COP',
                'amount_in_cents' => $amountInCents,
                'integrity_signature' => $integritySignature,
                'redirect_url' => $wompi->redirectUrl(),
                'checkout_url' => $checkoutUrl,
                'customer_data' => [
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'phone_number' => $user->phone,
                ],
            ];
        });

        return response()->json(['data' => $result], 201);
    }

    /**
     * GET /api/payments/subscriptions/{subscriptionId}
     */
    public function show(Request $request, int $subscriptionId, WompiPaymentService $wompi): JsonResponse
    {
        $subscription = Subscription::query()
            ->with('subscriptionPlan')
            ->where('user_id', $request->user()->id)
            ->findOrFail($subscriptionId);

        return response()->json([
            'data' => $this->formatSubscription($subscription, $wompi),
        ]);
    }
}
