<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class AdminUserController extends Controller
{
    /**
     * Obtiene la conexión donde reside la tabla 'users' (athena_core MySQL en producción,
     * o la conexión por defecto en entorno de pruebas si se configuró en memoria).
     */
    protected function usersConnection(): string
    {
        return 'athena_core';
    }

    /**
     * Retorna los datos del administrador autenticado inyectados por el middleware admin.auth.
     */
    protected function currentAdmin(Request $request): array
    {
        return $request->attributes->get('auth_admin', [
            'id'   => 'unknown',
            'role' => 'admin',
        ]);
    }

    protected function isSuperAdmin(array $admin): bool
    {
        $role = strtolower(trim((string) ($admin['role'] ?? '')));
        return in_array($role, ['superadmin', 'super_admin'], true);
    }

    protected function formatUserRow(object $u): array
    {
        $rawRole = strtolower(trim((string) ($u->role ?? 'student')));
        $role = $rawRole === 'super_admin' ? 'superadmin' : (in_array($rawRole, ['admin', 'superadmin'], true) ? $rawRole : 'student');
        $isSuspended = ($u->remember_token ?? null) === 'SUSPENDED';

        return [
            'id'                 => (int) $u->id,
            'full_name'          => $u->full_name,
            'name'               => $u->full_name,
            'email'              => $u->email,
            'phone'              => $u->phone,
            'role'               => $role,
            'grade'              => $u->grade ?: '11°',
            'target_icfes_score' => (int) ($u->target_icfes_score ?? 350),
            'targetScore'        => (int) ($u->target_icfes_score ?? 350),
            'avatar_url'         => $u->avatar_url,
            'status'             => $isSuspended ? 'Suspendido' : 'Activo',
            'is_active'          => ! $isSuspended,
            'last_login_at'      => $u->last_login_at,
            'created_at'         => $u->created_at,
            'updated_at'         => $u->updated_at,
        ];
    }

    /**
     * Listado y filtrado real de usuarios desde MySQL (athena_core.users).
     *
     * GET /api/v1/admin/users
     */
    public function index(Request $request): JsonResponse
    {
        $conn = $this->usersConnection();
        $allUsers = DB::connection($conn)->table('users')->orderBy('id', 'desc')->get();

        // Calcular métricas globales sobre todos los usuarios reales
        $totalUsers = $allUsers->count();
        $totalStudents = 0;
        $totalAdmins = 0;
        $totalSuperadmins = 0;
        $activeUsers = 0;
        $suspendedUsers = 0;
        $scoreSum = 0;
        $scoreCount = 0;

        foreach ($allUsers as $u) {
            $r = strtolower(trim((string) ($u->role ?? 'student')));
            if ($r === 'super_admin' || $r === 'superadmin') {
                $totalSuperadmins++;
            } elseif ($r === 'admin') {
                $totalAdmins++;
            } else {
                $totalStudents++;
            }

            if (($u->remember_token ?? null) === 'SUSPENDED') {
                $suspendedUsers++;
            } else {
                $activeUsers++;
            }

            if ($r === 'student' || empty($r)) {
                $scoreSum += (int) ($u->target_icfes_score ?: 350);
                $scoreCount++;
            }
        }

        $avgTargetScore = $scoreCount > 0 ? (int) round($scoreSum / $scoreCount) : 350;

        // Aplicar filtros de búsqueda
        $search = mb_strtolower(trim((string) $request->input('search', '')));
        $roleFilter = strtolower(trim((string) $request->input('role', '')));
        $statusFilter = trim((string) $request->input('status', ''));
        $gradeFilter = trim((string) $request->input('grade', ''));

        $filtered = $allUsers->map(fn($u) => $this->formatUserRow($u))->filter(function (array $item) use ($search, $roleFilter, $statusFilter, $gradeFilter) {
            if ($search !== '') {
                $haystack = mb_strtolower($item['full_name'] . ' ' . $item['email'] . ' ' . ($item['phone'] ?? ''));
                if (! str_contains($haystack, $search)) {
                    return false;
                }
            }

            if ($roleFilter !== '' && ! in_array($roleFilter, ['all', 'todos'], true)) {
                $normalizedFilter = $roleFilter === 'super_admin' ? 'superadmin' : $roleFilter;
                if ($item['role'] !== $normalizedFilter) {
                    return false;
                }
            }

            if ($statusFilter !== '' && ! in_array(strtolower($statusFilter), ['all', 'todos'], true)) {
                if (strtolower($item['status']) !== strtolower($statusFilter)) {
                    return false;
                }
            }

            if ($gradeFilter !== '' && ! in_array(strtolower($gradeFilter), ['all', 'todos'], true)) {
                if (! str_starts_with((string) $item['grade'], $gradeFilter)) {
                    return false;
                }
            }

            return true;
        })->values();

        $admin = $this->currentAdmin($request);
        $viewerRole = strtolower(trim((string) ($admin['role'] ?? 'admin')));
        if ($viewerRole === 'super_admin') {
            $viewerRole = 'superadmin';
        }

        return response()->json([
            'success' => true,
            'data'    => $filtered,
            'meta'    => [
                'current_page' => 1,
                'last_page'    => 1,
                'per_page'     => max(25, $filtered->count()),
                'total'        => $filtered->count(),
            ],
            'permissions' => [
                'viewer_role'      => $viewerRole,
                'can_create_users' => in_array($viewerRole, ['admin', 'superadmin'], true),
                'can_manage_roles' => $viewerRole === 'superadmin',
            ],
            'stats'   => [
                'total'                => $totalUsers,
                'total_users'          => $totalUsers,
                'total_students'       => $totalStudents,
                'total_admins'         => $totalAdmins,
                'total_superadmins'    => $totalSuperadmins,
                'active'               => $activeUsers,
                'active_users'         => $activeUsers,
                'suspended'            => $suspendedUsers,
                'suspended_users'      => $suspendedUsers,
                'avg_target_score'     => $avgTargetScore,
                'average_target_score' => $avgTargetScore,
                'by_role'              => [
                    'student'    => $totalStudents,
                    'admin'      => $totalAdmins,
                    'superadmin' => $totalSuperadmins,
                ],
            ],
        ]);
    }

    /**
     * Consulta la información detallada de un usuario y su resumen de actividad académica.
     *
     * GET /api/v1/admin/users/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $conn = $this->usersConnection();
        $user = DB::connection($conn)->table('users')->where('id', (int) $id)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $formatted = $this->formatUserRow($user);

        // Enriquecer con actividad académica real en Supabase si existe
        $academicSummary = [
            'exam_attempts_count'   => 0,
            'completed_exams_count' => 0,
            'answers_count'         => 0,
            'correct_answers_count' => 0,
            'total_answered'        => 0,
            'correct_count'         => 0,
            'accuracy'              => 0.0,
        ];

        try {
            $studentIdStr = (string) $user->id;
            if (Schema::connection('question_bank')->hasTable('student_answers')) {
                $totalAns = StudentAnswer::where('student_id', $studentIdStr)->count();
                $corrAns = StudentAnswer::where('student_id', $studentIdStr)->where('is_correct', true)->count();
                $academicSummary['total_answered'] = $totalAns;
                $academicSummary['answers_count'] = $totalAns;
                $academicSummary['correct_count'] = $corrAns;
                $academicSummary['correct_answers_count'] = $corrAns;
                $academicSummary['accuracy'] = $totalAns > 0 ? round(($corrAns / $totalAns) * 100, 1) : 0.0;
            }
            if (Schema::connection('question_bank')->hasTable('exam_attempts')) {
                $academicSummary['exam_attempts_count'] = ExamAttempt::where('student_id', $studentIdStr)->count();
                $academicSummary['completed_exams_count'] = ExamAttempt::where('student_id', $studentIdStr)
                    ->where('status', 'completed')
                    ->count();
            }
        } catch (Throwable) {
            // Mantener valores por defecto si no aplica
        }

        $formatted['academic_summary'] = $academicSummary;
        $formatted['academic_activity'] = $academicSummary;

        return response()->json([
            'success' => true,
            'data'    => $formatted,
        ]);
    }

    /**
     * Crea un nuevo usuario en MySQL respetando las reglas de permisos por rol.
     *
     * POST /api/v1/admin/users
     */
    public function store(Request $request): JsonResponse
    {
        $admin = $this->currentAdmin($request);

        if (! $request->filled('full_name') && $request->filled('name')) {
            $request->merge(['full_name' => $request->input('name')]);
        }

        $validated = $request->validate([
            'full_name'          => 'required|string|min:2|max:255',
            'email'              => 'required|string|email|max:255',
            'password'           => 'nullable|string|min:6|max:255',
            'phone'              => 'nullable|string|max:50',
            'role'               => 'nullable|string|in:student,admin,superadmin,super_admin',
            'grade'              => 'nullable|string|max:50',
            'target_icfes_score' => 'nullable|integer|min:0|max:500',
            'status'             => 'nullable|string|in:Activo,Suspendido,activo,suspendido',
        ]);

        $targetRole = strtolower(trim((string) ($validated['role'] ?? 'student')));
        if ($targetRole === 'super_admin') {
            $targetRole = 'superadmin';
        }

        // Un administrador regular solo puede crear cuentas de estudiantes
        if (! $this->isSuperAdmin($admin) && $targetRole !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Solo un Super Administrador puede crear cuentas con rol administrativo.',
            ], 403);
        }

        $conn = $this->usersConnection();
        $emailNormalized = strtolower(trim($validated['email']));

        $exists = DB::connection($conn)->table('users')
            ->whereRaw('LOWER(email) = ?', [$emailNormalized])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un usuario registrado con este correo electrónico.',
                'errors'  => ['email' => ['El correo electrónico ya está registrado.']],
            ], 422);
        }

        $passwordPlain = ! empty($validated['password']) ? $validated['password'] : 'Athena2026*';
        $isSuspended = isset($validated['status']) && strtolower($validated['status']) === 'suspendido';
        $now = now();

        $newId = DB::connection($conn)->table('users')->insertGetId([
            'full_name'          => trim($validated['full_name']),
            'email'              => $emailNormalized,
            'phone'              => isset($validated['phone']) ? trim((string) $validated['phone']) : null,
            'password'           => Hash::make($passwordPlain),
            'role'               => $targetRole,
            'grade'              => $validated['grade'] ?? '11°',
            'target_icfes_score' => isset($validated['target_icfes_score']) ? (int) $validated['target_icfes_score'] : 350,
            'streak_days'        => 0,
            'remember_token'     => $isSuspended ? 'SUSPENDED' : null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        $created = DB::connection($conn)->table('users')->where('id', $newId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente.',
            'data'    => $this->formatUserRow($created),
        ], 201);
    }

    /**
     * Edita la información de un usuario existente respetando jerarquía de roles.
     *
     * PUT /api/v1/admin/users/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $admin = $this->currentAdmin($request);

        $conn = $this->usersConnection();
        $user = DB::connection($conn)->table('users')->where('id', (int) $id)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $currentUserRole = strtolower(trim((string) ($user->role ?? 'student')));
        if ($currentUserRole === 'super_admin') {
            $currentUserRole = 'superadmin';
        }

        // Un admin regular solo puede editar cuentas de estudiantes
        if (! $this->isSuperAdmin($admin) && $currentUserRole !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Solo un Super Administrador puede modificar cuentas de administradores.',
            ], 403);
        }

        if (! $request->filled('full_name') && $request->filled('name')) {
            $request->merge(['full_name' => $request->input('name')]);
        }

        $validated = $request->validate([
            'full_name'          => 'sometimes|required|string|min:2|max:255',
            'email'              => 'sometimes|required|string|email|max:255',
            'password'           => 'nullable|string|min:6|max:255',
            'phone'              => 'nullable|string|max:50',
            'role'               => 'nullable|string|in:student,admin,superadmin,super_admin',
            'grade'              => 'nullable|string|max:50',
            'target_icfes_score' => 'nullable|integer|min:0|max:500',
            'status'             => 'nullable|string|in:Activo,Suspendido,activo,suspendido',
        ]);

        if (isset($validated['email'])) {
            $emailNormalized = strtolower(trim($validated['email']));
            $duplicate = DB::connection($conn)->table('users')
                ->whereRaw('LOWER(email) = ?', [$emailNormalized])
                ->where('id', '!=', (int) $user->id)
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'El correo electrónico ya pertenece a otro usuario.',
                    'errors'  => ['email' => ['El correo electrónico ya está en uso.']],
                ], 422);
            }
        }

        $updateData = ['updated_at' => now()];

        if (array_key_exists('full_name', $validated)) {
            $updateData['full_name'] = trim($validated['full_name']);
        }
        if (array_key_exists('email', $validated)) {
            $updateData['email'] = strtolower(trim($validated['email']));
        }
        if (array_key_exists('phone', $validated)) {
            $updateData['phone'] = $validated['phone'] !== null ? trim((string) $validated['phone']) : null;
        }
        if (array_key_exists('grade', $validated)) {
            $updateData['grade'] = $validated['grade'] !== null ? trim((string) $validated['grade']) : null;
        }
        if (array_key_exists('target_icfes_score', $validated) && $validated['target_icfes_score'] !== null) {
            $updateData['target_icfes_score'] = (int) $validated['target_icfes_score'];
        }
        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        if (array_key_exists('role', $validated) && $validated['role'] !== null) {
            $newRole = strtolower(trim($validated['role']));
            if ($newRole === 'super_admin') {
                $newRole = 'superadmin';
            }
            if (! $this->isSuperAdmin($admin) && $newRole !== 'student') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo un Super Administrador puede asignar roles administrativos.',
                ], 403);
            }
            if ((string) ($admin['id'] ?? $admin['sub'] ?? '') === (string) $user->id && $newRole !== 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes revocar tu propio rol de Super Administrador.',
                ], 422);
            }
            $updateData['role'] = $newRole;
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $suspend = strtolower(trim($validated['status'])) === 'suspendido';
            if ($suspend && (string) ($admin['id'] ?? $admin['sub'] ?? '') === (string) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes suspender tu propia cuenta activa.',
                ], 422);
            }
            $updateData['remember_token'] = $suspend ? 'SUSPENDED' : null;
        }

        DB::connection($conn)->table('users')->where('id', (int) $user->id)->update($updateData);
        $fresh = DB::connection($conn)->table('users')->where('id', (int) $user->id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente.',
            'data'    => $this->formatUserRow($fresh),
        ]);
    }

    /**
     * Cambia el rol de un usuario. Exclusivo para Super Administrador.
     *
     * PATCH /api/v1/admin/users/{id}/role
     */
    public function changeRole(Request $request, string $id): JsonResponse
    {
        $admin = $this->currentAdmin($request);

        if (! $this->isSuperAdmin($admin)) {
            return response()->json([
                'success' => false,
                'message' => 'Solo un Super Administrador tiene permisos para cambiar roles de usuario.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => 'required|string|in:student,admin,superadmin,super_admin',
        ]);

        $conn = $this->usersConnection();
        $user = DB::connection($conn)->table('users')->where('id', (int) $id)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $newRole = strtolower(trim($validated['role']));
        if ($newRole === 'super_admin') {
            $newRole = 'superadmin';
        }

        if ((string) ($admin['id'] ?? $admin['sub'] ?? '') === (string) $user->id && $newRole !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No puedes revocar tu propio rol de Super Administrador.',
            ], 422);
        }

        DB::connection($conn)->table('users')->where('id', (int) $user->id)->update([
            'role'       => $newRole,
            'updated_at' => now(),
        ]);

        $fresh = DB::connection($conn)->table('users')->where('id', (int) $user->id)->first();

        return response()->json([
            'success' => true,
            'message' => "Rol actualizado a '{$newRole}' exitosamente.",
            'data'    => $this->formatUserRow($fresh),
        ]);
    }

    /**
     * Activa o suspende/desactiva la cuenta de un usuario.
     *
     * PATCH /api/v1/admin/users/{id}/status
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $admin = $this->currentAdmin($request);
        $conn = $this->usersConnection();

        $user = DB::connection($conn)->table('users')->where('id', (int) $id)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $targetRole = strtolower(trim((string) ($user->role ?? 'student')));
        if ($targetRole === 'super_admin') {
            $targetRole = 'superadmin';
        }

        if (! $this->isSuperAdmin($admin) && $targetRole !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para suspender o activar cuentas de otros administradores.',
            ], 403);
        }

        if ((string) ($admin['id'] ?? $admin['sub'] ?? '') === (string) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes suspender o desactivar tu propia cuenta.',
            ], 422);
        }

        $currentlySuspended = ($user->remember_token ?? null) === 'SUSPENDED';

        if ($request->filled('status')) {
            $shouldSuspend = strtolower(trim((string) $request->input('status'))) === 'suspendido';
        } elseif ($request->has('is_active')) {
            $shouldSuspend = ! (bool) $request->input('is_active');
        } else {
            $shouldSuspend = ! $currentlySuspended;
        }

        DB::connection($conn)->table('users')->where('id', (int) $user->id)->update([
            'remember_token' => $shouldSuspend ? 'SUSPENDED' : null,
            'updated_at'     => now(),
        ]);

        // Si se suspende, revocar tokens Sanctum activos para invalidar sesiones abiertas
        if ($shouldSuspend) {
            try {
                if (Schema::connection($conn)->hasTable('personal_access_tokens')) {
                    DB::connection($conn)->table('personal_access_tokens')
                        ->where('tokenable_id', (int) $user->id)
                        ->delete();
                }
            } catch (Throwable) {
                // Ignorar si no existe tabla de tokens en entorno de prueba
            }
        }

        $fresh = DB::connection($conn)->table('users')->where('id', (int) $user->id)->first();

        return response()->json([
            'success' => true,
            'message' => $shouldSuspend
                ? 'Cuenta de usuario suspendida exitosamente.'
                : 'Cuenta de usuario reactivada exitosamente.',
            'data'    => $this->formatUserRow($fresh),
        ]);
    }
}
