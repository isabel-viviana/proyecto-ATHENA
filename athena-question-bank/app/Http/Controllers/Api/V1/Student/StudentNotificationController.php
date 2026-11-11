<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class StudentNotificationController extends Controller
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
     * Consulta las notificaciones institucionales del estudiante autenticado.
     * Soporta filtros: 'all', 'unread', 'read'.
     *
     * GET /api/v1/student/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = $authStudent['sub'];
        $filter = $request->input('filter', 'all'); // 'all', 'unread', 'read'

        try {
            $query = DB::connection('athena_core')
                ->table('notifications')
                ->where('user_id', $studentId);

            if ($filter === 'unread') {
                $query->where('is_read', 0);
            } elseif ($filter === 'read') {
                $query->where('is_read', 1);
            }

            $notifications = $query->orderBy('created_at', 'desc')->get();

            $unreadCount = DB::connection('athena_core')
                ->table('notifications')
                ->where('user_id', $studentId)
                ->where('is_read', 0)
                ->count();

            $data = $notifications->map(function ($n) {
                return [
                    'id'         => $n->id,
                    'title'      => $n->title,
                    'message'    => $n->message,
                    'type'       => $n->type,
                    'is_read'    => (bool) $n->is_read,
                    'created_at' => $n->created_at,
                ];
            });

            return response()->json([
                'success'      => true,
                'data'         => $data,
                'unread_count' => $unreadCount,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar notificaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Devuelve el conteo de notificaciones no leídas para el badge del menú.
     *
     * GET /api/v1/student/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json(['success' => true, 'count' => 0]);
        }

        $studentId = $authStudent['sub'];

        try {
            $count = DB::connection('athena_core')
                ->table('notifications')
                ->where('user_id', $studentId)
                ->where('is_read', 0)
                ->count();

            return response()->json([
                'success' => true,
                'count'   => $count,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => true, 'count' => 0]);
        }
    }

    /**
     * Marca una notificación específica como leída.
     *
     * PATCH /api/v1/student/notifications/{id}/read
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = $authStudent['sub'];

        try {
            $affected = DB::connection('athena_core')
                ->table('notifications')
                ->where('id', $id)
                ->where('user_id', $studentId)
                ->update([
                    'is_read'    => 1,
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notificación no encontrada o no pertenece al estudiante.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar notificación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marca todas las notificaciones pendientes del estudiante como leídas.
     *
     * PATCH /api/v1/student/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = $authStudent['sub'];

        try {
            DB::connection('athena_core')
                ->table('notifications')
                ->where('user_id', $studentId)
                ->where('is_read', 0)
                ->update([
                    'is_read'    => 1,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Todas las notificaciones fueron marcadas como leídas.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar notificaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina una notificación del estudiante.
     *
     * DELETE /api/v1/student/notifications/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $studentId = $authStudent['sub'];

        try {
            $deleted = DB::connection('athena_core')
                ->table('notifications')
                ->where('id', $id)
                ->where('user_id', $studentId)
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notificación no encontrada o no pertenece al estudiante.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notificación eliminada correctamente.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar notificación: ' . $e->getMessage(),
            ], 500);
        }
    }
}

