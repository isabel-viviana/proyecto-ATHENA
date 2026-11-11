<?php

namespace App\Services;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\IqpeQualityAudit;
use App\Models\IqpeQuestionResolution;
use App\Models\IqpeVerification;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class IqpeQualityAuditService
{
    /** @var array<string, array{exists:bool, valid_image:bool, sha256:?string}> */
    protected array $storageCheckCache = [];

    public function __construct(
        protected SupabaseStorageService $storageService,
        protected IqpeVerificationService $verificationService,
        protected IqpeDuplicateDetectionService $duplicateDetector
    ) {}

    /**
     * Ejecuta la auditoría integral de calidad y detección de duplicados sobre todas las preguntas de un trabajo IQPE.
     * Es idempotente y nunca elimina ni fusiona preguntas, opciones, estímulos ni historiales estudiantiles.
     *
     * @param IqpeDocumentJob $job
     * @param Collection<int, Question> $questions
     * @param Collection<int, Question>|null $candidatePool Pool opcional para pruebas unitarias sin consultar todo el banco
     * @return array<string, int>
     */
    public function auditJobQuestions(
        IqpeDocumentJob $job,
        Collection $questions,
        ?Collection $candidatePool = null
    ): array {
        $this->storageCheckCache = [];

        $questions->each(function (Question $q) {
            $q->loadMissing([
                'options',
                'stimulus.confirmedMediaAssets',
                'stimulus.mediaAssets',
                'confirmedMediaAssets',
                'mediaAssets',
                'subject',
            ]);
        });

        $resolutionsByQ = IqpeQuestionResolution::where('job_id', $job->id)
            ->get()
            ->groupBy('question_id');

        $verificationsByQ = IqpeVerification::where('job_id', $job->id)
            ->get()
            ->keyBy('question_id');

        $duplicateResults = $this->duplicateDetector->analyzeBatch($questions, $candidatePool);

        $stats = [
            'total_audited'            => $questions->count(),
            'ready_for_approval_count' => 0,
            'requires_review_count'    => 0,
            'blocked_critical_count'   => 0,
            'duplicate_risk_count'     => 0,
            'exact_duplicate_count'    => 0,
            'probable_duplicate_count' => 0,
            'related_question_count'   => 0,
            'critical_findings_count'  => 0,
            'high_findings_count'      => 0,
        ];

        $verifiedAndCleanCount = 0;
        $requiresAdminReviewTotal = 0;

        foreach ($questions as $question) {
            $qResolutions = ($resolutionsByQ->get($question->id) ?? collect())->keyBy('round_number');
            $verification = $verificationsByQ->get($question->id);
            $duplicateReport = $duplicateResults[$question->id] ?? [
                'classification'              => 'independent',
                'is_duplicate_risk'           => false,
                'involves_published_question' => false,
                'primary_candidate_id'        => null,
                'matches'                     => [],
            ];

            $auditPayload = $this->evaluateQuestionQuality(
                $question,
                $qResolutions,
                $verification,
                $duplicateReport
            );

            $this->persistQualityAudit($job->id, $question->id, $auditPayload);

            // Actualizar estadísticas de clasificación por pregunta (sin bloquear el resto del documento)
            $classification = $auditPayload['audit_warnings']['overall_classification'];
            if ($classification === 'ready_for_approval') {
                $stats['ready_for_approval_count']++;
            } elseif ($classification === 'blocked_critical') {
                $stats['blocked_critical_count']++;
                $stats['requires_review_count']++;
            } else {
                $stats['requires_review_count']++;
            }

            if ($auditPayload['duplicate_risk_flag']) {
                $stats['duplicate_risk_count']++;
            }
            if ($duplicateReport['classification'] === 'exact_duplicate') {
                $stats['exact_duplicate_count']++;
            } elseif ($duplicateReport['classification'] === 'probable_duplicate') {
                $stats['probable_duplicate_count']++;
            } elseif ($duplicateReport['classification'] === 'related_question') {
                $stats['related_question_count']++;
            }

            foreach ($auditPayload['audit_warnings']['findings'] as $f) {
                if ($f['severity'] === 'critical') {
                    $stats['critical_findings_count']++;
                } elseif ($f['severity'] === 'high') {
                    $stats['high_findings_count']++;
                }
            }

            // Si la auditoría detectó bloqueantes críticos/altos o riesgo de duplicado,
            // impedir que la verificación quede recomendada para aprobación sin alterar el veredicto original de R1/R2/R3.
            $hasBlockingIssue = ! $auditPayload['passed_quality_check'];
            if ($verification) {
                if ($hasBlockingIssue && $verification->recommended_status === 'verified_candidate') {
                    $detailPrefix = $verification->discrepancy_details ? ($verification->discrepancy_details . ' | ') : '';
                    $verification->update([
                        'recommended_status'    => 'requires_admin_review',
                        'requires_admin_review' => true,
                        'discrepancy_details'   => $detailPrefix . 'Bloqueada por auditoría de calidad/duplicados: revise los hallazgos en iqpe_quality_audits antes de aprobar.',
                    ]);
                }

                if (! $hasBlockingIssue && $verification->recommended_status === 'verified_candidate') {
                    $verifiedAndCleanCount++;
                } else {
                    $requiresAdminReviewTotal++;
                }
            } elseif ($hasBlockingIssue) {
                $requiresAdminReviewTotal++;
            }
        }

        // Actualizar contadores globales del trabajo manteniendo distinción por pregunta
        if ($verificationsByQ->isNotEmpty()) {
            $job->update([
                'total_questions_verified'    => $verifiedAndCleanCount,
                'total_questions_discrepancy' => $requiresAdminReviewTotal,
            ]);
        }

        IqpeExecutionLog::record(
            $job->id,
            'quality_audit',
            ($stats['blocked_critical_count'] > 0 || $stats['duplicate_risk_count'] > 0) ? 'warning' : 'info',
            "Auditoría de calidad y duplicados completada: {$stats['ready_for_approval_count']}/{$stats['total_audited']} listas en calidad, {$stats['requires_review_count']} requieren revisión ({$stats['blocked_critical_count']} críticas, {$stats['duplicate_risk_count']} posibles duplicados).",
            $stats
        );

        return $stats;
    }

    /**
     * Evalúa los 12 criterios de calidad y el reporte de duplicados para una sola pregunta.
     *
     * @param Collection<int, IqpeQuestionResolution> $resolutionsByRound
     * @param array<string, mixed> $duplicateReport
     * @return array<string, mixed>
     */
    public function evaluateQuestionQuality(
        Question $question,
        Collection $resolutionsByRound,
        ?IqpeVerification $verification,
        array $duplicateReport
    ): array {
        $findings = [];
        $statementScore = 1.000;
        $optionsScore = 1.000;
        $mediaStatus = 'none';

        // 1. Enunciado vacío o incompleto
        $stmt = trim((string) $question->statement);
        if ($stmt === '' || mb_strtolower($stmt) === 'pregunta sin enunciado') {
            $statementScore = 0.000;
            $findings[] = $this->makeFinding(
                'empty_statement',
                'critical',
                'El enunciado de la pregunta está vacío o sin contenido extraído.',
                ['statement_length' => mb_strlen($stmt)],
                'Completar o re-extraer manualmente el enunciado desde el PDF original.'
            );
        } elseif (mb_strlen($stmt) < 15) {
            $statementScore = min($statementScore, 0.350);
            $findings[] = $this->makeFinding(
                'incomplete_statement',
                'high',
                'El enunciado es excesivamente corto (' . mb_strlen($stmt) . ' caracteres) y podría estar truncado.',
                ['statement_preview' => $stmt, 'length' => mb_strlen($stmt)],
                'Verificar en el cuadernillo original que el enunciado esté completo.'
            );
        } elseif (mb_strlen($stmt) < 35 && empty($question->stimulus_id)) {
            $statementScore = min($statementScore, 0.500);
            $findings[] = $this->makeFinding(
                'short_statement_without_stimulus',
                'high',
                'Enunciado breve (' . mb_strlen($stmt) . ' caracteres) sin estímulo compartido asociado.',
                ['statement_preview' => $stmt, 'length' => mb_strlen($stmt)],
                'Confirmar si la pregunta depende de un contexto o lectura no asociada.'
            );
        } elseif (preg_match('/[,;]\s*$/u', $stmt)) {
            $statementScore = min($statementScore, 0.750);
            $findings[] = $this->makeFinding(
                'truncated_statement_punctuation',
                'medium',
                'El enunciado termina con puntuación abierta, posible corte en extracción.',
                ['statement_tail' => mb_substr($stmt, -40)],
                'Revisar la redacción final del enunciado.'
            );
        }

        // 2 & 3. Opciones incompletas, repetidas o con texto vacío
        $options = $question->options->sortBy('option_letter')->values();
        if ($options->count() !== 4) {
            $optionsScore = min($optionsScore, 0.200);
            $findings[] = $this->makeFinding(
                'incomplete_options',
                'critical',
                "La pregunta posee {$options->count()} opción(es) en lugar de las 4 obligatorias (A, B, C, D).",
                ['options_count' => $options->count(), 'letters' => $options->pluck('option_letter')->values()->all()],
                'Agregar o restaurar las opciones faltantes A, B, C y D.'
            );
        }

        $lettersSeen = [];
        $normalizedOptionTexts = [];
        $hasGraphicOptionIssue = false;

        foreach ($options as $opt) {
            $letter = strtoupper(trim((string) $opt->option_letter));
            $optText = trim((string) $opt->option_text);
            $hasOptImg = ! empty($opt->option_image_path);
            if (in_array($letter, ['A', 'B', 'C', 'D'], true)) {
                $lettersSeen[$letter] = true;
            }

            if ($optText === '' && ! $hasOptImg) {
                $optionsScore = min($optionsScore, 0.250);
                $findings[] = $this->makeFinding(
                    'empty_option',
                    'critical',
                    "La opción {$letter} está vacía y no tiene imagen asociada.",
                    ['option_letter' => $letter],
                    "Completar el texto o asociar la imagen correspondiente a la opción {$letter}."
                );
            } elseif (! $hasOptImg && preg_match('/^(opci[oó]n(\s+gr[aá]fica)?|gr[aá]fica|figura|dise[ñn]o|imagen)\s+[A-D]$/iu', $optText)) {
                $optionsScore = min($optionsScore, 0.250);
                $hasGraphicOptionIssue = true;
                $findings[] = $this->makeFinding(
                    'missing_graphic_option',
                    'critical',
                    "La opción {$letter} ('{$optText}') es un marcador gráfico sin imagen asociada.",
                    ['option_letter' => $letter, 'option_text' => $optText],
                    "Asociar el archivo de imagen correspondiente a la opción {$letter}."
                );
            }

            if ($optText !== '' && ! $hasOptImg) {
                $normOpt = $this->duplicateDetector->normalizeText($optText);
                if ($normOpt !== '') {
                    $normalizedOptionTexts[$normOpt][] = $letter;
                }
            }
        }

        if ($options->count() === 4 && count($lettersSeen) !== 4) {
            $optionsScore = min($optionsScore, 0.250);
            $findings[] = $this->makeFinding(
                'invalid_option_letters',
                'critical',
                'No están presentes las cuatro letras reglamentarias A, B, C y D.',
                ['letters_present' => array_keys($lettersSeen)],
                'Normalizar las letras de opción a A, B, C y D.'
            );
        }

        foreach ($normalizedOptionTexts as $normText => $dupLetters) {
            if (count($dupLetters) > 1) {
                $optionsScore = max(0.000, round($optionsScore - 0.300, 3));
                $findings[] = $this->makeFinding(
                    'duplicate_option_text',
                    'high',
                    'Existen opciones con texto repetido (' . implode(', ', $dupLetters) . ').',
                    ['letters' => $dupLetters, 'normalized_text' => $normText],
                    'Revisar si hubo error de extracción al transcribir las opciones repetidas.'
                );
            }
        }

        // 4. Clave oficial / respuesta verificable
        $keyInfo = $this->verificationService->inspectOfficialKey($question);
        if ($keyInfo['state'] === 'unreliable') {
            $findings[] = $this->makeFinding(
                'unreliable_official_key',
                'high',
                $keyInfo['reason'] ?? 'La clave oficial presenta inconsistencias.',
                ['key_status' => $question->key_status, 'official_letter' => $keyInfo['official_letter']],
                'Corregir key_status y exactamente una opción correcta antes de aprobar.'
            );
        } elseif ($keyInfo['state'] === 'absent') {
            $r123Consensus = $verification && $verification->verdict === 'verified_candidate_no_official_key';
            $findings[] = $this->makeFinding(
                'unverifiable_answer_key',
                $r123Consensus ? 'medium' : 'high',
                $r123Consensus
                    ? 'Pregunta sin clave oficial en el documento (R1, R2 y R3 coinciden, pero requiere validación humana).'
                    : 'Pregunta sin clave oficial verificable y sin consenso unánime R1=R2=R3.',
                ['key_status' => $question->key_status, 'verification_verdict' => $verification?->verdict],
                'Verificar manualmente la clave correcta antes de aprobar para publicación.'
            );
        }

        // 5 & 6. Recursos visuales (imágenes, tablas, diagramas)
        $mediaStatus = $this->evaluateVisualAndTabularQuality($question, $hasGraphicOptionIssue, $resolutionsByRound, $findings);

        // 7. Estímulos incompletos o no relacionados
        $this->evaluateStimulusIntegrity($question, $findings);

        // 8. Errores o advertencias de extracción
        $extWarnings = trim((string) ($question->extraction_warnings ?? ''));
        if ($extWarnings !== '') {
            $isSevereWarning = stripos($extWarnings, 'discrepancia') !== false || stripos($extWarnings, 'vacía') !== false;
            $findings[] = $this->makeFinding(
                'extraction_warning',
                $isSevereWarning ? 'high' : 'medium',
                "Advertencia registrada durante extracción: {$extWarnings}",
                ['extraction_warnings' => $extWarnings],
                'Contrastarlo con la página original del documento PDF.'
            );
        }

        // 9, 10, 11 & 12. Explicaciones, consistencia de candidata, rondas R1/R2/R3 y verificación oficial
        $this->evaluateResolutionsAndVerificationConsistency(
            $question,
            $options,
            $keyInfo,
            $resolutionsByRound,
            $verification,
            $findings
        );

        // 13. Detección de duplicados (informativa, nunca elimina)
        $dupClassification = $duplicateReport['classification'] ?? 'independent';
        $isDuplicateRisk = (bool) ($duplicateReport['is_duplicate_risk'] ?? false);
        $primaryCandidateId = $duplicateReport['primary_candidate_id'] ?? null;

        if ($isDuplicateRisk && ! empty($duplicateReport['matches'])) {
            $topMatch = $duplicateReport['matches'][0];
            $involvesPublished = (bool) ($duplicateReport['involves_published_question'] ?? false);
            $severity = ($dupClassification === 'exact_duplicate' && $involvesPublished)
                ? 'critical'
                : ($dupClassification === 'exact_duplicate' || $involvesPublished ? 'high' : 'medium');

            $pubNotice = $involvesPublished
                ? ' ATENCIÓN: Coincide con una pregunta YA PUBLICADA; no modificar ni eliminar el registro publicado.'
                : '';

            $findings[] = $this->makeFinding(
                $dupClassification,
                $severity,
                ($dupClassification === 'exact_duplicate' ? 'Duplicado exacto detectado' : 'Posible duplicado probable detectado')
                    . " frente a la pregunta {$primaryCandidateId} (similitud: " . round(($topMatch['similarity_score'] ?? 0) * 100, 1) . "%).{$pubNotice}",
                [
                    'candidate_question_id' => $primaryCandidateId,
                    'candidate_status'      => $topMatch['candidate_status'] ?? null,
                    'similarity_score'      => $topMatch['similarity_score'] ?? null,
                    'breakdown'             => $topMatch['evidence'] ?? null,
                    'involves_published'    => $involvesPublished,
                ],
                'Comparar ambos reactivos en revisión administrativa y decidir si conservar, rechazar o archivar el borrador.'
            );
        }

        // Clasificación final por pregunta
        $highestSeverity = $this->determineHighestSeverity($findings);
        $hasCriticalOrHigh = in_array($highestSeverity, ['critical', 'high'], true);

        $overallClassification = 'ready_for_approval';
        if ($highestSeverity === 'critical') {
            $overallClassification = 'blocked_critical';
        } elseif ($hasCriticalOrHigh || $isDuplicateRisk) {
            $overallClassification = 'requires_review';
        } elseif ($highestSeverity === 'medium' && $verification && $verification->requires_admin_review) {
            $overallClassification = 'requires_review';
        }

        // Una pregunta con problemas críticos, altos o duplicados pendientes NUNCA pasa el chequeo de calidad
        $passedQualityCheck = ! $hasCriticalOrHigh && ! $isDuplicateRisk;

        return [
            'statement_integrity_score' => round(max(0.0, min(1.0, $statementScore)), 3),
            'options_integrity_score'   => round(max(0.0, min(1.0, $optionsScore)), 3),
            'media_integrity_status'    => $mediaStatus,
            'duplicate_risk_flag'       => $isDuplicateRisk,
            'duplicate_candidate_id'    => $primaryCandidateId,
            'passed_quality_check'      => $passedQualityCheck,
            'audit_warnings'            => [
                'overall_classification' => $overallClassification,
                'highest_severity'       => $highestSeverity,
                'findings_count'         => count($findings),
                'findings'               => $findings,
                'duplicate_assessment'   => $duplicateReport,
            ],
        ];
    }

    /**
     * Evalúa integridad física y referencial de imágenes, tablas y diagramas.
     *
     * @param Collection<int, IqpeQuestionResolution> $resolutionsByRound
     * @param array<int, array<string, mixed>> $findings
     */
    protected function evaluateVisualAndTabularQuality(
        Question $question,
        bool $hasGraphicOptionIssue,
        Collection $resolutionsByRound,
        array &$findings
    ): string {
        if ($hasGraphicOptionIssue) {
            return 'missing_graphic_option';
        }

        $mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');
        $imagePaths = $this->collectQuestionVisualPaths($question);
        $stimulusType = $question->stimulus?->media_type ?? 'none';

        $stmtAndInst = trim((string) $question->statement) . "\n" . trim((string) ($question->stimulus?->instructions ?? ''));
        $referencesVisual = (bool) preg_match(
            '/\b(en\s+la\s+siguiente\s+(imagen|figura|gr[aá]fica|ilustraci[oó]n|historieta|vi[ñn]eta|caricatura|infograf[ií]a|fotograf[ií]a)|observa\s+la\s+(siguiente\s+)?(imagen|figura|gr[aá]fica|historieta|ilustraci[oó]n)|de\s+acuerdo\s+con\s+la\s+(imagen|figura|gr[aá]fica|ilustraci[oó]n|historieta|vi[ñn]eta|caricatura|infograf[ií]a)|seg[uú]n\s+la\s+(imagen|figura|gr[aá]fica|ilustraci[oó]n|historieta|vi[ñn]eta)|en\s+el\s+gr[aá]fico\s+anterior|en\s+la\s+figura\s+se\s+muestra|(observa|seg[uú]n|de\s+acuerdo\s+con|en)\s+el\s+(siguiente\s+)?(diagrama|esquema|mapa|plano|gr[aá]fico))\b/iu',
            $stmtAndInst
        );
        $referencesTable = (bool) preg_match(
            '/\b(en\s+la\s+siguiente\s+tabla|observa\s+la\s+(siguiente\s+)?tabla|de\s+acuerdo\s+con\s+la\s+tabla|seg[uú]n\s+la\s+tabla)\b/iu',
            $stmtAndInst
        );

        $stimContent = trim((string) ($question->stimulus?->content_text ?? ''));
        $stimTrans = trim((string) ($question->stimulus?->visual_transcription ?? ''));
        $qTrans = trim((string) ($question->visual_transcription ?? ''));

        $hasInlineTable = str_contains((string) $question->statement, '|')
            || str_contains($stimContent, '|')
            || $stimTrans !== ''
            || $qTrans !== ''
            || mb_strlen($stimContent) >= 40;

        // Tabla incompleta o faltante
        if (empty($imagePaths) && (($stimulusType === 'table' && $stimContent === '' && $stimTrans === '') || ($referencesTable && ! $hasInlineTable))) {
            $findings[] = $this->makeFinding(
                'incomplete_table_or_diagram',
                'critical',
                'La pregunta o estímulo requiere una tabla de datos que no está presente ni transcrita.',
                ['stimulus_media_type' => $stimulusType, 'stimulus_content_len' => mb_strlen($stimContent)],
                'Transcribir la tabla completa o adjuntar su imagen desde el PDF original.'
            );
            return 'unverified_media';
        }

        // Imagen/diagrama/gráfica requerida pero sin archivos asociados
        if (empty($imagePaths) && (in_array($stimulusType, ['image', 'chart', 'mixed'], true) || $referencesVisual)) {
            $findings[] = $this->makeFinding(
                'missing_referenced_visual_or_table',
                'critical',
                'La pregunta o su estímulo hacen referencia a una imagen, figura, gráfica o diagrama sin archivo visual asociado.',
                ['stimulus_media_type' => $stimulusType],
                'Asociar el recurso visual correspondiente en la pestaña de recursos visuales.'
            );
            return 'unverified_media';
        }

        // Verificar existencia y decodificación binaria real de las imágenes asociadas
        if (! empty($imagePaths)) {
            $missingPaths = [];
            $corruptPaths = [];

            foreach ($imagePaths as $path) {
                $check = $this->checkVisualAssetInStorage($mediaBucket, $path);
                if (! $check['exists']) {
                    $missingPaths[] = $path;
                } elseif (! $check['valid_image']) {
                    $corruptPaths[] = $path;
                }
            }

            if (! empty($missingPaths)) {
                $findings[] = $this->makeFinding(
                    'missing_visual_resource',
                    'critical',
                    'Uno o más recursos visuales asociados no existen en el almacenamiento privado (' . implode(', ', $missingPaths) . ').',
                    ['missing_paths' => $missingPaths],
                    'Re-extraer o volver a subir la imagen faltante al bucket icfes-media.'
                );
                return 'unverified_media';
            }

            if (! empty($corruptPaths)) {
                $findings[] = $this->makeFinding(
                    'corrupt_visual_resource',
                    'critical',
                    'Uno o más archivos visuales asociados están dañados o no son imágenes válidas (' . implode(', ', $corruptPaths) . ').',
                    ['corrupt_paths' => $corruptPaths],
                    'Reemplazar la imagen dañada por una captura legible del PDF.'
                );
                return 'visual_ambiguity';
            }

            // Verificar si alguna ronda marcó incertidumbre visual a pesar de existir el archivo
            foreach ($resolutionsByRound as $res) {
                if ($res->status === 'uncertain' && stripos((string) $res->solution_explanation, 'visual') !== false) {
                    $findings[] = $this->makeFinding(
                        'visual_ambiguity_reported',
                        'high',
                        "La ronda R{$res->round_number} reportó ambigüedad o ilegibilidad en el recurso visual.",
                        ['round_number' => $res->round_number, 'explanation' => $res->solution_explanation],
                        'Verificar manualmente la nitidez y legibilidad de la imagen asociada.'
                    );
                    return 'visual_ambiguity';
                }
            }

            return 'confirmed_valid';
        }

        if ($stimulusType === 'table' && $hasInlineTable) {
            return 'confirmed_valid';
        }

        return 'none';
    }

    /**
     * Evalúa integridad y coherencia temática del estímulo asociado.
     *
     * @param array<int, array<string, mixed>> $findings
     */
    protected function evaluateStimulusIntegrity(Question $question, array &$findings): void
    {
        if (empty($question->stimulus_id)) {
            return;
        }

        $stimulus = $question->stimulus;
        if (! $stimulus) {
            $findings[] = $this->makeFinding(
                'missing_stimulus',
                'critical',
                "La pregunta referencia el estímulo {$question->stimulus_id}, pero el registro no existe.",
                ['stimulus_id' => $question->stimulus_id],
                'Restaurar o desvincular la referencia al estímulo inexistente.'
            );
            return;
        }

        $stimText = trim((string) ($stimulus->content_text ?? ''));
        $stimTrans = trim((string) ($stimulus->visual_transcription ?? ''));
        $hasMedia = ! empty($stimulus->media_path) || $stimulus->mediaAssets->isNotEmpty();

        if ($stimText === '' && $stimTrans === '' && ! $hasMedia) {
            $findings[] = $this->makeFinding(
                'incomplete_stimulus',
                'high',
                "El estímulo '{$stimulus->title}' no contiene texto, transcripción ni recurso visual.",
                ['stimulus_id' => $stimulus->id, 'title' => $stimulus->title],
                'Completar el texto o asociar la imagen del estímulo compartido.'
            );
        } elseif ($stimulus->media_type === 'none' && $stimText !== '' && mb_strlen($stimText) < 25 && $stimTrans === '') {
            $findings[] = $this->makeFinding(
                'short_stimulus_text',
                'medium',
                'El texto del estímulo compartido es muy breve (' . mb_strlen($stimText) . ' caracteres) para ser una lectura/contexto completo.',
                ['stimulus_id' => $stimulus->id, 'length' => mb_strlen($stimText)],
                'Revisar si el texto compartido fue extraído de forma completa.'
            );
        }

        if (! empty($stimulus->subject_id) && ! empty($question->subject_id) && (int) $stimulus->subject_id !== (int) $question->subject_id) {
            $findings[] = $this->makeFinding(
                'unrelated_stimulus_subject',
                'high',
                "El área académica del estímulo ({$stimulus->subject_id}) no coincide con la de la pregunta ({$question->subject_id}).",
                ['stimulus_subject_id' => $stimulus->subject_id, 'question_subject_id' => $question->subject_id],
                'Corregir el área académica o verificar la asociación del estímulo.'
            );
        }
    }

    /**
     * Evalúa explicaciones, consistencia entre respuesta candidata y opciones, problemas de R1/R2/R3 y discrepancias.
     *
     * @param Collection<int, \App\Models\QuestionOption> $options
     * @param array<string, mixed> $keyInfo
     * @param Collection<int, IqpeQuestionResolution> $resolutionsByRound
     * @param array<int, array<string, mixed>> $findings
     */
    protected function evaluateResolutionsAndVerificationConsistency(
        Question $question,
        Collection $options,
        array $keyInfo,
        Collection $resolutionsByRound,
        ?IqpeVerification $verification,
        array &$findings
    ): void {
        $availableLetters = $options->map(fn ($o) => strtoupper(trim((string) $o->option_letter)))->all();

        foreach ($resolutionsByRound as $roundNum => $res) {
            if (in_array($res->status, ['failed', 'timeout'], true)) {
                $findings[] = $this->makeFinding(
                    'solver_execution_failure',
                    'critical',
                    "La ronda R{$roundNum} terminó en estado '{$res->status}'.",
                    ['round_number' => $roundNum, 'status' => $res->status, 'error' => $res->raw_metadata['error'] ?? null],
                    "Reintentar la ronda R{$roundNum} mediante el endpoint de reanudación sin re-extraer el PDF."
                );
                continue;
            }

            if ($res->status === 'uncertain') {
                $findings[] = $this->makeFinding(
                    'solver_uncertain',
                    'high',
                    "La ronda R{$roundNum} determinó incertidumbre: {$res->solution_explanation}",
                    ['round_number' => $roundNum, 'explanation' => $res->solution_explanation],
                    'Revisar el enunciado, opciones y recursos visuales del reactivo.'
                );
                continue;
            }

            $expl = trim((string) $res->solution_explanation);
            if ($expl === '' || mb_strlen($expl) < 15 || $expl === 'Resolución generada sin explicación detallada adicional.') {
                $findings[] = $this->makeFinding(
                    'empty_or_trivial_explanation',
                    'medium',
                    "La explicación pedagógica generada en R{$roundNum} está vacía o es demasiado breve.",
                    ['round_number' => $roundNum, 'explanation_length' => mb_strlen($expl)],
                    'Revisar o redactar una explicación pedagógica clara para el estudiante.'
                );
            } elseif (! empty($res->solved_option_letter)) {
                $declaredLetter = strtoupper(trim((string) $res->solved_option_letter));
                if (preg_match('/(?:la\s+)?(?:respuesta|opci[oó]n)\s+correcta\s+(?:es\s+(?:la\s+)?|[:\-]\s*)([A-D])\b/iu', $expl, $m)) {
                    $mentioned = strtoupper($m[1]);
                    if ($mentioned !== $declaredLetter) {
                        $findings[] = $this->makeFinding(
                            'explanation_option_mismatch',
                            'high',
                            "Inconsistencia en R{$roundNum}: la explicación menciona la opción '{$mentioned}', pero la respuesta seleccionada fue '{$declaredLetter}'.",
                            ['round_number' => $roundNum, 'declared_letter' => $declaredLetter, 'mentioned_letter' => $mentioned],
                            'Revisar manualmente la coherencia entre la explicación y la respuesta candidata.'
                        );
                    }
                }
            }
        }

        // Consistencia de explicación oficial si fue extraída/guardada en Question
        $officialExpl = trim((string) ($question->explanation ?? ''));
        if ($keyInfo['state'] === 'valid' && $officialExpl !== '') {
            if (preg_match('/(?:la\s+)?(?:respuesta|opci[oó]n)\s+correcta\s+(?:es\s+(?:la\s+)?|[:\-]\s*)([A-D])\b/iu', $officialExpl, $m)) {
                $mentioned = strtoupper($m[1]);
                if ($mentioned !== $keyInfo['official_letter']) {
                    $findings[] = $this->makeFinding(
                        'official_explanation_key_mismatch',
                        'high',
                        "La explicación almacenada señala '{$mentioned}', lo cual contradice la clave oficial '{$keyInfo['official_letter']}'.",
                        ['official_key_letter' => $keyInfo['official_letter'], 'mentioned_letter' => $mentioned],
                        'Corregir la explicación o verificar la clave oficial en el documento fuente.'
                    );
                }
            }
        }

        // Verificación y consistencia de respuesta candidata frente a las opciones reales
        if ($verification) {
            if ($verification->verdict === 'official_discrepancy') {
                $findings[] = $this->makeFinding(
                    'official_key_discrepancy',
                    'critical',
                    $verification->discrepancy_details ?: 'Discrepancia entre R1/R2 y la clave oficial registrada.',
                    [
                        'r1_letter'           => $verification->resolution_1_letter,
                        'r2_letter'           => $verification->resolution_2_letter,
                        'official_key_letter' => $verification->official_key_letter,
                    ],
                    'No modificar automáticamente la clave oficial; validar manualmente en el cuadernillo original.'
                );
            } elseif (in_array($verification->verdict, ['ai_disagreement', 'inconclusive'], true)) {
                $findings[] = $this->makeFinding(
                    'verification_disagreement',
                    'high',
                    $verification->discrepancy_details ?: 'Las rondas de resolución presentaron desacuerdo o inconclusión.',
                    [
                        'verdict'   => $verification->verdict,
                        'r1_letter' => $verification->resolution_1_letter,
                        'r2_letter' => $verification->resolution_2_letter,
                        'r3_letter' => $verification->resolution_3_letter,
                    ],
                    'Realizar revisión pedagógica manual antes de aprobar.'
                );
            }

            if (in_array($verification->verdict, ['verified_candidate', 'verified_candidate_no_official_key'], true)) {
                $candidateLetter = $verification->official_key_letter ?: $verification->resolution_1_letter;
                if (empty($candidateLetter) || ! in_array($candidateLetter, $availableLetters, true)) {
                    $findings[] = $this->makeFinding(
                        'candidate_option_inconsistency',
                        'critical',
                        "La respuesta candidata ('" . ($candidateLetter ?? 'null') . "') no corresponde a una opción válida del reactivo.",
                        ['candidate_letter' => $candidateLetter, 'available_letters' => $availableLetters],
                        'Verificar las opciones de respuesta registradas.'
                    );
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function collectQuestionVisualPaths(Question $question): array
    {
        $paths = [];
        $push = function (?string $p) use (&$paths) {
            $clean = trim((string) $p);
            if ($clean !== '' && ! in_array($clean, $paths, true)) {
                $paths[] = $clean;
            }
        };

        if ($question->stimulus) {
            $push($question->stimulus->media_path);
            $stimAssets = $question->stimulus->confirmedMediaAssets->isNotEmpty()
                ? $question->stimulus->confirmedMediaAssets
                : $question->stimulus->mediaAssets;
            foreach ($stimAssets as $a) {
                $push($a->storage_path);
            }
        }

        $push($question->question_image_path);
        $qAssets = $question->confirmedMediaAssets->isNotEmpty()
            ? $question->confirmedMediaAssets
            : $question->mediaAssets;
        foreach ($qAssets as $a) {
            $push($a->storage_path);
        }

        foreach ($question->options as $opt) {
            $push($opt->option_image_path);
        }

        return $paths;
    }

    /**
     * @return array{exists:bool, valid_image:bool, sha256:?string}
     */
    protected function checkVisualAssetInStorage(string $bucket, string $path): array
    {
        $cacheKey = "{$bucket}:{$path}";
        if (isset($this->storageCheckCache[$cacheKey])) {
            return $this->storageCheckCache[$cacheKey];
        }

        $result = ['exists' => false, 'valid_image' => false, 'sha256' => null];
        try {
            if ($this->storageService->exists($bucket, $path)) {
                $bytes = $this->storageService->get($bucket, $path);
                if (! empty($bytes)) {
                    $result['exists'] = true;
                    $imgInfo = @getimagesizefromstring($bytes);
                    if ($imgInfo !== false && ! empty($imgInfo['mime'])) {
                        $result['valid_image'] = true;
                        $result['sha256'] = hash('sha256', $bytes);
                    }
                }
            }
        } catch (Throwable) {
            // Mantener exists=false ante fallo de lectura
        }

        return $this->storageCheckCache[$cacheKey] = $result;
    }

    protected function makeFinding(
        string $type,
        string $severity,
        string $description,
        array $evidence,
        string $recommendation,
        string $reviewStatus = 'pending_review'
    ): array {
        return [
            'type'           => $type,
            'severity'       => $severity,
            'description'    => $description,
            'evidence'       => $evidence,
            'recommendation' => $recommendation,
            'review_status'  => $reviewStatus,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     */
    protected function determineHighestSeverity(array $findings): string
    {
        $order = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1, 'none' => 0];
        $best = 'none';

        foreach ($findings as $f) {
            $sev = $f['severity'] ?? 'low';
            if (($order[$sev] ?? 0) > ($order[$best] ?? 0)) {
                $best = $sev;
            }
        }

        return $best;
    }

    protected function persistQualityAudit(string $jobId, string $questionId, array $attributes): IqpeQualityAudit
    {
        $existing = IqpeQualityAudit::where('job_id', $jobId)
            ->where('question_id', $questionId)
            ->first();

        if ($existing) {
            $existing->update($attributes);
            return $existing;
        }

        return IqpeQualityAudit::create(array_merge([
            'id'          => (string) Str::uuid(),
            'job_id'      => $jobId,
            'question_id' => $questionId,
        ], $attributes));
    }
}
