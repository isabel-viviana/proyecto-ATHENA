<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\FirebaseAuthService;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            Log::warning('Intento de login fallido (credenciales inválidas)', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'El correo o la contraseña son incorrectos.',
            ], 401);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        try {
            $token = $user->createToken('athena-spa')->plainTextToken;
        } catch (Throwable $e) {
            Log::error('Error emitiendo token Sanctum en login', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al generar la sesión de autenticación.',
            ], 500);
        }

        Log::info('Login tradicional exitoso', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        try {
            $token = $user->createToken('athena-spa')->plainTextToken;
        } catch (Throwable $e) {
            Log::error('Error emitiendo token Sanctum en register', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al generar la sesión tras el registro.',
            ], 500);
        }

        Log::info('Registro tradicional exitoso', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Autentica al usuario mediante un Firebase ID Token.
     * Busca o crea el usuario en la BD y devuelve un token Sanctum.
     */
    public function firebaseLogin(Request $request): JsonResponse
    {
        Log::info('Procesando autenticación con Firebase', [
            'ip' => $request->ip(),
            'origin' => $request->header('Origin'),
        ]);

        $request->validate([
            'firebase_token' => ['required', 'string'],
            'full_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $firebaseService = new FirebaseAuthService();
        $payload = $firebaseService->verifyIdToken($request->firebase_token);

        if (!$payload) {
            Log::warning('firebaseLogin rechazado: Token de Firebase inválido, expirado o con firma incorrecta', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Token de Firebase inválido o expirado.',
            ], 401);
        }

        $firebaseUid = $payload['sub'];
        $email = $payload['email'] ?? null;

        if (!$email) {
            Log::warning('firebaseLogin rechazado: Token sin email', [
                'sub' => $firebaseUid,
            ]);

            return response()->json([
                'message' => 'El token no contiene un email válido.',
            ], 422);
        }

        // Buscar usuario por firebase_uid
        $user = User::where('firebase_uid', $firebaseUid)->first();
        $isNewUser = false;

        if (!$user) {
            // Buscar por email (vincular cuenta existente)
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->update(['firebase_uid' => $firebaseUid]);
                Log::info('firebaseLogin: Cuenta existente vinculada con firebase_uid', [
                    'user_id' => $user->id,
                    'email' => $email,
                ]);
            } else {
                // Crear usuario nuevo
                $user = User::create([
                    'firebase_uid' => $firebaseUid,
                    'email' => $email,
                    'full_name' => $request->input('full_name', $payload['name'] ?? 'Usuario'),
                    'password' => Hash::make(Str::random(32)),
                    'role' => 'user',
                ]);
                $isNewUser = true;

                Log::info('firebaseLogin: Nuevo usuario creado en base de datos', [
                    'user_id' => $user->id,
                    'email' => $email,
                ]);
            }
        }

        $user->forceFill(['last_login_at' => now()])->save();

        // Limpiar tokens obsoletos (> 1 minuto de antigüedad) para evitar condiciones de carrera
        // entre peticiones simultáneas del frontend (login explícito y listener onAuthStateChanged)
        $user->tokens()->where('created_at', '<', now()->subMinute())->delete();

        // Emisión explícita del token Sanctum
        try {
            $token = $user->createToken('athena-spa')->plainTextToken;
        } catch (Throwable $e) {
            Log::error('Fase crítica: Error emitiendo token Sanctum tras login Firebase', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error emitiendo token de sesión en el servidor.',
            ], 500);
        }

        Log::info('firebaseLogin: Token Sanctum emitido exitosamente', [
            'user_id' => $user->id,
            'is_new_user' => $isNewUser,
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $request->user()?->currentAccessToken()?->delete();

        Log::info('Usuario cerró sesión en backend', [
            'user_id' => $userId,
        ]);

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        Log::info('Endpoint /auth/me consultado con éxito', [
            'user_id' => $user?->id,
            'email' => $user?->email,
        ]);

        return response()->json(['user' => $user]);
    }
}
