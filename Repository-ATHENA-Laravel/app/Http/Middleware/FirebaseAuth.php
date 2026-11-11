<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\FirebaseAuthService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FirebaseAuth
{
    /**
     * Middleware que autentica al usuario directamente con un Firebase ID Token.
     * Alternativa a auth:sanctum para rutas que acepten tokens Firebase.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            Log::warning('Middleware FirebaseAuth: Petición sin token Bearer en Authorization header', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Token de autenticación no proporcionado.',
            ], 401);
        }

        $firebaseService = new FirebaseAuthService();
        $payload = $firebaseService->verifyIdToken($token);

        if (!$payload) {
            Log::warning('Middleware FirebaseAuth: Token de Firebase no superó la verificación criptográfica o expiró', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Token de Firebase inválido o expirado.',
            ], 401);
        }

        $user = User::where('firebase_uid', $payload['sub'])->first();

        if (!$user) {
            Log::warning('Middleware FirebaseAuth: Token válido pero firebase_uid no encontrado en base de datos', [
                'firebase_uid' => $payload['sub'],
                'path' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Usuario no registrado en el sistema.',
            ], 401);
        }

        // Establecer el usuario autenticado para el request
        Auth::setUser($user);

        return $next($request);
    }
}
