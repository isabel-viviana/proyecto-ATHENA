<?php

namespace App\Http\Middleware;

use App\Services\StudentTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    /**
     * Valida que la petición cuente con autenticación administrativa legítima.
     * Soporta:
     * 1. Token JWT firmado con rol administrativo (admin, superadmin, docente).
     * 2. Llave M2M administrativa (X-Athena-Admin-Key o Bearer admin-key) para servicios internos.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Verificación de Llave M2M Administrativa
        $configuredKey = config('services.athena.admin_key');
        $headerKey = $request->header('X-Athena-Admin-Key');
        
        if (!empty($configuredKey) && !empty($headerKey) && hash_equals($configuredKey, $headerKey)) {
            $request->attributes->set('auth_admin', [
                'id'    => 'm2m-admin',
                'name'  => 'M2M Admin Service',
                'email' => config('services.athena.admin_email', 'admin@athena.edu.co'),
                'role'  => 'superadmin',
            ]);
            return $next($request);
        }

        // 2. Extracción de Token Bearer
        $token = StudentTokenService::extractTokenFromRequest($request);

        // Si el token es la llave M2M enviada en Authorization: Bearer <key>
        if (!empty($token) && !empty($configuredKey) && hash_equals($configuredKey, $token)) {
            $request->attributes->set('auth_admin', [
                'id'    => 'm2m-admin',
                'name'  => 'M2M Admin Service',
                'email' => config('services.athena.admin_email', 'admin@athena.edu.co'),
                'role'  => 'superadmin',
            ]);
            return $next($request);
        }

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado: Se requiere token de autorización administrativa en el encabezado Authorization.',
            ], 401);
        }

        // 3. Validación de firma criptográfica y expiración JWT
        $payload = StudentTokenService::validateToken($token);

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado: Token administrativo ausente, expirado o con firma criptográfica no válida.',
            ], 401);
        }

        // 4. Verificación estricta de rol administrativo
        $role = strtolower((string) ($payload['role'] ?? ''));
        $allowedRoles = ['admin', 'superadmin', 'docente', 'coordinador'];

        if (!in_array($role, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado: El usuario autenticado no posee privilegios de administración académica.',
                'role_detected' => $role,
            ], 403);
        }

        $request->attributes->set('auth_admin', $payload);

        return $next($request);
    }
}

