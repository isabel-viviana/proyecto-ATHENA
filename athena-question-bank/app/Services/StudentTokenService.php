<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class StudentTokenService
{
    /**
     * Obtiene la clave secreta para la firma criptográfica HMAC-SHA256.
     */
    protected static function getSecret(): string
    {
        return env('ATHENA_JWT_SECRET') ?: config('app.key') ?: 'athena_saber11_fallback_secret_2026';
    }

    /**
     * Codificación Base64 URL-Safe sin padding.
     */
    protected static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodificación Base64 URL-Safe.
     */
    protected static function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padLen = 4 - $remainder;
            $data .= str_repeat('=', $padLen);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : null;
    }

    /**
     * Genera un token JWT firmado criptográficamente para un estudiante.
     *
     * @param array{id: string|int, full_name?: string, name?: string, email: string, role?: string} $user
     * @param int $ttlSeconds Por defecto 7 días
     * @return string
     */
    public static function generateToken(array $user, int $ttlSeconds = 604800): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'sub'   => (string) ($user['id'] ?? $user['sub'] ?? '1'),
            'name'  => $user['name'] ?? $user['full_name'] ?? 'Estudiante ATHENA',
            'email' => $user['email'] ?? '',
            'role'  => $user['role'] ?? 'student',
            'iat'   => time(),
            'exp'   => time() + $ttlSeconds,
        ];

        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", self::getSecret(), true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * Valida la firma y vigencia temporal de un token JWT.
     * Retorna el payload del usuario autenticado si es válido, o null si fue alterado o expiró.
     *
     * @param string|null $token
     * @return array|null
     */
    public static function validateToken(?string $token): ?array
    {
        if (empty($token) || !is_string($token)) {
            return null;
        }

        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return self::validateSanctumToken($token);
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verificar firma HMAC-SHA256
        $expectedSignature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", self::getSecret(), true);
        $providedSignature = self::base64UrlDecode($signatureEncoded);

        if (!$providedSignature || !hash_equals($expectedSignature, $providedSignature)) {
            return self::validateSanctumToken($token);
        }

        // Decodificar payload
        $payloadJson = self::base64UrlDecode($payloadEncoded);
        if (!$payloadJson) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || !isset($payload['sub'], $payload['exp'])) {
            return null;
        }

        // Verificar expiración
        if (time() > (int) $payload['exp']) {
            return null; // Token expirado
        }

        return $payload;
    }

    /**
     * Valida tokens Sanctum emitidos por ATHENA Core (athena-backend) contra PostgreSQL (athena_core).
     */
    protected static function validateSanctumToken(string $token): ?array
    {
        try {
            $plainText = str_contains($token, '|') ? explode('|', $token, 2)[1] : $token;
            $hashed = hash('sha256', trim($plainText));

            $tokenRow = DB::connection('athena_core')
                ->table('personal_access_tokens')
                ->where('token', $hashed)
                ->first();

            if (!$tokenRow) {
                return null;
            }

            $user = DB::connection('athena_core')
                ->table('users')
                ->where('id', $tokenRow->tokenable_id)
                ->first();

            if (!$user) {
                return null;
            }

            return [
                'sub'   => (string) $user->id,
                'id'    => (string) $user->id,
                'name'  => $user->full_name ?? $user->name ?? 'Usuario ATHENA',
                'email' => $user->email ?? '',
                'role'  => $user->role ?? 'student',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Extrae el token portador (Bearer token) del encabezado HTTP Authorization.
     */
    public static function extractTokenFromRequest(Request $request): ?string
    {
        $authHeader = $request->header('Authorization') ?: $request->header('X-Athena-Token');
        if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Si se envió directamente en el header sin prefijo 'Bearer '
        if (!empty($authHeader) && str_contains($authHeader, '.')) {
            return trim($authHeader);
        }

        return null;
    }
}

