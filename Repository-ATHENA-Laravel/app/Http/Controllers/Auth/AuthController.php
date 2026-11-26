<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\FirebaseAuthService;
use Throwable;

class AuthController extends Controller
{
    /**
     * Correos maestros que obtienen automáticamente el rol de superadmin
     * única y exclusivamente al autenticarse con Firebase.
     */
    public const MASTER_ADMIN_EMAILS = [
        'thewindislikelife@gmail.com',
        'isabelparedes3012@gmail.com',
        'athenapreicfes@gmail.com',
    ];

    private const SUPERADMIN_ROLE = 'superadmin';
    private const STUDENT_ROLE = 'student';

    public static function isMasterAdminEmail(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        return in_array(strtolower(trim($email)), self::MASTER_ADMIN_EMAILS, true);
    }

    /**
     * Asciende a superadmin al usuario si su correo está en la lista maestra.
     * Uso exclusivo dentro de firebaseLogin() (proveedores verificados).
     */
    private function ensureMasterAdminRole(User $user): void
    {
        if (self::isMasterAdminEmail($user->email) && $user->role !== self::SUPERADMIN_ROLE) {
            $previousRole = $user->role;
            $user->forceFill(['role' => self::SUPERADMIN_ROLE])->save();

            Log::info('Usuario maestro ascendido automáticamente a superadmin vía Firebase', [
                'user_id' => $user->id,
                'previous_role' => $previousRole,
            ]);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', strtolower(trim($credentials['email'])))->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            Log::warning('Intento de login fallido (credenciales inválidas)', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'El correo o la contraseña son incorrectos.',
            ], 401);
        }

        // Verificar explícitamente si el usuario está bloqueado antes de emitir token
        if ($user->isBlocked()) {
            $user->tokens()->delete();

            Log::warning('Intento de login tradicional rechazado: cuenta bloqueada', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'code' => 'ACCOUNT_BLOCKED',
                'message' => 'Tu cuenta ha sido bloqueada o suspendida por un administrador. Contacta a soporte institucional.',
            ], 403);
        }

        $normalizedRole = User::normalizeRole($user->role);
        $updates = ['last_login_at' => now()];
        if ($user->role !== $normalizedRole) {
            $updates['role'] = $normalizedRole;
        }
        $user->forceFill($updates)->save();

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
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
            'role' => self::STUDENT_ROLE,
            'status' => User::STATUS_ACTIVE,
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
     * Busca o crea el usuario en la BD, verifica que no esté bloqueado y devuelve un token Sanctum.
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
        $email = isset($payload['email']) ? strtolower(trim($payload['email'])) : null;

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
                    'password' => Str::random(32),
                    'role' => self::isMasterAdminEmail($email) ? self::SUPERADMIN_ROLE : self::STUDENT_ROLE,
                    'status' => User::STATUS_ACTIVE,
                ]);
                $isNewUser = true;

                Log::info('firebaseLogin: Nuevo usuario creado en base de datos', [
                    'user_id' => $user->id,
                    'email' => $email,
                ]);
            }
        }

        // VERIFICACIÓN CRÍTICA DE SEGURIDAD: Rechazar cuenta bloqueada antes de emitir token Sanctum
        if ($user->isBlocked()) {
            $user->tokens()->delete();

            Log::warning('firebaseLogin rechazado: Usuario bloqueado en PostgreSQL', [
                'user_id' => $user->id,
                'email' => $user->email,
                'status' => $user->status,
            ]);

            return response()->json([
                'code' => 'ACCOUNT_BLOCKED',
                'message' => 'Tu cuenta ha sido bloqueada o suspendida por un administrador. Contacta a soporte institucional.',
            ], 403);
        }

        // Ascenso automático de correos maestros (cubre usuarios ya existentes)
        $this->ensureMasterAdminRole($user);
        $normalizedRole = User::normalizeRole($user->role);
        $updates = ['last_login_at' => now()];
        if ($user->role !== $normalizedRole) {
            $updates['role'] = $normalizedRole;
        }
        $user->forceFill($updates)->save();

        // Limpiar tokens obsoletos (> 1 minuto de antigüedad)
        $user->tokens()->where('created_at', '<', now()->subMinute())->delete();

        // Emisión explícita del token Sanctum
        try {
            $token = $user->createToken('athena-spa')->plainTextToken;
        } catch (Throwable $e) {
            Log::error('Fase crítica: Error emitiendo token Sanctum tras login Firebase', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
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

    /**
     * Valida que el correo exista y no esté bloqueado antes/durante el flujo de recuperación de contraseña.
     * Genera además un token de respaldo en password_resets.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No encontramos ninguna cuenta registrada con ese correo electrónico.',
            ], 404);
        }

        if ($user->isBlocked()) {
            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_BLOCKED',
                'message' => 'Tu cuenta se encuentra bloqueada. Contacta a soporte institucional.',
            ], 403);
        }

        $plainToken = Str::random(64);
        DB::table('password_resets')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]
        );

        Log::info('Solicitud de recuperación de contraseña procesada', [
            'user_id' => $user->id,
            'email' => $email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Se ha enviado el enlace de recuperación a tu correo electrónico.',
        ]);
    }

    /**
     * Sincroniza la nueva contraseña en PostgreSQL tras restablecimiento por Firebase o token de reset.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'token' => ['nullable', 'string'],
            'firebase_oob_verified' => ['nullable', 'boolean'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró una cuenta asociada a este correo.',
            ], 404);
        }

        if ($user->isBlocked()) {
            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_BLOCKED',
                'message' => 'Tu cuenta está bloqueada.',
            ], 403);
        }

        // Si no viene verificado por oobCode de Firebase, validar el token de password_resets
        if (empty($validated['firebase_oob_verified'])) {
            $resetRow = DB::table('password_resets')->where('email', $email)->first();
            if (!$resetRow || empty($validated['token']) || !Hash::check($validated['token'], $resetRow->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El enlace o código de recuperación es inválido o ha expirado.',
                ], 422);
            }
        }

        // User tiene cast 'password' => 'hashed', por lo que se asigna en texto plano sin doble Hash::make
        $user->password = $validated['password'];
        $user->save();

        DB::table('password_resets')->where('email', $email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión.',
        ]);
    }

    /**
     * Cambia la contraseña del usuario autenticado verificando su contraseña actual
     * (en PostgreSQL o mediante reautenticación verificada de Firebase) y evitando doble hasheo.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
            'firebase_token' => ['nullable', 'string'],
        ]);

        $isCurrentPasswordValid = Hash::check($validated['current_password'], $user->password);

        // Si el usuario fue creado vía Firebase Auth (donde su clave real se valida en Firebase),
        // aceptamos el ID token recién emitido tras reauthenticateWithCredential + updatePassword
        if (!$isCurrentPasswordValid && !empty($validated['firebase_token'])) {
            $firebaseService = new FirebaseAuthService();
            $payload = $firebaseService->verifyIdToken($validated['firebase_token']);
            if (
                $payload &&
                (
                    ($payload['sub'] ?? '') === $user->firebase_uid ||
                    strtolower($payload['email'] ?? '') === strtolower($user->email)
                )
            ) {
                $isCurrentPasswordValid = true;
            }
        }

        if (!$isCurrentPasswordValid) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual ingresada es incorrecta.',
            ], 422);
        }

        // El modelo User tiene 'password' => 'hashed' en $casts; asignar directo evita doble hasheo
        $user->password = $validated['new_password'];
        $user->save();

        Log::info('Contraseña actualizada exitosamente por el usuario', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente.',
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

        if ($user && $user->isBlocked()) {
            $user->tokens()->delete();

            return response()->json([
                'code' => 'ACCOUNT_BLOCKED',
                'message' => 'Tu cuenta ha sido bloqueada o suspendida por un administrador.',
            ], 403);
        }

        if ($user) {
            $normalizedRole = User::normalizeRole($user->role);
            if ($user->role !== $normalizedRole) {
                $user->forceFill(['role' => $normalizedRole])->save();
            }
        }

        return response()->json(['user' => $user]);
    }
}
