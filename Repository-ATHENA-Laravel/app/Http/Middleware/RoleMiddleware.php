<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Verifica que el usuario autenticado tenga uno de los roles permitidos.
     * Uso en rutas: ->middleware('role:admin,superadmin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        // Expandir roles si vienen separados por comas y normalizar
        $allowedRoles = [];
        foreach ($roles as $roleGroup) {
            foreach (explode(',', $roleGroup) as $r) {
                $trimmed = strtolower(trim($r));
                if ($trimmed !== '') {
                    $allowedRoles[] = $trimmed;
                }
            }
        }

        $userRole = strtolower(trim((string) ($user->role ?? 'student')));

        // Equivalencia entre superadmin y super_admin
        if (in_array('superadmin', $allowedRoles, true) && !in_array('super_admin', $allowedRoles, true)) {
            $allowedRoles[] = 'super_admin';
        }
        if (in_array('super_admin', $allowedRoles, true) && !in_array('superadmin', $allowedRoles, true)) {
            $allowedRoles[] = 'superadmin';
        }

        if (!in_array($userRole, $allowedRoles, true)) {
            return response()->json([
                'code' => 'FORBIDDEN_ROLE',
                'message' => 'No tienes permisos para acceder a este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
