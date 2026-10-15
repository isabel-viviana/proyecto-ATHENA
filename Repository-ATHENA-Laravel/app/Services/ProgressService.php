<?php

namespace App\Services;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\UserTopicPerformance;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProgressService
{
    /**
     * Obtiene el historial de simulacros completados del usuario.
     */
    public function getHistory(int $userId): array
    {
        $attempts = ExamAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->orderBy('finished_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return $attempts->map(function ($attempt) {
            return [
                'id' => $attempt->id,
                'fecha' => $attempt->finished_at ? $attempt->finished_at->toDateTimeString() : $attempt->started_at->toDateTimeString(),
                'tipo' => $attempt->mode,
                'cantidad_preguntas' => $attempt->correct_answers + $attempt->incorrect_answers + $attempt->omitted_answers,
                'correctas' => $attempt->correct_answers,
                'incorrectas' => $attempt->incorrect_answers,
                'omitidas' => $attempt->omitted_answers,
                'porcentaje' => $attempt->score,
                'tiempo_empleado_segundos' => $attempt->duration_seconds,
                'estado' => $attempt->status,
            ];
        })->toArray();
    }

    /**
     * Obtiene las estadísticas generales del estudiante.
     */
    public function getGeneralStats(int $userId): array
    {
        $attempts = ExamAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->get();

        $totalCompleted = $attempts->count();

        if ($totalCompleted === 0) {
            return [
                'total_simulacros_completados' => 0,
                'total_preguntas_respondidas' => 0,
                'total_correctas' => 0,
                'total_incorrectas' => 0,
                'total_omitidas' => 0,
                'porcentaje_global_aciertos' => 0,
                'promedio_porcentaje_simulacro' => 0,
                'mejor_resultado' => 0,
                'peor_resultado' => 0,
            ];
        }

        $totalCorrect = (int) $attempts->sum('correct_answers');
        $totalIncorrect = (int) $attempts->sum('incorrect_answers');
        $totalOmitted = (int) $attempts->sum('omitted_answers');
        $totalQuestions = $totalCorrect + $totalIncorrect + $totalOmitted;

        $globalSuccessRate = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 2) : 0;
        $averagePercentage = round($attempts->avg('score'), 2);
        $bestResult = (int) $attempts->max('score');
        $worstResult = (int) $attempts->min('score');

        return [
            'total_simulacros_completados' => $totalCompleted,
            'total_preguntas_respondidas' => $totalQuestions,
            'total_correctas' => $totalCorrect,
            'total_incorrectas' => $totalIncorrect,
            'total_omitidas' => $totalOmitted,
            'porcentaje_global_aciertos' => $globalSuccessRate,
            'promedio_porcentaje_simulacro' => $averagePercentage,
            'mejor_resultado' => $bestResult,
            'peor_resultado' => $worstResult,
        ];
    }

    /**
     * Obtiene el rendimiento del estudiante en una materia específica.
     */
    public function getSubjectPerformance(int $userId, int $subjectId): array
    {
        $subject = Subject::findOrFail($subjectId);

        $attemptIds = ExamAttempt::where('user_id', $userId)
            ->where('status', 'completed')
            ->pluck('id');

        $answers = AttemptAnswer::whereIn('exam_attempt_id', $attemptIds)
            ->whereHas('question', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            })
            ->get();

        $totalQuestions = $answers->count();
        $correct = $answers->where('is_correct', true)->whereNotNull('selected_option_id')->count();
        $incorrect = $answers->where('is_correct', false)->whereNotNull('selected_option_id')->count();
        $omitted = $answers->whereNull('selected_option_id')->count();

        $percentage = $totalQuestions > 0 ? round(($correct / $totalQuestions) * 100, 2) : 0;

        $level = match (true) {
            $percentage >= 80 => 'Superior',
            $percentage >= 65 => 'Alto',
            $percentage >= 45 => 'Básico',
            default => 'Bajo',
        };

        return [
            'subject_id' => $subject->id,
            'subject_name' => $subject->name,
            'subject_slug' => $subject->slug,
            'total_preguntas' => $totalQuestions,
            'correctas' => $correct,
            'incorrectas' => $incorrect,
            'omitidas' => $omitted,
            'porcentaje_aciertos' => $percentage,
            'nivel_desempeno' => $level,
        ];
    }

    /**
     * Obtiene el rendimiento del estudiante en un tema específico utilizando `user_topic_performance`.
     */
    public function getTopicPerformance(int $userId, int $topicId): array
    {
        $topic = Topic::with('subject')->findOrFail($topicId);

        $performance = UserTopicPerformance::where('user_id', $userId)
            ->where('topic_id', $topicId)
            ->first();

        if (!$performance) {
            return [
                'topic_id' => $topic->id,
                'topic_name' => $topic->name,
                'subject_id' => $topic->subject_id,
                'subject_name' => $topic->subject->name ?? '',
                'total_preguntas' => 0,
                'correctas' => 0,
                'incorrectas' => 0,
                'omitidas' => 0,
                'porcentaje_aciertos' => 0,
                'mastery_score' => 0.00,
            ];
        }

        $total = $performance->correct_count + $performance->incorrect_count + $performance->omitted_count;
        $percentage = $total > 0 ? round(($performance->correct_count / $total) * 100, 2) : 0;

        return [
            'topic_id' => $topic->id,
            'topic_name' => $topic->name,
            'subject_id' => $topic->subject_id,
            'subject_name' => $topic->subject->name ?? '',
            'total_preguntas' => $total,
            'correctas' => $performance->correct_count,
            'incorrectas' => $performance->incorrect_count,
            'omitidas' => $performance->omitted_count,
            'porcentaje_aciertos' => $percentage,
            'mastery_score' => (float) $performance->mastery_score,
        ];
    }

    /**
     * Obtiene el resumen general para el dashboard del estudiante.
     */
    public function getDashboardOverview(int $userId): array
    {
        $stats = $this->getGeneralStats($userId);
        $recentExams = array_slice($this->getHistory($userId), 0, 5);

        // Desglose por todas las materias
        $subjects = Subject::all()->map(function ($subject) use ($userId) {
            return $this->getSubjectPerformance($userId, $subject->id);
        })->toArray();

        // Temas evaluados del usuario
        $performances = UserTopicPerformance::where('user_id', $userId)
            ->with(['topic.subject'])
            ->get();

        $strongTopics = $performances->filter(fn($p) => $p->mastery_score >= 0.70)
            ->sortByDesc('mastery_score')
            ->take(5)
            ->map(fn($p) => [
                'topic_id' => $p->topic_id,
                'topic_name' => $p->topic->name ?? '',
                'subject_name' => $p->topic->subject->name ?? '',
                'mastery_score' => (float) $p->mastery_score,
            ])->values()->toArray();

        $weakTopics = $performances->filter(fn($p) => $p->mastery_score < 0.70)
            ->sortBy('mastery_score')
            ->take(5)
            ->map(fn($p) => [
                'topic_id' => $p->topic_id,
                'topic_name' => $p->topic->name ?? '',
                'subject_name' => $p->topic->subject->name ?? '',
                'mastery_score' => (float) $p->mastery_score,
            ])->values()->toArray();

        return [
            'resumen_general' => $stats,
            'materias' => $subjects,
            'temas_fuertes' => $strongTopics,
            'temas_debiles' => $weakTopics,
            'ultimos_simulacros' => $recentExams,
        ];
    }
}
