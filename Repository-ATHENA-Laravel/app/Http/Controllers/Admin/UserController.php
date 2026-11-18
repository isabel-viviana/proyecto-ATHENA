<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
        $isSuspended = ($u->remember_token === 'SUSPENDED');

        return [
            'id'                 => (int) $u->id,
            'full_name'          => $u->full_name,
            'name'               => $u->full_name,
            'email'              => $u->email,
            'phone'              => $u->phone,
            'role'               => $role,
            'grade'              => $u->grade ?: '—',
            'target_icfes_score' => (int) ($u->target_icfes_score ?: 350),
            'targetScore'        => (int) ($u->target_icfes_score ?: 350),
            'status'             => $isSuspended ? 'Suspendido' : 'Activo',
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

        $filtered = $all->map(fn(User $u) => $this->formatUser($u))
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
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $actorRole = $this->normalizeRole($actor?->role);

        if ($actorRole === 'support') {
            return response()->json(['success' => false, 'message' => 'Sin permisos para crear usuarios.'], 403);
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
            'status'             => 'nullable|string|in:Activo,Suspendido,activo,suspendido',
        ]);

        $targetRole = $this->normalizeRole($validated['role'] ?? 'student');
        if ($actorRole !== 'superadmin' && $targetRole !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Solo un Super Administrador puede crear cuentas con rol administrativo.',
            ], 403);
        }

        $isSuspended = isset($validated['status']) && strtolower($validated['status']) === 'suspendido';

        $user = User::create([
            'full_name'          => trim($validated['full_name']),
            'email'              => strtolower(trim($validated['email'])),
            'phone'              => $validated['phone'] ?? null,
            'password'           => Hash::make($validated['password'] ?? 'Athena2026*'),
            'role'               => $targetRole,
            'grade'              => $validated['grade'] ?? '11°',
            'target_icfes_score' => $validated['target_icfes_score'] ?? 350,
        ]);

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
        $actor = $request->user();
        $actorRole = $this->normalizeRole($actor?->role);

        if ($actorRole === 'support') {
            return response()->json(['success' => false, 'message' => 'Sin permisos para editar usuarios.'], 403);
        }

        $user = User::find((int) $id);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }

        $currentTargetRole = $this->normalizeRole($user->role);
        if ($actorRole !== 'superadmin' && $currentTargetRole !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Solo un Super Administrador puede editar cuentas de administradores o soporte.',
            ], 403);
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
            'status'             => 'nullable|string|in:Activo,Suspendido,activo,suspendido',
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
            $user->password = Hash::make($validated['password']);
        }
        if (isset($validated['role'])) {
            $newRole = $this->normalizeRole($validated['role']);
            if ($actorRole !== 'superadmin' && $newRole !== 'student') {
                return response()->json(['success' => false, 'message' => 'Sin permisos para asignar este rol.'], 403);
            }
            $user->role = $newRole;
        }
        if (isset($validated['status'])) {
            $suspend = strtolower($validated['status']) === 'suspendido';
            $user->remember_token = $suspend ? 'SUSPENDED' : null;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente.',
            'data'    => $this->formatUser($user),
        ]);
    }

    public function changeRole(Request $request, $id): JsonResponse
    {
        $actor = $request->user();
        $actorRole = $this->normalizeRole($actor?->role);

        if ($actorRole !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'Solo un Super Administrador puede cambiar roles.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => 'required|string|in:student,admin,support,superadmin,super_admin',
        ]);

        $user = User::find((int) $id);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
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
        $actor = $request->user();
        $actorRole = $this->normalizeRole($actor?->role);

        $user = User::find((int) $id);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }

        if ($actorRole !== 'superadmin' && $this->normalizeRole($user->role) !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Sin permisos para cambiar el estado de otro administrador.',
            ], 403);
        }

        $currentlySuspended = ($user->remember_token === 'SUSPENDED');
        $shouldSuspend = $request->filled('status')
            ? strtolower(trim((string) $request->input('status'))) === 'suspendido'
            : ! $currentlySuspended;

        $user->remember_token = $shouldSuspend ? 'SUSPENDED' : null;
        $user->save();

        if ($shouldSuspend) {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $shouldSuspend ? 'Cuenta suspendida.' : 'Cuenta reactivada.',
            'data'    => $this->formatUser($user),
        ]);
    }
}
