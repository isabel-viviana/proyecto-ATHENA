<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * GET /api/users
     * Lista todos los usuarios registrados en PostgreSQL para el panel administrativo.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->orderBy('id', 'asc');

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $users = $query->get()->map(function (User $u) {
            return [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'name' => $u->full_name,
                'email' => $u->email,
                'role' => $u->role ?? 'student',
                'status' => $u->isBlocked() ? 'Suspendido' : 'Activo',
                'raw_status' => $u->status ?? User::STATUS_ACTIVE,
                'grade' => $u->grade ?: '11° A',
                'target_icfes_score' => (int) ($u->target_icfes_score ?: 380),
                'targetScore' => (int) ($u->target_icfes_score ?: 380),
                'last_login_at' => $u->last_login_at ? $u->last_login_at->toIso8601String() : null,
                'created_at' => $u->created_at ? $u->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * POST /api/users
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'string', 'in:student,admin,support,superadmin'],
            'grade' => ['nullable', 'string', 'max:50'],
            'target_icfes_score' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'] ?? Str::random(16),
            'role' => $validated['role'] ?? 'student',
            'status' => User::STATUS_ACTIVE,
            'grade' => $validated['grade'] ?? '11° A',
            'target_icfes_score' => $validated['target_icfes_score'] ?? 380,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado correctamente.',
            'data' => $user,
        ], 201);
    }

    /**
     * GET /api/users/{id}
     */
    public function show($id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * PUT /api/users/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'grade' => ['nullable', 'string', 'max:50'],
            'target_icfes_score' => ['nullable', 'integer', 'min:0', 'max:500'],
            'status' => ['sometimes', 'string', 'in:active,blocked,Activo,Suspendido'],
        ]);

        if (isset($validated['status'])) {
            $isBlocking = in_array(strtolower($validated['status']), ['blocked', 'suspendido'], true);
            $validated['status'] = $isBlocking ? User::STATUS_BLOCKED : User::STATUS_ACTIVE;

            if ($isBlocking) {
                $user->tokens()->delete();
            }
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente.',
            'data' => $user,
        ]);
    }

    /**
     * DELETE /api/users/{id}
     */
    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (AuthController::isMasterAdminEmail($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una cuenta maestra de SuperAdmin.',
            ], 403);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    /**
     * PATCH /api/users/{id}/role
     */
    public function changeRole(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:student,admin,support,superadmin,super_admin'],
        ]);

        if (AuthController::isMasterAdminEmail($user->email) && $validated['role'] !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede degradar el rol de un SuperAdmin maestro.',
            ], 403);
        }

        $user->role = $validated['role'] === 'super_admin' ? 'superadmin' : $validated['role'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado correctamente.',
            'data' => $user,
        ]);
    }

    /**
     * PATCH /api/users/{id}/status
     * Bloquea o desbloquea un usuario en PostgreSQL y revoca inmediatamente todos sus tokens Sanctum.
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (AuthController::isMasterAdminEmail($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'No está permitido bloquear una cuenta maestra de SuperAdmin.',
            ], 403);
        }

        if ($request->filled('status')) {
            $requested = strtolower(trim($request->input('status')));
            $shouldBlock = in_array($requested, ['blocked', 'suspendido', 'inactive', 'inactivo'], true);
        } else {
            $shouldBlock = !$user->isBlocked();
        }

        $user->status = $shouldBlock ? User::STATUS_BLOCKED : User::STATUS_ACTIVE;
        $user->save();

        // Si el usuario fue bloqueado, revocar inmediatamente todos sus tokens de Sanctum
        if ($shouldBlock) {
            $user->tokens()->delete();

            Log::warning('Usuario bloqueado por un administrador; tokens de Sanctum revocados', [
                'blocked_user_id' => $user->id,
                'blocked_user_email' => $user->email,
                'admin_id' => $request->user()?->id,
            ]);
        } else {
            Log::info('Usuario reactivado por un administrador', [
                'reactivated_user_id' => $user->id,
                'reactivated_user_email' => $user->email,
                'admin_id' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $shouldBlock
                ? 'La cuenta del usuario ha sido bloqueada y sus sesiones activas fueron revocadas.'
                : 'La cuenta del usuario ha sido reactivada exitosamente.',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'status' => $user->isBlocked() ? 'Suspendido' : 'Activo',
                'raw_status' => $user->status,
            ],
        ]);
    }
}
