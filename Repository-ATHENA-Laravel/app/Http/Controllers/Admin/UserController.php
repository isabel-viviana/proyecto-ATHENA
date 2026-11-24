<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    protected function normalizeRole(?string $role): string
    {
        return User::normalizeRole($role);
    }

    protected function ensureSuperAdmin(Request $request): ?JsonResponse
    {
        $actor = $request->user();
        if (! $actor || $this->normalizeRole($actor->role) !== User::ROLE_SUPERADMIN) {
            return response()->json([
                'success' => false,
                'code'    => 'FORBIDDEN_ROLE',
                'message' => 'Acceso restringido: Solo un SuperAdmin puede gestionar usuarios y asignar roles.',
            ], 403);
        }

        return null;
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
            'grade'              => $role === User::ROLE_STUDENT ? ($u->grade ?: '11°') : null,
            'target_icfes_score' => $role === User::ROLE_STUDENT ? (int) ($u->target_icfes_score ?: 350) : null,
            'targetScore'        => $role === User::ROLE_STUDENT ? (int) ($u->target_icfes_score ?: 350) : null,
            'status'             => $isSuspended ? 'Suspendido' : 'Activo',
            'raw_status'         => $isSuspended ? User::STATUS_BLOCKED : User::STATUS_ACTIVE,
            'is_active'          => ! $isSuspended,
            'is_master_admin'    => AuthController::isMasterAdminEmail($u->email),
            'last_login_at'      => $u->last_login_at?->toIso8601String(),
            'created_at'         => $u->created_at?->toIso8601String(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->ensureSuperAdmin($request)) {
            return $denied;
        }

        $all = User::orderBy('id', 'desc')->get();

        $totalUsers = $all->count();
        $totalStudents = 0;
        $totalAdmins = 0;
        $totalSuperadmins = 0;
        $activeUsers = 0;
        $suspendedUsers = 0;
        $scoreSum = 0;
        $scoreCount = 0;

        foreach ($all as $u) {
            $normRole = $this->normalizeRole($u->role);
            if ($normRole === User::ROLE_SUPERADMIN) {
                $totalSuperadmins++;
            } elseif ($normRole === User::ROLE_ADMIN) {
                $totalAdmins++;
            } else {
                $totalStudents++;
                $scoreSum += (int) ($u->target_icfes_score ?: 350);
                $scoreCount++;
            }

            if ($u->isBlocked()) {
                $suspendedUsers++;
            } else {
                $activeUsers++;
            }
        }

        $avgTargetScore = $scoreCount > 0 ? (int) round($scoreSum / $scoreCount) : 350;

        $search = mb_strtolower(trim((string) $request->input('search', '')));
        $roleFilter = strtolower(trim((string) $request->input('role', '')));
        $statusFilter = strtolower(trim((string) $request->input('status', '')));
        $gradeFilter = trim((string) $request->input('grade', ''));

        $filtered = $all->map(fn (User $u) => $this->formatUser($u))
            ->filter(function (array $item) use ($search, $roleFilter, $statusFilter, $gradeFilter) {
                if ($search !== '') {
                    $haystack = mb_strtolower($item['full_name'] . ' ' . $item['email'] . ' ' . ($item['phone'] ?? ''));
                    if (! str_contains($haystack, $search)) {
                        return false;
                    }
                }
                if ($roleFilter !== '' && ! in_array($roleFilter, ['all', 'todos'], true)) {
                    $norm = $this->normalizeRole($roleFilter);
                    if ($item['role'] !== $norm) {
                        return false;
                    }
                }
                if ($statusFilter !== '' && ! in_array($statusFilter, ['all', 'todos'], true)) {
                    $itemBlocked = strtolower($item['status']) === 'suspendido';
                    if (in_array($statusFilter, ['suspendido', 'bloqueado', 'blocked', 'suspended'], true) && ! $itemBlocked) {
                        return false;
                    }
                    if (in_array($statusFilter, ['activo', 'active'], true) && $itemBlocked) {
                        return false;
                    }
                }
                if ($gradeFilter !== '' && ! in_array(strtolower($gradeFilter), ['all', 'todos'], true)) {
                    if (! str_starts_with((string) ($item['grade'] ?? ''), $gradeFilter)) {
                        return false;
                    }
                }
                return true;
            })
            ->values();

        $perPage = max(1, (int) $request->input('per_page', 25));
        $page = max(1, (int) $request->input('page', 1));
        $totalFiltered = $filtered->count();
        $lastPage = max(1, (int) ceil($totalFiltered / $perPage));
        $pageItems = $filtered->forPage($page, $perPage)->values();

        return response()->json([
            'success'     => true,
            'data'        => $pageItems,
            'meta'        => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $totalFiltered,
            ],
            'permissions' => [
                'viewer_role'      => User::ROLE_SUPERADMIN,
                'can_create_users' => true,
                'can_manage_roles' => true,
                'can_delete_users' => true,
            ],
            'stats'       => [
                'total'            => $totalUsers,
                'active'           => $activeUsers,
                'suspended'        => $suspendedUsers,
                'avg_target_score' => $avgTargetScore,
                'by_role'          => [
                    'superadmin' => $totalSuperadmins,
                    'admin'      => $totalAdmins,
                    'student'    => $totalStudents,
                ],
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        if ($denied = $this->ensureSuperAdmin($request)) {
            return $denied;
        }

        $user = User::find((int) $id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->ensureSuperAdmin($request)) {
            return $denied;
        }

        if (! $request->filled('full_name') && $request->filled('name')) {
            $request->merge(['full_name' => $request->input('name')]);
        }

        $validated = $request->validate([
            'full_name'          => 'required|string|min:2|max:255',
            'email'              => 'required|string|email|max:255|unique:users,email',
            'password'           => 'required|string|min:6|max:255',
            'phone'              => 'nullable|string|max:50',
            'role'               => 'required|string|in:student,admin',
            'grade'              => 'nullable|string|max:50',
            'target_icfes_score' => 'nullable|integer|min:0|max:500',
            'status'             => 'nullable|string|in:Activo,Suspendido,Bloqueado,activo,suspendido,bloqueado,active,blocked',
        ]);

        $targetRole = $this->normalizeRole($validated['role']);
        $isSuspended = isset($validated['status'])
            && in_array(strtolower($validated['status']), ['suspendido', 'bloqueado', 'blocked'], true);

        $attributes = [
            'full_name'          => trim($validated['full_name']),
            'email'              => strtolower(trim($validated['email'])),
            'phone'              => $validated['phone'] ?? null,
            'password'           => $validated['password'],
            'role'               => $targetRole,
            'grade'              => $targetRole === User::ROLE_STUDENT ? ($validated['grade'] ?? '11°') : null,
            'target_icfes_score' => $targetRole === User::ROLE_STUDENT ? ($validated['target_icfes_score'] ?? 350) : null,
        ];

        if (Schema::hasColumn('users', 'status')) {
            $attributes['status'] = $isSuspended ? User::STATUS_BLOCKED : User::STATUS_ACTIVE;
        }

        $user = User::create($attributes);

        if ($isSuspended) {
            $user->remember_token = 'SUSPENDED';
            $user->save();
        }

        Log::info('SuperAdmin creó un nuevo usuario', [
            'admin_id'      => $request->user()?->id,
            'created_id'    => $user->id,
            'created_email' => $user->email,
            'created_role'  => $user->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente.',
            'data'    => $this->formatUser($user),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if ($denied = $this->ensureSuperAdmin($request)) {
            return $denied;
        }

        $user = User::find((int) $id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $actor = $request->user();
        $isSelf = $actor && (int) $actor->id === (int) $user->id;

        if (! $request->filled('full_name') && $request->filled('name')) {
            $request->merge(['full_name' => $request->input('name')]);
        }

        $validated = $request->validate([
            'full_name'          => 'sometimes|required|string|min:2|max:255',
            'email'              => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password'           => 'nullable|string|min:6|max:255',
            'phone'              => 'nullable|string|max:50',
            'role'               => 'nullable|string|in:student,admin,superadmin,super_admin',
            'grade'              => 'nullable|string|max:50',
            'target_icfes_score' => 'nullable|integer|min:0|max:500',
            'status'             => 'nullable|string|in:Activo,Suspendido,Bloqueado,activo,suspendido,bloqueado,active,blocked',
        ]);

        if (isset($validated['role'])) {
            $newRole = $this->normalizeRole($validated['role']);
            if ($isSelf && $newRole !== User::ROLE_SUPERADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Operación denegada: No puedes quitarte el rol de SuperAdmin a ti mismo.',
                ], 422);
            }
            if (AuthController::isMasterAdminEmail($user->email) && $newRole !== User::ROLE_SUPERADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Operación denegada: No se puede degradar el rol de una cuenta maestra de SuperAdmin.',
                ], 403);
            }
            $user->role = $newRole;
        }

        if (isset($validated['status'])) {
            $suspend = in_array(strtolower($validated['status']), ['suspendido', 'bloqueado', 'blocked'], true);
            if ($isSelf && $suspend) {
                return response()->json([
                    'success' => false,
                    'message' => 'Operación denegada: No puedes bloquear o suspender tu propia cuenta.',
                ], 422);
            }
            if (AuthController::isMasterAdminEmail($user->email) && $suspend) {
                return response()->json([
                    'success' => false,
                    'message' => 'Operación denegada: No se puede bloquear una cuenta maestra de SuperAdmin.',
                ], 403);
            }
            $user->remember_token = $suspend ? 'SUSPENDED' : null;
            if (Schema::hasColumn('users', 'status')) {
                $user->status = $suspend ? User::STATUS_BLOCKED : User::STATUS_ACTIVE;
            }
            if ($suspend) {
                $user->tokens()->delete();
            }
        }

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

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente.',
            'data'    => $this->formatUser($user),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        if ($denied = $this->ensureSuperAdmin($request)) {
            return $denied;
        }

        $user = User::find((int) $id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $actor = $request->user();
        if ($actor && (int) $actor->id === (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Operación denegada: No puedes eliminar tu propia cuenta de SuperAdmin.',
            ], 422);
        }

        if (AuthController::isMasterAdminEmail($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'Operación denegada: No se puede eliminar una cuenta maestra de SuperAdmin.',
            ], 403);
        }

        $deletedEmail = $user->email;
        $user->tokens()->delete();
        $user->delete();

        Log::warning('SuperAdmin eliminó una cuenta de usuario', [
            'admin_id'      => $actor?->id,
            'deleted_id'    => $id,
            'deleted_email' => $deletedEmail,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    public function changeRole(Request $request, $id): JsonResponse
    {
        if ($denied = $this->ensureSuperAdmin($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'role' => 'required|string|in:student,admin',
        ]);

        $user = User::find((int) $id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $actor = $request->user();
        $newRole = $this->normalizeRole($validated['role']);

        if ($actor && (int) $actor->id === (int) $user->id && $newRole !== User::ROLE_SUPERADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'Operación denegada: No puedes quitarte el rol de SuperAdmin a ti mismo.',
            ], 422);
        }

        if (AuthController::isMasterAdminEmail($user->email) && $newRole !== User::ROLE_SUPERADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'Operación denegada: No se puede degradar el rol de un SuperAdmin maestro.',
            ], 403);
        }

        if ($this->normalizeRole($user->role) === User::ROLE_SUPERADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'Operación denegada: No se puede modificar el rol de otra cuenta SuperAdmin.',
            ], 403);
        }

        $user->role = $newRole;
        $user->save();

        $roleLabel = $newRole === User::ROLE_ADMIN ? 'Administrador' : 'Estudiante';

        return response()->json([
            'success' => true,
            'message' => "Rol actualizado a {$roleLabel} exitosamente.",
            'data'    => $this->formatUser($user),
        ]);
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        if ($denied = $this->ensureSuperAdmin($request)) {
            return $denied;
        }

        $user = User::find((int) $id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $actor = $request->user();
        if ($actor && (int) $actor->id === (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Operación denegada: No puedes bloquear o desactivar tu propia cuenta.',
            ], 422);
        }

        if (AuthController::isMasterAdminEmail($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'Operación denegada: No está permitido bloquear una cuenta maestra de SuperAdmin.',
            ], 403);
        }

        $currentlySuspended = $user->isBlocked();
        if ($request->filled('status')) {
            $reqStatus = strtolower(trim((string) $request->input('status')));
            $shouldSuspend = in_array($reqStatus, ['suspendido', 'bloqueado', 'blocked', 'inactive', 'inactivo'], true);
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
                'blocked_user_id'    => $user->id,
                'blocked_user_email' => $user->email,
                'admin_id'           => $actor?->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $shouldSuspend
                ? 'Cuenta bloqueada y sesiones activas revocadas inmediatamente.'
                : 'Cuenta desbloqueada y reactivada exitosamente.',
            'data'    => $this->formatUser($user),
        ]);
    }
}
