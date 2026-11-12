<?php

namespace App\Services;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\IqpeQuestionResolution;
use App\Models\IqpeVerification;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IqpeVerificationService
{
    /**
     * Analiza la integridad de la clave oficial de una pregunta exclusivamente en memoria/BD de Laravel.
     * Nunca debe ser invocado desde los prompts o DTOs enviados a Gemini.
     *
     * Estados retornados:
     * - valid: key_status === 'verified' y exactamente 1 opción con is_correct === true (A/B/C/D).
     * - unreliable: clave disputada, verificada pero con 0 o >1 opciones correctas, o unverified con marcas is_correct.
     * - absent: sin clave oficial (key_status === 'unverified' o vacío, y 0 opciones marcadas is_correct === true).
     *
     * @return array{state:'valid'|'unreliable'|'absent', has_official_key:bool, official_letter:?string, reason:?string}
     */
    public function inspectOfficialKey(Question $question): array
    {
        $question->loadMissing(['options']);

        $keyStatus = strtolower(trim((string) $question->key_status));
        $options = $question->options;
        $correctOptions = $options->filter(fn ($opt) => $opt->is_correct === true)->values();
        $correctCount = $correctOptions->count();

        if ($keyStatus === 'disputed') {
            return [
                'state'            => 'unreliable',
                'has_official_key' => true,
                'official_letter'  => $correctCount === 1
                    ? strtoupper(trim((string) $correctOptions->first()->option_letter))
                    : null,
                'reason'           => 'La clave oficial se encuentra en estado "disputed" y su integridad no es confiable.',
            ];
        }

        if ($keyStatus === 'verified') {
            if ($correctCount !== 1) {
                return [
                    'state'            => 'unreliable',
                    'has_official_key' => true,
                    'official_letter'  => null,
                    'reason'           => "Clave oficial incompleta o ambigua: key_status='verified', pero existen {$correctCount} opciones marcadas como correctas (se requiere exactamente 1).",
                ];
            }

            $letter = strtoupper(trim((string) $correctOptions->first()->option_letter));
            if (! in_array($letter, ['A', 'B', 'C', 'D'], true)) {
                return [
                    'state'            => 'unreliable',
                    'has_official_key' => true,
                    'official_letter'  => null,
                    'reason'           => "Clave oficial no válida: la opción marcada posee una letra desconocida ('{$letter}').",
                ];
            }

            return [
                'state'            => 'valid',
                'has_official_key' => true,
                'official_letter'  => $letter,
                'reason'           => null,
            ];
        }

        if ($correctCount > 0) {
            $letters = $correctOptions->map(fn ($o) => strtoupper(trim((string) $o->option_letter)))->implode(', ');
            return [
                'state'            => 'unreliable',
                'has_official_key' => true,
                'official_letter'  => $correctCount === 1 ? $letters : null,
                'reason'           => "Clave oficial no confiable: key_status='" . ($keyStatus ?: 'unverified') . "', pero hay {$correctCount} opción(es) marcadas como correctas ({$letters}) sin verificación confirmada.",
            ];
        }

        return [
            'state'            => 'absent',
            'has_official_key' => false,
            'official_letter'  => null,
            'reason'           => null,
        ];
    }

    /**
     * Clasifica las preguntas de un documento en Escenario A (clave oficial válida o no confiable)
     * y Escenario B (sin clave oficial -> requieren R3 independiente).
     *
     * @param Collection<int, Question> $questions
     * @return array{scenario_a_ids:array<int, string>, scenario_b_ids:array<int, string>, key_reports:array<string, array>}
     */
    public function classifyQuestionsForVerification(Collection $questions): array
    {
        $scenarioAIds = [];
        $scenarioBIds = [];
        $keyReports = [];

        foreach ($questions as $question) {
            $report = $this->inspectOfficialKey($question);
            $keyReports[$question->id] = $report;

            if ($report['state'] === 'absent') {
                $scenarioBIds[] = $question->id;
            } else {
                $scenarioAIds[] = $question->id;
            }
        }

        return [
            'scenario_a_ids' => $scenarioAIds,
            'scenario_b_ids' => $scenarioBIds,
            'key_reports'    => $keyReports,
        ];
    }

    /**
     * Ejecuta la comparación final y persiste los registros idempotentes en iqpe_verifications.
     *
     * @param IqpeDocumentJob $job
     * @param Collection<int, Question> $questions Colección de preguntas con opciones cargadas (incluyendo is_correct)
     * @param bool $persistFailed Si es false, omite crear verificación definitiva en preguntas con rondas técnicas fallidas
     * @return array{total:int, verified_candidate:int, verified_candidate_no_official_key:int, requires_admin_review:int, pending_or_failed:int}
     */
    public function verifyJobQuestions(
        IqpeDocumentJob $job,
        Collection $questions,
        bool $persistFailed = false
    ): array {
        $stats = [
            'total'                              => $questions->count(),
            'verified_candidate'                 => 0,
            'verified_candidate_no_official_key' => 0,
            'requires_admin_review'              => 0,
            'pending_or_failed'                  => 0,
        ];

        $resolutionsByQuestion = IqpeQuestionResolution::where('job_id', $job->id)
            ->get()
            ->groupBy('question_id');

        foreach ($questions as $question) {
            $keyInfo = $this->inspectOfficialKey($question);
            $qResolutions = ($resolutionsByQuestion->get($question->id) ?? collect())->keyBy('round_number');

            $r1 = $qResolutions->get(1);
            $r2 = $qResolutions->get(2);
            $r3 = $keyInfo['state'] === 'absent' ? $qResolutions->get(3) : null;

            $evaluation = $this->evaluateQuestion($keyInfo, $r1, $r2, $r3);

            if ($evaluation['verdict'] === 'resolution_failed') {
                $stats['pending_or_failed']++;
                if (! $persistFailed) {
                    continue;
                }
            }

            $this->persistVerification($job->id, $question->id, $evaluation);

            if ($evaluation['verdict'] === 'verified_candidate') {
                $stats['verified_candidate']++;
            } elseif ($evaluation['verdict'] === 'verified_candidate_no_official_key') {
                $stats['verified_candidate_no_official_key']++;
            } else {
                $stats['requires_admin_review']++;
            }
        }

        $totalVerifiedCandidates = $stats['verified_candidate'] + $stats['verified_candidate_no_official_key'];

        $job->update([
            'total_questions_detected'    => $stats['total'],
            'total_questions_verified'    => $totalVerifiedCandidates,
            'total_questions_discrepancy' => $stats['requires_admin_review'],
        ]);

        IqpeExecutionLog::record(
            $job->id,
            'verification',
            $stats['pending_or_failed'] > 0 ? 'warning' : 'info',
            "Verificación IQPE procesada: {$stats['verified_candidate']} candidatas con clave oficial, {$stats['verified_candidate_no_official_key']} candidatas sin clave oficial (R1=R2=R3), {$stats['requires_admin_review']} enviadas a revisión administrativa" . ($stats['pending_or_failed'] > 0 ? ", {$stats['pending_or_failed']} pendientes por fallo técnico." : '.'),
            $stats
        );

        return $stats;
    }

    /**
     * Evalúa una pregunta específica aplicando estrictamente las reglas de Escenario A y Escenario B.
     *
     * @return array<string, mixed>
     */
    public function evaluateQuestion(
        array $keyInfo,
        ?IqpeQuestionResolution $r1,
        ?IqpeQuestionResolution $r2,
        ?IqpeQuestionResolution $r3 = null
    ): array {
        $r1Letter = $this->extractValidLetter($r1);
        $r2Letter = $this->extractValidLetter($r2);

        $basePayload = [
            'has_official_key'     => $keyInfo['has_official_key'],
            'official_key_letter'  => $keyInfo['official_letter'],
            'resolution_1_id'      => $r1?->id,
            'resolution_1_letter'  => $r1Letter,
            'resolution_2_id'      => $r2?->id,
            'resolution_2_letter'  => $r2Letter,
            'resolution_3_id'      => null,
            'resolution_3_letter'  => null,
            'is_approved_by_admin' => false,
        ];

        // =====================================================================
        // ESCENARIO A: LA PREGUNTA TIENE CLAVE OFICIAL (O CLAVE NO CONFIABLE)
        // =====================================================================
        if ($keyInfo['state'] === 'unreliable') {
            return array_merge($basePayload, [
                'verdict'               => 'unreliable_official_key',
                'recommended_status'    => 'requires_admin_review',
                'requires_admin_review' => true,
                'confidence_index'      => 0.000,
                'discrepancy_details'   => trim(
                    $keyInfo['reason'] . " (Resoluciones independientes: R1=" . ($r1Letter ?? ($r1?->status ?? 'sin_datos'))
                    . ", R2=" . ($r2Letter ?? ($r2?->status ?? 'sin_datos')) . ').'
                ),
            ]);
        }

        if ($keyInfo['state'] === 'valid') {
            $official = (string) $keyInfo['official_letter'];

            if ($this->isMissingOrTechnicalFailure($r1) || $this->isMissingOrTechnicalFailure($r2)) {
                return array_merge($basePayload, [
                    'verdict'               => 'resolution_failed',
                    'recommended_status'    => 'requires_admin_review',
                    'requires_admin_review' => true,
                    'confidence_index'      => 0.000,
                    'discrepancy_details'   => "Fallo técnico en resolución independiente: R1={$this->describeRound($r1)}, R2={$this->describeRound($r2)}. Clave oficial='{$official}'.",
                ]);
            }

            if ($r1->status === 'uncertain' || $r2->status === 'uncertain' || $r1Letter === null || $r2Letter === null) {
                return array_merge($basePayload, [
                    'verdict'               => 'inconclusive',
                    'recommended_status'    => 'requires_admin_review',
                    'requires_admin_review' => true,
                    'confidence_index'      => 0.000,
                    'discrepancy_details'   => "Resolución incierta o sin respuesta concluyente: R1={$this->describeRound($r1)}, R2={$this->describeRound($r2)}. Clave oficial='{$official}'.",
                ]);
            }

            if ($r1Letter !== $r2Letter) {
                return array_merge($basePayload, [
                    'verdict'               => 'ai_disagreement',
                    'recommended_status'    => 'requires_admin_review',
                    'requires_admin_review' => true,
                    'confidence_index'      => 0.250,
                    'discrepancy_details'   => "Desacuerdo entre R1 y R2 con clave oficial: R1='{$r1Letter}', R2='{$r2Letter}', Clave oficial='{$official}'.",
                ]);
            }

            if ($r1Letter !== $official) {
                return array_merge($basePayload, [
                    'verdict'               => 'official_discrepancy',
                    'recommended_status'    => 'requires_admin_review',
                    'requires_admin_review' => true,
                    'confidence_index'      => 0.300,
                    'discrepancy_details'   => "Discrepancia con clave oficial: R1 y R2 coinciden en '{$r1Letter}', pero la clave oficial registrada es '{$official}'. La clave oficial no se modifica automáticamente; requiere revisión administrativa.",
                ]);
            }

            $avgConfidence = $this->computeAverageConfidence([$r1, $r2], 0.950);
            return array_merge($basePayload, [
                'verdict'               => 'verified_candidate',
                'recommended_status'    => 'verified_candidate',
                'requires_admin_review' => false,
                'confidence_index'      => $avgConfidence,
                'discrepancy_details'   => null,
            ]);
        }

        // =====================================================================
        // ESCENARIO B: LA PREGUNTA NO TIENE CLAVE OFICIAL (REQUIERE R3)
        // =====================================================================
        $r3Letter = $this->extractValidLetter($r3);
        $basePayload['resolution_3_id'] = $r3?->id;
        $basePayload['resolution_3_letter'] = $r3Letter;

        if ($this->isMissingOrTechnicalFailure($r1) || $this->isMissingOrTechnicalFailure($r2) || $this->isMissingOrTechnicalFailure($r3)) {
            return array_merge($basePayload, [
                'verdict'               => 'resolution_failed',
                'recommended_status'    => 'requires_admin_review',
                'requires_admin_review' => true,
                'confidence_index'      => 0.000,
                'discrepancy_details'   => "Sin clave oficial y fallo/ausencia técnica en alguna ronda independiente: R1={$this->describeRound($r1)}, R2={$this->describeRound($r2)}, R3={$this->describeRound($r3)}.",
            ]);
        }

        if (
            $r1->status === 'uncertain' || $r2->status === 'uncertain' || $r3->status === 'uncertain'
            || $r1Letter === null || $r2Letter === null || $r3Letter === null
        ) {
            return array_merge($basePayload, [
                'verdict'               => 'inconclusive',
                'recommended_status'    => 'requires_admin_review',
                'requires_admin_review' => true,
                'confidence_index'      => 0.000,
                'discrepancy_details'   => "Sin clave oficial y al menos una resolución fue incierta: R1={$this->describeRound($r1)}, R2={$this->describeRound($r2)}, R3={$this->describeRound($r3)}.",
            ]);
        }

        if ($r1Letter !== $r2Letter || $r2Letter !== $r3Letter) {
            return array_merge($basePayload, [
                'verdict'               => 'ai_disagreement',
                'recommended_status'    => 'requires_admin_review',
                'requires_admin_review' => true,
                'confidence_index'      => 0.300,
                'discrepancy_details'   => "Sin clave oficial y desacuerdo entre resoluciones independientes: R1='{$r1Letter}', R2='{$r2Letter}', R3='{$r3Letter}'. R3 no arbitra respuestas; requiere revisión administrativa.",
            ]);
        }

        $avgConfidence = min(0.950, $this->computeAverageConfidence([$r1, $r2, $r3], 0.850));

        return array_merge($basePayload, [
            'verdict'               => 'verified_candidate_no_official_key',
            'recommended_status'    => 'verified_candidate',
            'requires_admin_review' => true,
            'confidence_index'      => $avgConfidence,
            'discrepancy_details'   => "Sin clave oficial en el documento: R1, R2 y R3 coinciden independientemente en '{$r1Letter}'. La coincidencia de tres modelos no representa certeza absoluta; la pregunta requiere aprobación administrativa explícita.",
        ]);
    }

    /**
     * Persiste o actualiza idempotente el registro de verificación respetando UNIQUE(job_id, question_id).
     */
    protected function persistVerification(string $jobId, string $questionId, array $attributes): IqpeVerification
    {
        $existing = IqpeVerification::where('job_id', $jobId)
            ->where('question_id', $questionId)
            ->first();

        if ($existing) {
            $existing->update($attributes);
            return $existing;
        }

        return IqpeVerification::create(array_merge([
            'id'          => (string) Str::uuid(),
            'job_id'      => $jobId,
            'question_id' => $questionId,
        ], $attributes));
    }

    protected function extractValidLetter(?IqpeQuestionResolution $resolution): ?string
    {
        if (! $resolution || $resolution->status !== 'success') {
            return null;
        }

        $letter = strtoupper(trim((string) $resolution->solved_option_letter));
        return in_array($letter, ['A', 'B', 'C', 'D'], true) ? $letter : null;
    }

    protected function isMissingOrTechnicalFailure(?IqpeQuestionResolution $resolution): bool
    {
        if (! $resolution) {
            return true;
        }

        return in_array($resolution->status, ['failed', 'timeout'], true);
    }

    protected function describeRound(?IqpeQuestionResolution $resolution): string
    {
        if (! $resolution) {
            return 'sin_registro';
        }

        if ($resolution->status === 'success' && ! empty($resolution->solved_option_letter)) {
            return strtoupper(trim((string) $resolution->solved_option_letter));
        }

        return $resolution->status;
    }

    /**
     * @param array<int, IqpeQuestionResolution> $resolutions
     */
    protected function computeAverageConfidence(array $resolutions, float $fallback): float
    {
        $scores = [];
        foreach ($resolutions as $res) {
            if ($res->confidence_score !== null) {
                $scores[] = (float) $res->confidence_score;
            }
        }

        if (empty($scores)) {
            return round($fallback, 3);
        }

        $avg = array_sum($scores) / count($scores);
        return round(max(0.000, min(1.000, $avg)), 3);
    }
}

