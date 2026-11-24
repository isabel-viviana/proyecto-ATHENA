<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudentTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminAuthController extends Controller
{
    /**
     * Autentica a un administrador de ATHENA y retorna su JWT criptográfico con rol administrativo.
     *
     * POST /api/v1/admin/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $email = trim(strtolower($validated['email']));
        $password = $validated['password'];

        $adminUser = null;
        $userRecord = null;

        // 1. Intentar validar contra base de datos ATHENA Core (MySQL) si existe usuario administrativo
        try {
            $userRecord = DB::connection('athena_core')
                ->table('users')
                ->where('email', $email)
                ->first();

            if ($userRecord && ($userRecord->remember_token ?? null) === 'SUSPENDED') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuenta administrativa se encuentra suspendida temporalmente.',
                ], 403);
            }

            $rawRole = strtolower(trim((string) ($userRecord->role ?? '')));
            $normalizedRole = $rawRole === 'super_admin' ? 'superadmin' : $rawRole;

            if ($userRecord && in_array($normalizedRole, ['admin', 'superadmin', 'support', 'docente', 'coordinador'], true)) {
                if (!empty($userRecord->password) && password_verify($password, $userRecord->password)) {
                    $adminUser = [
                        'id'        => (string) $userRecord->id,
                        'name'      => $userRecord->full_name ?? $userRecord->name ?? 'Administrador ATHENA',
                        'full_name' => $userRecord->full_name ?? $userRecord->name ?? 'Administrador ATHENA',
                        'email'     => $userRecord->email,
                        'role'      => $normalizedRole,
                    ];
                }
            }
        } catch (Throwable $e) {
            // Continuar con verificación de credenciales de entorno
        }

        // 2. Verificar cuenta administradora de sistema configurada en servicios (.env)
        $configuredEmail = strtolower((string) config('services.athena.admin_email', 'admin@athena.edu.co'));
        $configuredPassword = (string) config('services.athena.admin_password', 'admin123456');

        if (! $adminUser && ! $userRecord && $email === $configuredEmail && $password === $configuredPassword) {
            $adminUser = [
                'id'    => 'admin-root',
                'name'  => 'Super Administrador ATHENA',
                'email' => $configuredEmail,
                'role'  => 'superadmin',
            ];
        }

        if (! $adminUser) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales administrativas no válidas. Acceso restringido al personal autorizado.',
            ], 401);
        }

        // 3. Generar token firmado con rol administrativo
        $token = StudentTokenService::generateToken($adminUser, ttlSeconds: 86400 * 7); // 7 días

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión administrativo exitoso.',
            'token'   => $token,
            'admin'   => $adminUser,
        ]);
    }

    /**
     * Consulta el perfil del administrador autenticado actualmente.
     *
     * GET /api/v1/admin/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->attributes->get('auth_admin');

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'No hay una sesión administrativa activa.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'admin'   => $admin,
        ]);
    }
}

