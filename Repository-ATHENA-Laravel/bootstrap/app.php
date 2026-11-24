<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'firebase.auth' => \App\Http\Middleware\FirebaseAuth::class,
        ]);

        // Asegura que toda petición autenticada en la API verifique que el usuario no esté bloqueado
        $middleware->appendToGroup('api', \App\Http\Middleware\EnsureUserIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $token = $request->bearerToken();
                Log::warning('Acceso 401 Unauthorized en ruta API protegida', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'origin' => $request->header('Origin'),
                    'ip' => $request->ip(),
                    'has_bearer' => !empty($token),
                    'token_prefix' => $token ? substr($token, 0, 8) . '...' : null,
                ]);

                return response()->json([
                    'message' => 'No autenticado. El token Sanctum es inválido, ha expirado o no fue suministrado.',
                ], 401);
            }
        });
    })->create();
