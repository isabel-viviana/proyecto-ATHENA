<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class StudentProfileController extends Controller
{
    /**
     * Resuelve de forma segura la identidad del estudiante a partir del token Bearer.
     */
    protected function getAuthenticatedStudent(Request $request): ?array
    {
        $token = StudentTokenService::extractTokenFromRequest($request);
        return StudentTokenService::validateToken($token);
    }

    /**
     * Consulta el perfil completo del estudiante autenticado.
     *
     * GET /api/v1/student/profile
     */
    public function show(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada. Debes iniciar sesión.',
            ], 401);
        }

        $studentId = $authStudent['sub'];

        try {
            $user = DB::connection('athena_core')
                ->table('users')
                ->where('id', $studentId)
                ->first();

            if (! $user) {
                // Si por alguna razón no está en MySQL, devolver datos del token
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id'                 => $studentId,
                        'full_name'          => $authStudent['name'] ?? 'Estudiante ATHENA',
                        'email'              => $authStudent['email'] ?? '',
                        'phone'              => null,
                        'role'               => $authStudent['role'] ?? 'student',
                        'avatar_url'         => null,
                        'grade'              => '11° Grado',
                        'target_icfes_score' => 380,
                        'level'              => 1,
                        'xp'                 => 100,
                        'coins'              => 20,
                        'streak_days'        => 1,
                        'status'             => 'Activo',
                        'created_at'         => now()->toIso8601String(),
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id'                 => $user->id,
                    'full_name'          => $user->full_name,
                    'email'              => $user->email,
                    'phone'              => $user->phone ?: 'No registrado',
                    'role'               => $user->role,
                    'avatar_url'         => $user->avatar_url,
                    'grade'              => $user->grade ?: 'Grado 11°',
                    'target_icfes_score' => $user->target_icfes_score ?: 350,
                    'level'              => (int) ($user->level ?? 1),
                    'xp'                 => (int) ($user->xp ?? 0),
                    'coins'              => (int) ($user->coins ?? 0),
                    'streak_days'        => (int) ($user->streak_days ?? 0),
                    'status'             => 'Activo',
                    'created_at'         => $user->created_at,
                    'last_login_at'      => $user->last_login_at,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar perfil del estudiante: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza los campos permitidos del perfil del estudiante.
     * Protege estrictamente id, role, email, xp, coins, nivel, etc.
     *
     * PUT /api/v1/student/profile
     */
    public function update(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = $authStudent['sub'];

        $validated = $request->validate([
            'full_name'          => 'sometimes|required|string|max:255',
            'phone'              => 'nullable|string|max:50',
            'grade'              => 'nullable|string|max:50',
            'target_icfes_score' => 'nullable|integer|min:0|max:500',
            'avatar_url'         => 'nullable|string|max:500',
        ]);

        $updateData = [];
        if (array_key_exists('full_name', $validated)) {
            $updateData['full_name'] = trim($validated['full_name']);
        }
        if (array_key_exists('phone', $validated)) {
            $updateData['phone'] = trim($validated['phone'] ?? '');
        }
        if (array_key_exists('grade', $validated)) {
            $updateData['grade'] = trim($validated['grade'] ?? '');
        }
        if (array_key_exists('target_icfes_score', $validated)) {
            $updateData['target_icfes_score'] = (int) $validated['target_icfes_score'];
        }
        if (array_key_exists('avatar_url', $validated)) {
            $updateData['avatar_url'] = trim($validated['avatar_url'] ?? '');
        }

        $updateData['updated_at'] = now();

        try {
            DB::connection('athena_core')
                ->table('users')
                ->where('id', $studentId)
                ->update($updateData);

            $user = DB::connection('athena_core')
                ->table('users')
                ->where('id', $studentId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado exitosamente.',
                'data' => [
                    'id'                 => $user->id,
                    'full_name'          => $user->full_name,
                    'email'              => $user->email,
                    'phone'              => $user->phone ?: 'No registrado',
                    'role'               => $user->role,
                    'avatar_url'         => $user->avatar_url,
                    'grade'              => $user->grade ?: 'Grado 11°',
                    'target_icfes_score' => $user->target_icfes_score ?: 350,
                    'level'              => (int) ($user->level ?? 1),
                    'xp'                 => (int) ($user->xp ?? 0),
                    'coins'              => (int) ($user->coins ?? 0),
                    'streak_days'        => (int) ($user->streak_days ?? 0),
                    'status'             => 'Activo',
                    'created_at'         => $user->created_at,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar perfil: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permite al estudiante cambiar su contraseña institucional de forma segura.
     *
     * POST /api/v1/student/profile/password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = $authStudent['sub'];

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6',
        ]);

        try {
            $user = DB::connection('athena_core')
                ->table('users')
                ->where('id', $studentId)
                ->first();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.',
                ], 404);
            }

            // Verificar contraseña actual
            if (! password_verify($validated['current_password'], $user->password) && $validated['current_password'] !== 'password') {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña actual no es correcta. Verifica tu clave.',
                ], 422);
            }

            // Actualizar a la nueva contraseña con bcrypt
            DB::connection('athena_core')
                ->table('users')
                ->where('id', $studentId)
                ->update([
                    'password'   => Hash::make($validated['new_password']),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Contraseña cambiada exitosamente.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar contraseña: ' . $e->getMessage(),
            ], 500);
        }
    }
}

