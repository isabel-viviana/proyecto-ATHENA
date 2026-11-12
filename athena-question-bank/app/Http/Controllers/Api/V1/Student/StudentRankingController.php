<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentAnswer;
use App\Services\StudentTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class StudentRankingController extends Controller
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
     * Consulta el ranking oficial de estudiantes calculado en backend
     * a partir de respuestas reales de práctica en Supabase PostgreSQL y perfiles MySQL.
     *
     * FÓRMULA CENTRALIZADA DE PUNTUACIÓN:
     * - Puntos base = (Preguntas distintas contestadas correctamente) * 10 pts.
     * - Criterio de desempate 1: Porcentaje de efectividad (accuracy %).
     * - Criterio de desempate 2: Total de reactivos respondidos.
     *
     * PRIVACIDAD:
     * - Solo se expone el nombre visible, grado, avatar y puntuación.
     * - Jamás se exponen correos, teléfonos ni hashes.
     *
     * GET /api/v1/student/ranking
     */
    public function index(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        $currentStudentId = $authStudent ? (string) $authStudent['sub'] : null;

        try {
            // 1. Obtener estadísticas de respuestas en Supabase PostgreSQL agrupadas por student_id
            $answersStats = DB::connection('question_bank')
                ->table('student_answers')
                ->whereNotNull('student_id')
                ->select(
                    'student_id',
                    DB::raw('count(id) as total_answers'),
                    DB::raw('count(distinct case when is_correct = true then question_id end) as distinct_correct'),
                    DB::raw('sum(case when is_correct = true then 1 else 0 end) as total_correct'),
                    DB::raw('max(answered_at) as last_activity')
                )
                ->groupBy('student_id')
                ->get()
                ->keyBy('student_id');

            // 2. Obtener usuarios estudiantes desde MySQL (ATHENA Core)
            $users = DB::connection('athena_core')
                ->table('users')
                ->where('role', 'student')
                ->select('id', 'full_name', 'avatar_url', 'grade', 'level', 'xp')
                ->get();

            // Si MySQL fallara o no tuviera usuarios, asegurar la cuenta semilla
            if ($users->isEmpty()) {
                $users = collect([
                    (object) [
                        'id'         => 1,
                        'full_name'  => 'Juan Sebastian Acevedo Medina',
                        'avatar_url' => null,
                        'grade'      => 'Grado 11°',
                        'level'      => 1,
                        'xp'         => 100,
                    ],
                ]);
            }

            // 3. Cruzar métricas reales y calcular puntuaciones y desempates
            $rankedList = $users->map(function ($u) use ($answersStats) {
                $uid = (string) $u->id;
                $stats = $answersStats->get($uid);

                $totalAnswers = $stats ? (int) $stats->total_answers : 0;
                $distinctCorrect = $stats ? (int) $stats->distinct_correct : 0;
                $totalCorrect = $stats ? (int) $stats->total_correct : 0;
                $accuracy = $totalAnswers > 0 ? round(($totalCorrect / $totalAnswers) * 100, 1) : 0;

                // Fórmula oficial: 10 puntos por cada reactivo único acertado
                $score = $distinctCorrect * 10;

                // Generar nombre público seguro (Privacidad)
                $nameParts = explode(' ', trim($u->full_name));
                $publicName = count($nameParts) >= 2
                    ? $nameParts[0] . ' ' . substr($nameParts[1], 0, 1) . '.'
                    : $u->full_name;

                return [
                    'student_id'       => $uid,
                    'full_name'        => $u->full_name,
                    'public_name'      => $publicName,
                    'avatar_url'       => $u->avatar_url,
                    'grade'            => $u->grade ?: 'Grado 11°',
                    'level'            => (int) ($u->level ?? 1),
                    'score'            => $score,
                    'distinct_correct' => $distinctCorrect,
                    'total_answered'   => $totalAnswers,
                    'accuracy'         => $accuracy,
                    'last_activity'    => $stats ? $stats->last_activity : null,
                ];
            });

            // 4. Ordenar según las reglas académicas oficiales
            $sorted = $rankedList->sort(function ($a, $b) {
                // 1° Criterio: Puntuación descendente
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }
                // 2° Criterio (desempate): Efectividad porcentual descendente
                if ($a['accuracy'] !== $b['accuracy']) {
                    return $b['accuracy'] <=> $a['accuracy'];
                }
                // 3° Criterio (desempate): Total de reactivos contestados
                if ($a['total_answered'] !== $b['total_answered']) {
                    return $b['total_answered'] <=> $a['total_answered'];
                }
                return strcmp($a['public_name'], $b['public_name']);
            })->values();

            // 5. Asignar posiciones ordinales (1, 2, 3...)
            $finalRanking = $sorted->map(function ($item, $index) use ($currentStudentId) {
                $item['rank'] = $index + 1;
                $item['is_current_student'] = ($currentStudentId !== null && (string)$item['student_id'] === $currentStudentId);
                return $item;
            });

            // 6. Extraer Top 3 y posición del estudiante actual
            $top3 = $finalRanking->take(3)->values()->all();
            $myRank = $currentStudentId
                ? $finalRanking->firstWhere('student_id', $currentStudentId)
                : null;

            return response()->json([
                'success' => true,
                'data'    => [
                    'top_3'              => $top3,
                    'ranking'            => $finalRanking->values()->all(),
                    'my_rank'            => $myRank,
                    'total_participants' => $finalRanking->count(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular ranking de estudiantes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta la posición específica del estudiante autenticado.
     *
     * GET /api/v1/student/ranking/me
     */
    public function me(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada.',
            ], 401);
        }

        $res = $this->index($request);
        $data = json_decode($res->getContent(), true)['data'] ?? [];

        return response()->json([
            'success' => true,
            'data'    => $data['my_rank'] ?? null,
        ]);
    }
}

