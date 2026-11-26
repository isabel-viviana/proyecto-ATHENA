<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\WompiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WompiWebhookController extends Controller
{
    //Recibir los eventos de pasarela de pago wompi y actualizar el estado de la subscripcion en la base de datos
    public function handle(Request $request, WompiPaymentService $wompi): JsonResponse
    {
        $event = $request->json()->all();

        if (! $wompi->hasValidEventSignature($event)) {
            return response()->json(['message' => 'Firma de evento Wompi inválida.'], 403);
        }

        $eventEnv = $event['environment'] ?? null;
        $configuredEnv = config('services.wompi.environment');
        $isSandboxMatch = in_array($eventEnv, ['test', 'sandbox'], true)
            && in_array($configuredEnv, ['test', 'sandbox'], true);

        if ($eventEnv !== $configuredEnv && ! $isSandboxMatch) {
            return response()->json(['message' => 'El evento no pertenece al entorno Wompi configurado.'], 422);
        }

        if (($event['event'] ?? null) !== 'transaction.updated') {
            return response()->json(['message' => 'Evento recibido.']);
        }

        $transactionData = data_get($event, 'data.transaction', []);

        if (! is_array($transactionData)) {
            return response()->json(['message' => 'Los datos de la transacción Wompi son inválidos.'], 422);
        }

        //Validar dtos especificos de la transaccion
        $validator = Validator::make($transactionData, [
            'id' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:255'],
            'amount_in_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'in:COP'],
            'status' => ['required', 'in:PENDING,APPROVED,DECLINED,VOIDED,ERROR'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos de la transacción Wompi son inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }


        $data = $validator->validated();
        $subscriptionId = $wompi->subscriptionIdFromReference($data['reference']);
        $subscription = $subscriptionId
            ? Subscription::query()->with('subscriptionPlan')->find($subscriptionId)
            : null;

        if (! $subscription) {
            Log::warning('Wompi notificó una referencia de pago no registrada.', [
                'reference' => $data['reference'],
                'transaction_id' => $data['id'],
            ]);

            return response()->json(['message' => 'Evento recibido.']);
        }

        if ($data['currency'] !== 'COP'
            || ! $wompi->matchesSubscriptionReference(
                $data['reference'],
                $subscription,
                (int) $data['amount_in_cents'],
            )) {
            Log::warning('Los datos del evento Wompi no coinciden con el pago registrado.', [
                'reference' => $data['reference'],
            ]);

            return response()->json(['message' => 'La referencia, el monto o la moneda no coinciden.'], 422);
        }

        //hacer la respetiva suscripcion
        DB::transaction(function () use ($subscription, $data): void {
            $lockedSubscription = Subscription::query()
                ->with('subscriptionPlan')
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            if ($lockedSubscription->status === 'active'
                || ($lockedSubscription->status === 'cancelled' && $data['status'] !== 'APPROVED')) {
                return;
            }

            if ($data['status'] === 'APPROVED') {
                $lockedSubscription->update([
                    'start_date' => now()->toDateString(),
                    'end_date' => now()->addDays($lockedSubscription->subscriptionPlan->duration_days)->toDateString(),
                    'status' => 'active',
                ]);
            } elseif (in_array($data['status'], ['DECLINED', 'VOIDED', 'ERROR'], true)
                && $lockedSubscription->status === 'pending') {
                $lockedSubscription->update(['status' => 'cancelled']);
            }

        });

        return response()->json(['message' => 'Evento recibido.']);
    }
}
