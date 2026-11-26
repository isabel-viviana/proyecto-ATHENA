<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Arr;
use LogicException;

class WompiPaymentService
{
    public function amountInCents(string $amount): int
    {
        if (! is_numeric($amount) || (float) $amount < 0) {
            throw new LogicException('El monto del plan no tiene un formato monetario válido.');
        }

        return (int) round((float) $amount * 100);
    }

    public function subscriptionReference(Subscription $subscription, int $amountInCents): string
    {
        $payload = implode(':', [
            $subscription->id,
            $subscription->user_id,
            $subscription->subscription_plan_id,
            $amountInCents,
        ]);
        $signature = hash_hmac('sha256', $payload, $this->integritySecret());

        return "ATHENA-{$subscription->id}-{$amountInCents}-{$signature}";
    }

    public function subscriptionIdFromReference(string $reference): ?int
    {
        $parts = $this->parseSubscriptionReference($reference);

        return $parts['subscription_id'] ?? null;
    }

    public function matchesSubscriptionReference(
        string $reference,
        Subscription $subscription,
        int $amountInCents,
    ): bool {
        $parts = $this->parseSubscriptionReference($reference);

        if ($parts === null
            || $parts['subscription_id'] !== $subscription->id
            || $parts['amount_in_cents'] !== $amountInCents) {
            return false;
        }

        $payload = implode(':', [
            $subscription->id,
            $subscription->user_id,
            $subscription->subscription_plan_id,
            $amountInCents,
        ]);
        $expected = hash_hmac('sha256', $payload, $this->integritySecret());

        return hash_equals($expected, $parts['signature']);
    }

    /**
     * @return array{subscription_id: int, amount_in_cents: int, signature: string}|null
     */
    private function parseSubscriptionReference(string $reference): ?array
    {
        $parts = explode('-', $reference);

        if (count($parts) === 4
            && $parts[0] === 'ATHENA'
            && ctype_digit($parts[1])
            && ctype_digit($parts[2])
            && strlen($parts[3]) === 64
            && ctype_xdigit($parts[3])) {
            return [
                'subscription_id' => (int) $parts[1],
                'amount_in_cents' => (int) $parts[2],
                'signature' => strtolower($parts[3]),
            ];
        }

        return null;
    }

    public function publicKey(): string
    {
        $publicKey = config('services.wompi.public_key');

        if (! in_array(config('services.wompi.environment'), ['test', 'sandbox'], true)
            || ! is_string($publicKey)
            || ! str_starts_with($publicKey, 'pub_test_')
        ) {
            throw new LogicException('Configura las credenciales de Wompi Colombia en sandbox antes de iniciar pagos.');
        }

        return $publicKey;
    }

    public function integritySignature(string $reference, int $amountInCents, string $currency = 'COP'): string
    {
        return hash(
            'sha256',
            $reference.$amountInCents.$currency.$this->integritySecret(),
        );
    }

    public function redirectUrl(): ?string
    {
        $url = config('services.wompi.redirect_url');

        return filled($url) ? (string) $url : null;
    }

    public function checkoutUrl(string $reference, int $amountInCents, User $user): string
    {
        $publicKey = $this->publicKey();
        $integritySignature = $this->integritySignature($reference, $amountInCents, 'COP');

        $parameters = [
            'public-key' => $publicKey,
            'currency' => 'COP',
            'amount-in-cents' => $amountInCents,
            'reference' => $reference,
            'signature:integrity' => $integritySignature,
            'customer-data:email' => $user->email,
        ];

        if ($redirectUrl = $this->redirectUrl()) {
            $parameters['redirect-url'] = $redirectUrl;
        }

        if (filled($user->full_name)) {
            $parameters['customer-data:full-name'] = $user->full_name;
        }

        return rtrim((string) config('services.wompi.checkout_url'), '/').'/?'.http_build_query($parameters);
    }

    private function integritySecret(): string
    {
        $secret = config('services.wompi.integrity_secret');

        if (! is_string($secret) || $secret === '') {
            throw new LogicException('WOMPI_INTEGRITY_SECRET no está configurado.');
        }

        return $secret;
    }

    /**
     * Validate Wompi's event checksum using the properties named by the event.
     *
     * @param  array<string, mixed>  $event
     */
    public function hasValidEventSignature(array $event): bool
    {
        $secret = config('services.wompi.events_secret');

        if (! is_string($secret) || $secret === '') {
            throw new LogicException('WOMPI_EVENTS_SECRET no está configurado.');
        }

        $properties = Arr::get($event, 'signature.properties');
        $timestamp = Arr::get($event, 'timestamp');
        $checksum = Arr::get($event, 'signature.checksum');

        if (! is_array($properties) || $properties === []
            || (! is_string($timestamp) && ! is_int($timestamp))
            || ! ctype_digit((string) $timestamp)
            || ! is_string($checksum)
            || ! preg_match('/^[a-f0-9]{64}$/i', $checksum)) {
            return false;
        }

        $values = [];

        foreach ($properties as $property) {
            if (! is_string($property)) {
                return false;
            }

            $value = Arr::get(Arr::get($event, 'data', []), $property);

            if (! is_scalar($value)) {
                return false;
            }

            $values[] = (string) $value;
        }

        $expected = hash('sha256', implode('', $values).$timestamp.$secret);

        return hash_equals($expected, strtolower($checksum));
    }
}
