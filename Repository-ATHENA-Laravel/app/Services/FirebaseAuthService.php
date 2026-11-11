<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseAuthService
{
    /**
     * URL de los certificados públicos de Google para Firebase Auth.
     */
    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    /**
     * Margen de tolerancia (en segundos) para desfase horario (clock skew) entre servidores.
     * Estándar en verificación de JWTs (Google / RFC 7519).
     */
    private const CLOCK_SKEW_LEEWAY_SECONDS = 300; // 5 minutos

    /**
     * Verifica y decodifica un Firebase ID Token.
     *
     * @param  string  $idToken  El JWT emitido por Firebase Auth.
     * @return array|null  El payload decodificado o null si el token es inválido.
     */
    public function verifyIdToken(string $idToken): ?array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            Log::warning('Firebase Auth: Formato de token inválido (no contiene 3 segmentos JWT).');
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode($this->base64UrlDecode($headerB64), true);
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        $signature = $this->base64UrlDecode($signatureB64);

        if (!$header || !$payload || !$signature) {
            Log::warning('Firebase Auth: No se pudo decodificar header, payload o firma del token.', [
                'has_header' => (bool) $header,
                'has_payload' => (bool) $payload,
                'has_signature' => (bool) $signature,
            ]);
            return null;
        }

        // 1. Verificar algoritmo RS256
        if (($header['alg'] ?? '') !== 'RS256') {
            Log::warning('Firebase Auth: Algoritmo no soportado en token.', [
                'alg' => $header['alg'] ?? 'desconocido',
            ]);
            return null;
        }

        // 2. Verificar Key ID (kid)
        $kid = $header['kid'] ?? null;
        if (!$kid) {
            Log::warning('Firebase Auth: Token no contiene cabecera "kid".');
            return null;
        }

        // 3. Obtener certificados públicos de Google (cacheados)
        $certs = $this->getGoogleCerts();
        if (!isset($certs[$kid])) {
            // Intentar sin caché por si rotaron las claves
            $certs = $this->getGoogleCerts(forceRefresh: true);
            if (!isset($certs[$kid])) {
                Log::warning('Firebase Auth: Certificado no encontrado para el kid proporcionado.', [
                    'kid' => $kid,
                    'available_kids' => array_keys($certs),
                ]);
                return null;
            }
        }

        // 4. Verificar firma criptográfica con clave pública de Google
        $publicKey = openssl_pkey_get_public($certs[$kid]);
        if (!$publicKey) {
            Log::error('Firebase Auth: Clave pública de Google inválida o corrupta.', ['kid' => $kid]);
            return null;
        }

        $dataToVerify = $headerB64 . '.' . $payloadB64;
        $valid = openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($valid !== 1) {
            Log::warning('Firebase Auth: Falló la verificación de la firma criptográfica.');
            return null;
        }

        // 5. Verificar claims estándar con tolerancia de reloj (Clock Skew)
        $projectId = config('services.firebase.project_id');
        $now = time();
        $leeway = self::CLOCK_SKEW_LEEWAY_SECONDS;

        if (empty($projectId)) {
            Log::error('Firebase Auth: FIREBASE_PROJECT_ID no está configurado en services.php o .env.');
            return null;
        }

        // Expiración (con margen de tolerancia hacia atrás)
        if (($payload['exp'] ?? 0) < ($now - $leeway)) {
            Log::warning('Firebase Auth: Token expirado.', [
                'exp' => $payload['exp'] ?? 0,
                'now' => $now,
                'diff_seconds' => $now - ($payload['exp'] ?? 0),
            ]);
            return null;
        }

        // Issued At (con margen de tolerancia hacia adelante para evitar falsos positivos por desfase de reloj)
        if (($payload['iat'] ?? ($now + 1)) > ($now + $leeway)) {
            Log::warning('Firebase Auth: Token emitido en el futuro (excede margen de reloj).', [
                'iat' => $payload['iat'] ?? 0,
                'now' => $now,
                'diff_seconds' => ($payload['iat'] ?? 0) - $now,
                'leeway' => $leeway,
            ]);
            return null;
        }

        // Audience (debe coincidir con el Project ID)
        if (($payload['aud'] ?? '') !== $projectId) {
            Log::warning('Firebase Auth: Audience incorrecto en token.', [
                'expected' => $projectId,
                'got' => $payload['aud'] ?? 'N/A',
            ]);
            return null;
        }

        // Issuer (debe ser https://securetoken.google.com/{project_id})
        $expectedIssuer = "https://securetoken.google.com/{$projectId}";
        if (($payload['iss'] ?? '') !== $expectedIssuer) {
            Log::warning('Firebase Auth: Issuer incorrecto en token.', [
                'expected' => $expectedIssuer,
                'got' => $payload['iss'] ?? 'N/A',
            ]);
            return null;
        }

        // Subject (UID de Firebase)
        if (empty($payload['sub'])) {
            Log::warning('Firebase Auth: Token no contiene subject (UID de Firebase).');
            return null;
        }

        return $payload;
    }

    /**
     * Obtiene los certificados públicos de Google para verificar tokens de Firebase.
     * Se cachean durante 1 hora para reducir latencia.
     */
    private function getGoogleCerts(bool $forceRefresh = false): array
    {
        $cacheKey = 'firebase_google_certs';

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 3600, function () {
            try {
                $response = Http::timeout(10)->get(self::GOOGLE_CERTS_URL);
                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('Firebase Auth: Respuesta no exitosa al consultar certificados de Google.', [
                    'status' => $response->status(),
                ]);
                return [];
            } catch (\Exception $e) {
                Log::error('Firebase Auth: Excepción al obtener certificados de Google Firebase.', [
                    'error' => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    /**
     * Decodifica una cadena Base64Url (variante usada en JWTs).
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
