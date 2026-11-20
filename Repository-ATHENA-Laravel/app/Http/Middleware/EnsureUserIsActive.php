<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Verifica en cada petición autenticada que el usuario no haya sido bloqueado.
     * Si está bloqueado, revoca todos sus tokens de Sanctum y devuelve HTTP 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isBlocked') && $user->isBlocked()) {
            $user->tokens()->delete();

            return response()->json([
                'code' => 'ACCOUNT_BLOCKED',
                'message' => 'Tu cuenta ha sido bloqueada o suspendida por un administrador. Contacta a soporte institucional.',
            ], 403);
        }

        return $next($request);
    }
}

