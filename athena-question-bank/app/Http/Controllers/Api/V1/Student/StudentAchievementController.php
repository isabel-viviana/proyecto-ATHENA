<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use App\Services\StudentTokenService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StudentAchievementController extends Controller
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
     * Calcula la racha real de días consecutivos con actividad en student_answers.
     */
    protected function calculateRealStreak(string $studentId): int
    {
        try {
            $dates = StudentAnswer::where('student_id', $studentId)
                ->select(DB::raw('DATE(answered_at) as practice_date'))
                ->groupBy(DB::raw('DATE(answered_at)'))
                ->orderBy('practice_date', 'desc')
                ->pluck('practice_date')
                ->map(fn($d) => substr((string) $d, 0, 10))
                ->all();

            $realStreak = 0;
            $currentDate = now()->toDateString();
            $yesterdayDate = now()->subDay()->toDateString();

            if (! empty($dates)) {
                if ($dates[0] === $currentDate || $dates[0] === $yesterdayDate) {
                    $checkDate = Carbon::parse($dates[0]);
                    $realStreak = 1;
                    for ($i = 1; $i < count($dates); $i++) {
                        $prevDate = Carbon::parse($dates[$i]);
                        if ($checkDate->diffInDays($prevDate) === 1) {
                            $realStreak++;
                            $checkDate = $prevDate;
                        } else {
                            break;
                        }
                    }
                }
            }

            return $realStreak;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Evalúa dinámicamente los logros académicos del estudiante a partir de su actividad real
     * en student_answers, exam_attempts y su racha de estudio (streak_days), sin depender de XP ni monedas.
     *
     * GET /api/v1/student/achievements
     */
    public function index(Request $request): JsonResponse
    {
        $authStudent = $this->getAuthenticatedStudent($request);
        if (! $authStudent) {
            return response()->json([
                'success' => false,
                'message' => 'Sesión no válida o expirada. Debes iniciar sesión.',
            ], 401);
        }

        $studentId = (string) $authStudent['sub'];

        try {
            // 1. Métricas reales de respuestas en student_answers
            $statsRow = DB::connection('question_bank')
                ->table('student_answers')
                ->where('student_id', $studentId)
                ->select(
                    DB::raw('count(id) as total_answered'),
                    DB::raw('sum(case when is_correct = true then 1 else 0 end) as total_correct'),
                    DB::raw('count(distinct case when is_correct = true then question_id end) as distinct_correct')
                )
                ->first();

            $totalAnswered = $statsRow ? (int) $statsRow->total_answered : 0;
            $totalCorrect = $statsRow ? (int) $statsRow->total_correct : 0;
            $distinctCorrect = $statsRow ? (int) $statsRow->distinct_correct : 0;
            $accuracy = $totalAnswered > 0 ? round(($totalCorrect / $totalAnswered) * 100, 1) : 0.0;

            // 2. Áreas/materias distintas practicadas
            $distinctSubjects = (int) DB::connection('question_bank')
                ->table('student_answers as sa')
                ->join('questions as q', 'sa.question_id', '=', 'q.id')
                ->where('sa.student_id', $studentId)
                ->whereNotNull('q.subject_id')
                ->distinct()
                ->count('q.subject_id');

            // 3. Simulacros completados
            $completedExamsCount = 0;
            try {
                if (Schema::connection('question_bank')->hasTable('exam_attempts')) {
                    $completedExamsCount = ExamAttempt::where('student_id', $studentId)
                        ->where('status', 'completed')
                        ->count();
                }
            } catch (Throwable) {
                $completedExamsCount = 0;
            }

            // 4. Racha real de días consecutivos de estudio
            $realStreak = $this->calculateRealStreak($studentId);

            // 5. Catálogo de 9 logros académicos reales
            $catalog = [
                [
                    'id'          => 'first_step',
                    'title'       => 'Primer Paso',
                    'description' => 'Responde tu primera pregunta oficial ICFES en la plataforma.',
                    'category'    => 'Práctica',
                    'icon'        => 'fa-seedling',
                    'current'     => $totalAnswered,
                    'target'      => 1,
                    'unit'        => 'preguntas',
                    'unlocked'    => $totalAnswered >= 1,
                ],
                [
                    'id'          => 'warming_up',
                    'title'       => 'En Calentamiento',
                    'description' => 'Responde un acumulado de 25 preguntas oficiales en sesiones de práctica.',
                    'category'    => 'Práctica',
                    'icon'        => 'fa-dumbbell',
                    'current'     => $totalAnswered,
                    'target'      => 25,
                    'unit'        => 'preguntas',
                    'unlocked'    => $totalAnswered >= 25,
                ],
                [
                    'id'          => 'academic_centurion',
                    'title'       => 'Centurión Académico',
                    'description' => 'Alcanza las 100 preguntas respondidas del banco oficial ICFES.',
                    'category'    => 'Práctica',
                    'icon'        => 'fa-medal',
                    'current'     => $totalAnswered,
                    'target'      => 100,
                    'unit'        => 'preguntas',
                    'unlocked'    => $totalAnswered >= 100,
                ],
                [
                    'id'          => 'initial_streak',
                    'title'       => 'Racha Inicial',
                    'description' => 'Mantén una racha de estudio activa durante 3 días consecutivos.',
                    'category'    => 'Constancia',
                    'icon'        => 'fa-fire',
                    'current'     => $realStreak,
                    'target'      => 3,
                    'unit'        => 'días seguidos',
                    'unlocked'    => $realStreak >= 3,
                ],
                [
                    'id'          => 'unstoppable_streak',
                    'title'       => 'Racha Imparable',
                    'description' => 'Estudia y resuelve reactivos durante 7 días seguidos sin interrupción.',
                    'category'    => 'Constancia',
                    'icon'        => 'fa-fire-flame-curved',
                    'current'     => $realStreak,
                    'target'      => 7,
                    'unit'        => 'días seguidos',
                    'unlocked'    => $realStreak >= 7,
                ],
                [
                    'id'          => 'sharp_shooter',
                    'title'       => 'Mente Certera',
                    'description' => 'Acierta al menos 20 reactivos únicos del banco de preguntas ICFES.',
                    'category'    => 'Precisión',
                    'icon'        => 'fa-bullseye',
                    'current'     => $distinctCorrect,
                    'target'      => 20,
                    'unit'        => 'aciertos únicos',
                    'unlocked'    => $distinctCorrect >= 20,
                ],
                [
                    'id'          => 'surgical_precision',
                    'title'       => 'Precisión Quirúrgica',
                    'description' => 'Logra una efectividad global igual o superior al 80% habiendo respondido al menos 10 preguntas.',
                    'category'    => 'Precisión',
                    'icon'        => 'fa-crosshairs',
                    'current'     => $totalAnswered >= 10 ? (int) round($accuracy) : $totalAnswered,
                    'target'      => $totalAnswered >= 10 ? 80 : 10,
                    'unit'        => $totalAnswered >= 10 ? '% de efectividad' : 'preguntas mínimas (meta: 80%)',
                    'unlocked'    => $totalAnswered >= 10 && $accuracy >= 80.0,
                    'custom_progress' => $totalAnswered < 10
                        ? (int) min(90, round(($totalAnswered / 10) * 50))
                        : (int) min(100, round(($accuracy / 80) * 100)),
                ],
                [
                    'id'          => 'icfes_explorer',
                    'title'       => 'Explorador ICFES',
                    'description' => 'Responde preguntas en al menos 3 áreas o materias distintas del examen.',
                    'category'    => 'Dominio ICFES',
                    'icon'        => 'fa-compass',
                    'current'     => $distinctSubjects,
                    'target'      => 3,
                    'unit'        => 'áreas ICFES',
                    'unlocked'    => $distinctSubjects >= 3,
                ],
                [
                    'id'          => 'multidisciplinary_master',
                    'title'       => 'Maestro Multidisciplinar',
                    'description' => 'Entrena y responde preguntas en las 5 áreas oficiales del ICFES Saber 11.',
                    'category'    => 'Dominio ICFES',
                    'icon'        => 'fa-graduation-cap',
                    'current'     => $distinctSubjects,
                    'target'      => 5,
                    'unit'        => 'áreas ICFES',
                    'unlocked'    => $distinctSubjects >= 5,
                ],
            ];

            $achievements = array_map(function (array $item) {
                $unlocked = (bool) $item['unlocked'];
                $target = max(1, (int) $item['target']);
                $current = (int) $item['current'];

                if ($unlocked) {
                    $progressPercent = 100;
                } elseif (isset($item['custom_progress'])) {
                    $progressPercent = (int) max(0, min(99, $item['custom_progress']));
                } else {
                    $progressPercent = (int) max(0, min(99, round(($current / $target) * 100)));
                }

                return [
                    'id'               => $item['id'],
                    'title'            => $item['title'],
                    'description'      => $item['description'],
                    'category'         => $item['category'],
                    'icon'             => $item['icon'],
                    'current'          => $current,
                    'target'           => $target,
                    'unit'             => $item['unit'] ?? '',
                    'progress_percent' => $progressPercent,
                    'unlocked'         => $unlocked,
                ];
            }, $catalog);

            $totalCount = count($achievements);
            $unlockedCount = count(array_filter($achievements, fn($a) => $a['unlocked']));
            $completionPercent = $totalCount > 0 ? (int) round(($unlockedCount / $totalCount) * 100) : 0;

            return response()->json([
                'success' => true,
                'data'    => [
                    'summary' => [
                        'unlocked_count'       => $unlockedCount,
                        'total_count'          => $totalCount,
                        'completion_percent'   => $completionPercent,
                        'total_answered'       => $totalAnswered,
                        'distinct_correct'     => $distinctCorrect,
                        'accuracy'             => $accuracy,
                        'streak_days'          => $realStreak,
                        'distinct_subjects'    => $distinctSubjects,
                        'completed_exams_count'=> $completedExamsCount,
                    ],
                    'achievements' => $achievements,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al evaluar logros académicos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
