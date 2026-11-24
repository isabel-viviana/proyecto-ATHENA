<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    protected function normalizeRole(?string $role): string
    {
        $clean = strtolower(trim((string) $role));
        if ($clean === 'super_admin') {
            return 'superadmin';
        }
        return $clean !== '' ? $clean : 'student';
    }

    protected function formatUser(User $u): array
    {
        $role = $this->normalizeRole($u->role);
        $isSuspended = $u->isBlocked();

        return [
            'id'                 => (int) $u->id,
            'full_name'          => $u->full_name,
            'name'               => $u->full_name,
            'email'              => $u->email,
            'phone'              => $u->phone,
            'role'               => $role,
            'grade'              => $u->grade ?: '11° A',
            'target_icfes_score' => (int) ($u->target_icfes_score ?: 350),
            'targetScore'        => (int) ($u->target_icfes_score ?: 350),
            'status'             => $isSuspended ? 'Suspendido' : 'Activo',
            'raw_status'         => $isSuspended ? User::STATUS_BLOCKED : User::STATUS_ACTIVE,
            'is_active'          => ! $isSuspended,
            'last_login_at'      => $u->last_login_at?->toIso8601String(),
            'created_at'         => $u->created_at?->toIso8601String(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $all = User::orderBy('id', 'desc')->get();

        $search = mb_strtolower(trim((string) $request->input('search', '')));
        $roleFilter = strtolower(trim((string) $request->input('role', '')));
        $statusFilter = strtolower(trim((string) $request->input('status', '')));
        $gradeFilter = trim((string) $request->input('grade', ''));

        $filtered = $all->map(fn (User $u) => $this->formatUser($u))
            ->filter(function (array $item) use ($search, $roleFilter, $statusFilter, $gradeFilter) {
                if ($search !== '') {
                    $haystack = mb_strtolower($item['full_name'] . ' ' . $item['email']);
                    if (! str_contains($haystack, $search)) {
                        return false;
                    }
                }
                if ($roleFilter !== '' && ! in_array($roleFilter, ['all', 'todos'], true)) {
                    $norm = $roleFilter === 'super_admin' ? 'superadmin' : $roleFilter;
                    if ($item['role'] !== $norm) {
                        return false;
                    }
                }
                if ($statusFilter !== '' && ! in_array($statusFilter, ['all', 'todos'], true)) {
                    if (strtolower($item['status']) !== $statusFilter) {
                        return false;
                    }
                }
                if ($gradeFilter !== '' && ! in_array(strtolower($gradeFilter), ['all', 'todos'], true)) {
                    if (! str_starts_with((string) $item['grade'], $gradeFilter)) {
                        return false;
                    }
                }
                return true;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $filtered,
        ]);
    }

    public function show($id): JsonResponse
    {
        $user = User::find((int) $id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->filled('email') && ! $request->filled('full_name') && ! $request->filled('name')) {
            return response()->json([
                'success' => true,
                'message' => 'Store user endpoint ready',
            ]);
        }

        if (! $request->filled('full_name') && $request->filled('name')) {
            $request->merge(['full_name' => $request->input('name')]);
        }

        $validated = $request->validate([
            'full_name'          => 'required|string|min:2|max:255',
            'email'              => 'required|string|email|max:255|unique:users,email',
            'password'           => 'nullable|string|min:6|max:255',
            'phone'              => 'nullable|string|max:50',
            'role'               => 'nullable|string|in:student,admin,support,superadmin,super_admin',
            'grade'              => 'nullable|string|max:50',
            'target_icfes_score' => 'nullable|integer|min:0|max:500',
            'status'             => 'nullable|string|in:Activo,Suspendido,activo,suspendido,active,blocked',
        ]);

        $targetRole = $this->normalizeRole($validated['role'] ?? 'student');
        $isSuspended = isset($validated['status'])
            && in_array(strtolower($validated['status']), ['suspendido', 'blocked'], true);

        $attributes = [
            'full_name'          => trim($validated['full_name']),
            'email'              => strtolower(trim($validated['email'])),
            'phone'              => $validated['phone'] ?? null,
            'password'           => $validated['password'] ?? 'Athena2026*',
            'role'               => $targetRole,
            'grade'              => $validated['grade'] ?? '11°',
            'target_icfes_score' => $validated['target_icfes_score'] ?? 350,
        ];

        if (Schema::hasColumn('users', 'status')) {
            $attributes['status'] = $isSuspended ? User::STATUS_BLOCKED : User::STATUS_ACTIVE;
        }

        $user = User::create($attributes);

        if ($isSuspended) {
            $user->remember_token = 'SUSPENDED';
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente.',
            'data'    => $this->formatUser($user),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::find((int) $id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 200);
        }

        if (! $request->filled('full_name') && $request->filled('name')) {
            $request->merge(['full_name' => $request->input('name')]);
        }

        $validated = $request->validate([
            'full_name'          => 'sometimes|required|string|min:2|max:255',
            'email'              => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password'           => 'nullable|string|min:6|max:255',
            'phone'              => 'nullable|string|max:50',
            'role'               => 'nullable|string|in:student,admin,support,superadmin,super_admin',
            'grade'              => 'nullable|string|max:50',
            'target_icfes_score' => 'nullable|integer|min:0|max:500',
            'status'             => 'nullable|string|in:Activo,Suspendido,activo,suspendido,active,blocked',
        ]);

        if (isset($validated['full_name'])) {
            $user->full_name = trim($validated['full_name']);
        }
        if (isset($validated['email'])) {
            $user->email = strtolower(trim($validated['email']));
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if (array_key_exists('grade', $validated)) {
            $user->grade = $validated['grade'];
        }
        if (array_key_exists('target_icfes_score', $validated) && $validated['target_icfes_score'] !== null) {
            $user->target_icfes_score = (int) $validated['target_icfes_score'];
        }
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        if (isset($validated['role'])) {
            $user->role = $this->normalizeRole($validated['role']);
        }
        if (isset($validated['status'])) {
            $suspend = in_array(strtolower($validated['status']), ['suspendido', 'blocked'], true);
            $user->remember_token = $suspend ? 'SUSPENDED' : null;
            if (Schema::hasColumn('users', 'status')) {
                $user->status = $suspend ? User::STATUS_BLOCKED : User::STATUS_ACTIVE;
            }
            if ($suspend) {
                $user->tokens()->delete();
            }
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente.',
            'data'    => $this->formatUser($user),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $user = User::find((int) $id);
        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => 'Delete user placeholder',
            ]);
        }

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

    public function changeRole(Request $request, $id): JsonResponse
    {
        if (! $request->filled('role')) {
            return response()->json([
                'success' => true,
                'message' => 'Change user role placeholder',
            ]);
        }

        $validated = $request->validate([
            'role' => 'required|string|in:student,admin,support,superadmin,super_admin',
        ]);

        $user = User::find((int) $id);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }

        if (AuthController::isMasterAdminEmail($user->email) && $this->normalizeRole($validated['role']) !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede degradar el rol de un SuperAdmin maestro.',
            ], 403);
        }

        $user->role = $this->normalizeRole($validated['role']);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado exitosamente.',
            'data'    => $this->formatUser($user),
        ]);
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $user = User::find((int) $id);
        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => 'Toggle user status placeholder',
            ]);
        }

        if (AuthController::isMasterAdminEmail($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'No está permitido bloquear una cuenta maestra de SuperAdmin.',
            ], 403);
        }

        $currentlySuspended = $user->isBlocked();
        if ($request->filled('status')) {
            $reqStatus = strtolower(trim((string) $request->input('status')));
            $shouldSuspend = in_array($reqStatus, ['suspendido', 'blocked', 'inactive', 'inactivo'], true);
        } else {
            $shouldSuspend = ! $currentlySuspended;
        }

        $user->remember_token = $shouldSuspend ? 'SUSPENDED' : null;
        if (Schema::hasColumn('users', 'status')) {
            $user->status = $shouldSuspend ? User::STATUS_BLOCKED : User::STATUS_ACTIVE;
        }
        $user->save();

        // Revocar inmediatamente todos los tokens de Sanctum si fue bloqueado/suspendido
        if ($shouldSuspend) {
            $user->tokens()->delete();

            Log::warning('Usuario bloqueado/suspendido; tokens de Sanctum revocados', [
                'blocked_user_id' => $user->id,
                'blocked_user_email' => $user->email,
                'admin_id' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $shouldSuspend ? 'Cuenta suspendida y sesiones activas revocadas.' : 'Cuenta reactivada.',
            'data'    => $this->formatUser($user),
        ]);
    }
}
