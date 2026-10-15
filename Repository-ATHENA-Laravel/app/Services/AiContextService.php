<?php

namespace App\Services;

class AiContextService
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Obtiene los datos de rendimiento académico del usuario desde MySQL.
     */
    public function buildAcademicContext(int $userId): array
    {
        $overview = $this->progressService->getDashboardOverview($userId);

        return [
            'stats' => $overview['resumen_general'] ?? [],
            'subjects' => $overview['materias'] ?? [],
            'strong_topics' => $overview['temas_fuertes'] ?? [],
            'weak_topics' => $overview['temas_debiles'] ?? [],
            'recent_exams_count' => count($overview['ultimos_simulacros'] ?? []),
        ];
    }

    /**
     * Sintetiza el contexto académico en un texto estructurado y compacto para el prompt de la IA.
     */
    public function formatContextForPrompt(array $contextData): string
    {
        $stats = $contextData['stats'] ?? [];
        $subjects = $contextData['subjects'] ?? [];
        $strongTopics = $contextData['strong_topics'] ?? [];
        $weakTopics = $contextData['weak_topics'] ?? [];

        $totalExams = $stats['total_simulacros_completados'] ?? 0;
        $globalRate = $stats['porcentaje_global_aciertos'] ?? 0;

        $lines = [];
        $lines[] = "--- CONTEXTO ACADÉMICO DEL ESTUDIANTE (MySQL) ---";
        $lines[] = "- Simulacros completados: {$totalExams}";
        $lines[] = "- Porcentaje global de aciertos: {$globalRate}%";

        if (!empty($subjects)) {
            $lines[] = "- Desempeño por materias:";
            foreach ($subjects as $s) {
                $name = $s['subject_name'] ?? 'Materia';
                $pct = $s['porcentaje_aciertos'] ?? 0;
                $level = $s['nivel_desempeno'] ?? 'N/A';
                $lines[] = "  * {$name}: {$pct}% (Nivel: {$level})";
            }
        }

        if (!empty($strongTopics)) {
            $topicNames = array_column($strongTopics, 'topic_name');
            $lines[] = "- Temas fuertes: " . implode(', ', $topicNames);
        }

        if (!empty($weakTopics)) {
            $topicNames = array_column($weakTopics, 'topic_name');
            $lines[] = "- Temas débiles (Requieren refuerzo prioritario): " . implode(', ', $topicNames);
        }

        $lines[] = "--------------------------------------------------";

        return implode("\n", $lines);
    }
}
