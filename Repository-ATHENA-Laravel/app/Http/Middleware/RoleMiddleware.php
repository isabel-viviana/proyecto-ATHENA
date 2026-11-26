<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Verifica que el usuario autenticado tenga uno de los roles permitidos.
     * Reconoce 'superadmin' y 'super_admin' como variantes equivalentes del superadministrador.
     * Uso en rutas: ->middleware('role:admin,superadmin,super_admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        $allowedRoles = [];
        foreach ($roles as $roleGroup) {
            foreach (explode(',', $roleGroup) as $r) {
                $normalized = strtolower(trim($r));
                if ($normalized === '') {
                    continue;
                }
                $allowedRoles[] = $normalized;

                if ($normalized === 'superadmin' || $normalized === 'super_admin') {
                    $allowedRoles[] = 'superadmin';
                    $allowedRoles[] = 'super_admin';
                }
            }
        }

        $userRole = strtolower(trim((string) ($user->role ?? 'student')));

        if (!in_array($userRole, array_unique($allowedRoles), true)) {
            return response()->json([
                'code' => 'FORBIDDEN_ROLE',
                'message' => 'No tienes permisos para acceder a este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
