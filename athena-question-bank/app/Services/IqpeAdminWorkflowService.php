<?php

namespace App\Services;

use App\Models\IqpeAdminDecision;
use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\IqpeQualityAudit;
use App\Models\IqpeQuestionResolution;
use App\Models\IqpeVerification;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Stimulus;
use App\Models\Topic;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IqpeAdminWorkflowService
{
    public function __construct(
        protected SupabaseStorageService $storageService,
        protected IqpeQualityAuditService $qualityAuditService
    ) {}

    /**
     * Resuelve el último trabajo IQPE y sus artefactos asociados (resoluciones, verificación,
     * auditoría de calidad y decisiones administrativas) para una pregunta dada.
     *
     * @return array{
     *     job_id: ?string,
     *     resolutions: Collection<int, IqpeQuestionResolution>,
     *     verification: ?IqpeVerification,
     *     quality_audit: ?IqpeQualityAudit,
     *     decisions: Collection<int, IqpeAdminDecision>
     * }
     */
    public function resolveQuestionIqpeContext(Question $question): array
    {
        $verification = IqpeVerification::where('question_id', $question->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $qualityAudit = IqpeQualityAudit::where('question_id', $question->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $jobId = $verification?->job_id ?? $qualityAudit?->job_id;

        if (! $jobId) {
            $jobId = IqpeQuestionResolution::where('question_id', $question->id)
                ->orderBy('created_at', 'desc')
                ->value('job_id');
        }

        if (! $jobId && $question->source_document_id) {
            $jobId = IqpeDocumentJob::where('source_document_id', $question->source_document_id)
                ->orderBy('run_number', 'desc')
                ->value('id');
        }

        if ($jobId) {
            $verification = IqpeVerification::where('job_id', $jobId)
                ->where('question_id', $question->id)
                ->first() ?? $verification;

            $qualityAudit = IqpeQualityAudit::where('job_id', $jobId)
                ->where('question_id', $question->id)
                ->first() ?? $qualityAudit;
        }

        $resolutions = $jobId
            ? IqpeQuestionResolution::where('job_id', $jobId)
                ->where('question_id', $question->id)
                ->orderBy('round_number', 'asc')
                ->get()
                ->keyBy('round_number')
            : collect();

        $decisions = IqpeAdminDecision::where('question_id', $question->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'job_id'        => $jobId,
            'resolutions'   => $resolutions,
            'verification'  => $verification,
            'quality_audit' => $qualityAudit,
            'decisions'     => $decisions,
        ];
    }

    /**
     * Evalúa en vivo la elegibilidad para Aprobación y Publicación independiente,
     * distinguiendo bloqueos críticos de advertencias que requieren atención humana.
     *
     * @param Collection<int, IqpeAdminDecision> $decisions
     * @return array<string, mixed>
     */
    public function evaluateEligibility(
        Question $question,
        ?IqpeVerification $verification,
        ?IqpeQualityAudit $audit,
        Collection $decisions,
        bool $verifyStorage = false
    ): array {
        $question->loadMissing([
            'options',
            'stimulus.confirmedMediaAssets',
            'stimulus.mediaAssets',
            'confirmedMediaAssets',
            'mediaAssets',
        ]);

        $approveBlockers = [];
        $approveWarnings = [];
        $publishBlockers = [];
        $publishWarnings = [];

        $options = $question->options->sortBy('option_letter')->values();
        $stmt = trim((string) $question->statement);

        // 1. Integridad básica del enunciado y materia
        if ($stmt === '' || mb_strlen($stmt) < 10) {
            $msg = 'El enunciado está vacío o no cumple el mínimo de 10 caracteres.';
            $approveBlockers[] = $msg;
            $publishBlockers[] = $msg;
        }

        if (empty($question->subject_id) || $question->subject_id < 1 || $question->subject_id > 5) {
            $msg = 'El área académica asignada no es válida dentro del marco oficial ICFES (1–5).';
            $approveBlockers[] = $msg;
            $publishBlockers[] = $msg;
        }

        // 2. Integridad de estímulo
        if ($question->stimulus_id) {
            if (! $question->stimulus) {
                $msg = 'El estímulo compartido referenciado no existe en la base de datos.';
                $approveBlockers[] = $msg;
                $publishBlockers[] = $msg;
            } elseif ((int) $question->stimulus->subject_id !== (int) $question->subject_id) {
                $msg = 'El área académica de la pregunta no coincide con la de su estímulo asociado.';
                $approveBlockers[] = $msg;
                $publishBlockers[] = $msg;
            }
        }

        // 3. Integridad de opciones A, B, C, D
        $letters = $options->map(fn ($o) => strtoupper(trim((string) $o->option_letter)))->all();
        if ($options->count() !== 4 || count(array_diff(['A', 'B', 'C', 'D'], $letters)) > 0) {
            $msg = "Se requieren exactamente 4 opciones válidas (A, B, C, D); actualmente tiene {$options->count()}.";
            $approveBlockers[] = $msg;
            $publishBlockers[] = $msg;
        }

        foreach ($options as $opt) {
            $letter = strtoupper(trim((string) $opt->option_letter));
            $optText = trim((string) $opt->option_text);
            $hasImg = ! empty($opt->option_image_path);

            if ($optText === '' && ! $hasImg) {
                $msg = "La opción {$letter} está vacía y no posee recurso visual.";
                $approveBlockers[] = $msg;
                $publishBlockers[] = $msg;
            } elseif (! $hasImg && preg_match('/^(opci[oó]n(\s+gr[aá]fica)?|gr[aá]fica|figura|dise[ñn]o|imagen)\s+[A-D]$/iu', $optText)) {
                $msg = "La opción {$letter} contiene un marcador gráfico sin imagen asociada.";
                $approveBlockers[] = $msg;
                $publishBlockers[] = $msg;
            }
        }

        // 4. Clave oficial / respuesta correcta / clave asignada manualmente
        $correctOptions = $options->filter(fn ($o) => $o->is_correct === true)->values();
        $correctCount = $correctOptions->count();
        $manualKeyAssigned = $this->hasManualKeyDecision($decisions);
        $hasKeyOverrideAfterAudit = $this->hasDecisionAfter(
            $decisions,
            ['key_overridden', 'manual_key_set', 'discrepancy_confirmed'],
            $audit?->updated_at
        );
        $isWithoutOfficialKey = ($question->key_status !== 'verified' && $correctCount === 0)
            || ($verification && ! $verification->has_official_key);

        if ($question->key_status === 'disputed') {
            $msg = 'La clave está en estado "disputed" y requiere calibración manual antes de aprobar o publicar.';
            $approveBlockers[] = $msg;
            $publishBlockers[] = $msg;
        } elseif ($question->key_status === 'verified' && $correctCount !== 1) {
            $msg = "Clave marcada como verificada pero existen {$correctCount} opciones correctas (debe ser exactamente 1).";
            $approveBlockers[] = $msg;
            $publishBlockers[] = $msg;
        } elseif ($correctCount > 1) {
            $msg = "Existen {$correctCount} opciones marcadas como correctas simultáneamente.";
            $approveBlockers[] = $msg;
            $publishBlockers[] = $msg;
        }

        if ($correctCount !== 1 || $question->key_status !== 'verified') {
            $publishBlockers[] = 'No se puede publicar: la pregunta requiere exactamente una opción correcta con clave verificada.';
            if ($question->key_status === 'unverified') {
                $approveWarnings[] = 'Advertencia: La pregunta no tiene clave oficial verificada. Si aprueba ahora, permanecerá en revisión hasta calibrar una clave antes de publicar.';
            }
        } elseif ($manualKeyAssigned) {
            $warn = 'Clave establecida manualmente por un administrador (no proviene de la hoja oficial original del documento).';
            $approveWarnings[] = $warn;
            $publishWarnings[] = $warn;
        }

        // 5. Auditoría de calidad Bloque 5
        $hasDiscrepancy = false;
        $hasDuplicateRisk = (bool) ($audit?->duplicate_risk_flag ?? false);
        $duplicateClassification = $audit?->audit_warnings['duplicate_assessment']['classification'] ?? 'independent';

        if ($verification) {
            if (in_array($verification->verdict, ['official_discrepancy', 'ai_disagreement', 'inconclusive', 'disputed_or_incomplete_key', 'requires_admin_review'], true)
                || $verification->requires_admin_review) {
                $hasDiscrepancy = true;
            }
        }

        if ($audit) {
            $findings = is_array($audit->audit_warnings['findings'] ?? null)
                ? $audit->audit_warnings['findings']
                : [];

            foreach ($findings as $finding) {
                $sev = $finding['severity'] ?? 'low';
                $type = $finding['type'] ?? 'unknown';
                $desc = $finding['description'] ?? 'Hallazgo de auditoría detectado.';

                if ($sev === 'critical') {
                    // Si es discrepancia con clave oficial y ya fue calibrada manualmente tras la auditoría, pasa a advertencia informativa
                    if ($type === 'official_key_discrepancy' && $hasKeyOverrideAfterAudit) {
                        $approveWarnings[] = "Discrepancia oficial calibrada manualmente por administración: {$desc}";
                        $publishWarnings[] = "Discrepancia oficial calibrada manualmente por administración: {$desc}";
                        continue;
                    }

                    $approveBlockers[] = "Problema crítico sin corregir ({$type}): {$desc}";
                    $publishBlockers[] = "Bloqueo crítico pendiente ({$type}): {$desc}";
                } elseif ($sev === 'high') {
                    $approveWarnings[] = "Advertencia de calidad ({$type}): {$desc}";
                    $publishWarnings[] = "Advertencia de calidad ({$type}): {$desc}";
                }
            }
        }

        if ($hasDiscrepancy) {
            $msg = 'Existen discrepancias en verificación IA/clave oficial que requieren revisión humana y observación administrativa.';
            if (! in_array($msg, $approveWarnings, true)) {
                $approveWarnings[] = $msg;
            }
            if (! in_array($msg, $publishWarnings, true)) {
                $publishWarnings[] = $msg;
            }
        }

        if ($hasDuplicateRisk) {
            $involvesPublished = (bool) ($audit?->audit_warnings['duplicate_assessment']['involves_published_question'] ?? false);
            $dupMsg = $involvesPublished
                ? "Riesgo de duplicado ({$duplicateClassification}) contra una pregunta YA PUBLICADA."
                : "Posible duplicado detectado ({$duplicateClassification}). Revise la comparativa antes de aprobar o publicar.";
            $approveWarnings[] = $dupMsg;
            $publishWarnings[] = $dupMsg;
        }

        // 6. Verificación de recursos visuales en Storage (obligatoria antes de publicar o si la auditoría reportó fallo visual)
        $mediaStatus = $audit?->media_integrity_status ?? 'none';
        if (in_array($mediaStatus, ['missing_resource', 'corrupt_resource', 'missing_graphic_option'], true)) {
            $msg = "Integridad visual comprometida ({$mediaStatus}). Verifique o asocie los recursos visuales faltantes.";
            if (! in_array($msg, $approveBlockers, true)) {
                $approveBlockers[] = $msg;
            }
            if (! in_array($msg, $publishBlockers, true)) {
                $publishBlockers[] = $msg;
            }
        }

        if ($verifyStorage && $question->status === 'review') {
            $missingPaths = $this->findMissingMediaPaths($question);
            if (! empty($missingPaths)) {
                $publishBlockers[] = 'Recursos visuales no disponibles en Supabase Storage: ' . implode(', ', $missingPaths);
            }
        }

        // 7. Reglas de estado del ciclo de vida
        if ($question->status === 'review') {
            $approveBlockers[] = 'La pregunta ya fue aprobada previamente.';
        } elseif ($question->status === 'published') {
            $approveBlockers[] = 'La pregunta ya se encuentra publicada.';
            $publishBlockers[] = 'La pregunta ya se encuentra publicada.';
        } elseif ($question->status === 'archived') {
            $approveBlockers[] = 'La pregunta está rechazada/archivada. Devuélvala a revisión antes de aprobar.';
            $publishBlockers[] = 'No se puede publicar una pregunta rechazada/archivada.';
        }

        if ($question->status !== 'review' && $question->status !== 'published') {
            $publishBlockers[] = 'Solo se pueden publicar preguntas previamente aprobadas.';
        } elseif ($question->status === 'review' && $verification && ! $verification->is_approved_by_admin) {
            $publishBlockers[] = 'La verificación IQPE aún no registra aprobación administrativa explícita.';
        }

        $approveBlockers = array_values(array_unique($approveBlockers));
        $approveWarnings = array_values(array_unique($approveWarnings));
        $publishBlockers = array_values(array_unique($publishBlockers));
        $publishWarnings = array_values(array_unique($publishWarnings));

        return [
            'can_approve'             => empty($approveBlockers),
            'approve_blockers'        => $approveBlockers,
            'approve_warnings'        => $approveWarnings,
            'can_publish'             => empty($publishBlockers),
            'publish_blockers'        => $publishBlockers,
            'publish_warnings'        => $publishWarnings,
            'has_critical_issues'     => ($audit?->audit_warnings['highest_severity'] ?? 'none') === 'critical' && ! empty($approveBlockers),
            'has_discrepancy'         => $hasDiscrepancy,
            'has_duplicate_risk'      => $hasDuplicateRisk,
            'duplicate_classification'=> $duplicateClassification,
            'is_without_official_key' => $isWithoutOfficialKey,
            'manual_key_assigned'     => $manualKeyAssigned,
            'is_approved'             => $question->status === 'review',
            'is_published'            => $question->status === 'published',
            'is_rejected'             => $question->status === 'archived',
        ];
    }

    /**
     * Construye el payload completo de revisión IQPE para una pregunta (uso exclusivo de administradores).
     *
     * @return array<string, mixed>
     */
    public function getQuestionDetail(Question $question): array
    {
        $question->loadMissing([
            'options',
            'stimulus.confirmedMediaAssets',
            'stimulus.mediaAssets',
            'confirmedMediaAssets',
            'mediaAssets',
            'subject',
            'topic',
            'sourceDocument',
        ]);

        $ctx = $this->resolveQuestionIqpeContext($question);
        $resolutions = $ctx['resolutions'];
        $verification = $ctx['verification'];
        $audit = $ctx['quality_audit'];
        $decisions = $ctx['decisions'];

        $eligibility = $this->evaluateEligibility($question, $verification, $audit, $decisions, verifyStorage: false);

        $formatResolution = function (?IqpeQuestionResolution $res): ?array {
            if (! $res) {
                return null;
            }
            return [
                'id'                    => $res->id,
                'round_number'          => $res->round_number,
                'ai_model'              => $res->ai_model,
                'solved_option_letter'  => $res->solved_option_letter,
                'solution_explanation'  => $res->solution_explanation,
                'visual_interpretation' => $res->visual_interpretation,
                'confidence_score'      => $res->confidence_score,
                'latency_ms'            => $res->latency_ms,
                'prompt_tokens'         => $res->prompt_tokens,
                'completion_tokens'     => $res->completion_tokens,
                'total_tokens'          => ($res->prompt_tokens !== null || $res->completion_tokens !== null)
                    ? ((int) ($res->prompt_tokens ?? 0) + (int) ($res->completion_tokens ?? 0))
                    : null,
                'status'                => $res->status,
                'created_at'            => $res->created_at?->toIso8601String(),
            ];
        };

        $r1 = $resolutions->get(1);
        $r2 = $resolutions->get(2);
        $r3 = $resolutions->get(3);

        // Información de Clave Oficial (exclusiva del panel administrativo)
        $correctOption = $question->options->firstWhere('is_correct', true);
        $currentMarkedLetter = $correctOption ? strtoupper(trim((string) $correctOption->option_letter)) : null;
        $manualKeyAssigned = $eligibility['manual_key_assigned'];
        $hasOfficialKeyInSource = $verification
            ? (bool) $verification->has_official_key
            : ($question->key_status === 'verified' && ! $manualKeyAssigned);
        $officialKeyLetter = $verification?->official_key_letter
            ?: ($hasOfficialKeyInSource ? $currentMarkedLetter : null);

        $compareLetter = $officialKeyLetter ?: $currentMarkedLetter;
        $keyWarnings = [];
        if (! $hasOfficialKeyInSource) {
            $keyWarnings[] = $manualKeyAssigned
                ? 'Esta pregunta NO contaba con clave oficial en el cuadernillo; la respuesta actual fue establecida manualmente por un administrador.'
                : 'La pregunta no posee clave oficial verificada en el documento origen.';
        }
        if ($question->key_status === 'disputed') {
            $keyWarnings[] = 'La clave oficial se encuentra marcada como inconsistente o en disputa.';
        }
        if ($verification && $verification->verdict === 'official_discrepancy') {
            $keyWarnings[] = 'Las resoluciones independientes de IA discreparon con la clave oficial registrada en el documento.';
        }
        if ($officialKeyLetter && $currentMarkedLetter && $officialKeyLetter !== $currentMarkedLetter) {
            $keyWarnings[] = "La clave oficial del documento ('{$officialKeyLetter}') difiere de la respuesta calibrada actual ('{$currentMarkedLetter}') tras intervención administrativa.";
        }

        // Hidratar comparativa de posibles duplicados (sin eliminar ni fusionar)
        $dupReport = $audit?->audit_warnings['duplicate_assessment'] ?? [
            'classification'              => 'independent',
            'is_duplicate_risk'           => false,
            'involves_published_question' => false,
            'primary_candidate_id'        => null,
            'matches'                     => [],
        ];

        $matches = is_array($dupReport['matches'] ?? null) ? $dupReport['matches'] : [];
        $candidateIds = collect($matches)->pluck('candidate_question_id')->filter()->unique()->values()->all();
        $candidatesById = ! empty($candidateIds)
            ? Question::with(['subject', 'sourceDocument', 'options'])
                ->whereIn('id', $candidateIds)
                ->get()
                ->keyBy('id')
            : collect();

        $hydratedMatches = array_map(function ($m) use ($candidatesById) {
            $candId = $m['candidate_question_id'] ?? null;
            $cand = $candId ? $candidatesById->get($candId) : null;
            return array_merge($m, [
                'candidate_question' => $cand ? [
                    'id'                   => $cand->id,
                    'order_in_group'       => $cand->order_in_group,
                    'status'               => $cand->status,
                    'key_status'           => $cand->key_status,
                    'statement'            => $cand->statement,
                    'subject_name'         => $cand->subject?->name,
                    'source_document_title'=> $cand->sourceDocument?->title,
                ] : null,
            ]);
        }, $matches);

        return [
            'question' => $question,
            'job_id'   => $ctx['job_id'],
            'resolutions' => [
                'r1' => $formatResolution($r1),
                'r2' => $formatResolution($r2),
                'r3' => $formatResolution($r3),
            ],
            'official_key' => [
                'has_official_key_in_source' => $hasOfficialKeyInSource,
                'official_key_letter'        => $officialKeyLetter,
                'current_marked_letter'      => $currentMarkedLetter,
                'key_status'                 => $question->key_status,
                'manual_key_assigned'        => $manualKeyAssigned,
                'matches_r1'                 => $compareLetter && $r1?->solved_option_letter ? ($r1->solved_option_letter === $compareLetter) : null,
                'matches_r2'                 => $compareLetter && $r2?->solved_option_letter ? ($r2->solved_option_letter === $compareLetter) : null,
                'matches_r3'                 => $compareLetter && $r3?->solved_option_letter ? ($r3->solved_option_letter === $compareLetter) : null,
                'is_consistent'              => empty($keyWarnings),
                'warnings'                   => $keyWarnings,
            ],
            'verification' => $verification ? [
                'id'                    => $verification->id,
                'verdict'               => $verification->verdict,
                'recommended_status'    => $verification->recommended_status,
                'requires_admin_review' => $verification->requires_admin_review,
                'is_approved_by_admin'  => $verification->is_approved_by_admin,
                'confidence_index'      => $verification->confidence_index,
                'discrepancy_details'   => $verification->discrepancy_details,
                'updated_at'            => $verification->updated_at?->toIso8601String(),
            ] : null,
            'quality_audit' => $audit ? [
                'id'                        => $audit->id,
                'statement_integrity_score' => $audit->statement_integrity_score,
                'options_integrity_score'   => $audit->options_integrity_score,
                'media_integrity_status'    => $audit->media_integrity_status,
                'duplicate_risk_flag'       => $audit->duplicate_risk_flag,
                'duplicate_candidate_id'    => $audit->duplicate_candidate_id,
                'passed_quality_check'      => $audit->passed_quality_check,
                'overall_classification'    => $audit->audit_warnings['overall_classification'] ?? 'requires_review',
                'highest_severity'          => $audit->audit_warnings['highest_severity'] ?? 'none',
                'findings_count'            => $audit->audit_warnings['findings_count'] ?? 0,
                'findings'                  => $audit->audit_warnings['findings'] ?? [],
                'updated_at'                => $audit->updated_at?->toIso8601String(),
            ] : null,
            'duplicates' => [
                'classification'              => $dupReport['classification'] ?? 'independent',
                'is_duplicate_risk'           => (bool) ($dupReport['is_duplicate_risk'] ?? false),
                'involves_published_question' => (bool) ($dupReport['involves_published_question'] ?? false),
                'primary_candidate_id'        => $dupReport['primary_candidate_id'] ?? null,
                'matches'                     => $hydratedMatches,
            ],
            'admin_decisions' => $decisions->map(fn (IqpeAdminDecision $d) => [
                'id'                      => $d->id,
                'action'                  => $d->action,
                'previous_status'         => $d->previous_status,
                'new_status'              => $d->new_status,
                'previous_key_status'     => $d->previous_key_status,
                'new_key_status'          => $d->new_key_status,
                'previous_correct_letter' => $d->previous_correct_letter,
                'new_correct_letter'      => $d->new_correct_letter,
                'manual_key_assigned'     => $d->manual_key_assigned,
                'admin_id'                => $d->admin_id,
                'admin_name'              => $d->admin_name,
                'admin_email'             => $d->admin_email,
                'admin_role'              => $d->admin_role,
                'reason'                  => $d->reason,
                'notes'                   => $d->notes,
                'changes_diff'            => $d->changes_diff,
                'created_at'              => $d->created_at?->toIso8601String(),
            ])->values()->all(),
            'eligibility' => $eligibility,
        ];
    }

    /**
     * Edita enunciado, opciones, taxonomía o clave de una pregunta manteniendo
     * trazabilidad estricta y sin alterar silenciosamente claves oficiales existentes.
     *
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $admin
     */
    public function editQuestion(Question $question, array $validated, array $admin): Question
    {
        $question->loadMissing(['options', 'stimulus']);
        $ctx = $this->resolveQuestionIqpeContext($question);
        $verification = $ctx['verification'];
        $jobId = $ctx['job_id'];

        $targetSubjectId = $validated['subject_id'] ?? $question->subject_id;
        if ($question->stimulus_id && $targetSubjectId) {
            $stimulus = $question->stimulus ?: Stimulus::find($question->stimulus_id);
            if ($stimulus && (int) $stimulus->subject_id !== (int) $targetSubjectId) {
                $this->abortJson(422, "La pregunta está vinculada a un estímulo de lectura cuya área es '{$stimulus->subject_id}'. No se puede cambiar el área sin modificar el estímulo asociado.");
            }
        }

        if (! empty($validated['topic_id'])) {
            $topicExists = Topic::where('id', $validated['topic_id'])
                ->where('subject_id', $targetSubjectId)
                ->exists();
            if (! $topicExists) {
                $this->abortJson(422, 'El tema seleccionado no pertenece al área académica asignada.');
            }
        }

        $previousStatus = $question->status;
        $previousKeyStatus = $question->key_status;
        $prevCorrectOpt = $question->options->firstWhere('is_correct', true);
        $previousCorrectLetter = $prevCorrectOpt ? strtoupper(trim((string) $prevCorrectOpt->option_letter)) : null;

        $keyStatus = $validated['key_status'] ?? $question->key_status;
        $correctLetter = isset($validated['correct_option']) && $validated['correct_option'] !== null
            ? strtoupper(trim((string) $validated['correct_option']))
            : null;

        if ($keyStatus === 'verified' && empty($correctLetter)) {
            if (! empty($validated['options'])) {
                foreach ($validated['options'] as $opt) {
                    if (! empty($opt['is_correct'])) {
                        $correctLetter = strtoupper(trim((string) $opt['option_letter']));
                        break;
                    }
                }
            }
            if (empty($correctLetter) && $previousCorrectLetter) {
                $correctLetter = $previousCorrectLetter;
            }
            if (empty($correctLetter)) {
                $this->abortJson(422, 'Para marcar la clave como verificada, debe seleccionar una opción de respuesta correcta (A, B, C o D).');
            }
        }

        $effectiveNewLetter = $keyStatus === 'verified' ? $correctLetter : $previousCorrectLetter;
        $hasOfficialKeyInDoc = $verification
            ? (bool) $verification->has_official_key
            : ($previousKeyStatus === 'verified' && ! $this->hasManualKeyDecision($ctx['decisions']));

        $keyChanged = ($keyStatus !== $previousKeyStatus) || ($effectiveNewLetter !== $previousCorrectLetter);
        $overrideReason = trim((string) ($validated['key_change_reason'] ?? $validated['manual_key_reason'] ?? $validated['notes'] ?? ''));

        // Protección contra alteración silenciosa de clave oficial verificada
        if ($hasOfficialKeyInDoc && $effectiveNewLetter && $previousCorrectLetter && $effectiveNewLetter !== $previousCorrectLetter) {
            if ($overrideReason === '') {
                $this->abortJson(422, 'No se puede modificar silenciosamente una clave oficial existente. Indique el motivo del cambio en "key_change_reason" o "notes".');
            }
        }

        // Si la pregunta no tenía clave oficial y el admin establece una manualmente, exigir/registrar trazabilidad explícita
        $isManualKeyAssignment = ! $hasOfficialKeyInDoc && $keyStatus === 'verified' && ! empty($effectiveNewLetter);

        $beforeSnapshot = [
            'statement'  => $question->statement,
            'explanation'=> $question->explanation,
            'subject_id' => $question->subject_id,
            'topic_id'   => $question->topic_id,
            'difficulty' => $question->difficulty,
            'key_status' => $previousKeyStatus,
            'correct'    => $previousCorrectLetter,
            'options'    => $question->options->mapWithKeys(fn ($o) => [$o->option_letter => $o->option_text])->all(),
        ];

        DB::connection('question_bank')->transaction(function () use (
            $question,
            $validated,
            $keyStatus,
            $correctLetter,
            $targetSubjectId,
            $admin,
            $jobId,
            $previousStatus,
            $previousKeyStatus,
            $previousCorrectLetter,
            $effectiveNewLetter,
            $isManualKeyAssignment,
            $hasOfficialKeyInDoc,
            $keyChanged,
            $overrideReason,
            $beforeSnapshot
        ) {
            $updateData = ['key_status' => $keyStatus];

            if (array_key_exists('statement', $validated) && $validated['statement'] !== null) {
                $updateData['statement'] = trim((string) $validated['statement']);
            }
            if (array_key_exists('explanation', $validated)) {
                $updateData['explanation'] = $validated['explanation'];
            }
            if (array_key_exists('subject_id', $validated) && $validated['subject_id'] !== null) {
                $updateData['subject_id'] = (int) $targetSubjectId;
            }
            if (array_key_exists('topic_id', $validated)) {
                $updateData['topic_id'] = $validated['topic_id'] ? (int) $validated['topic_id'] : null;
            }
            if (! empty($validated['difficulty'])) {
                $updateData['difficulty'] = $validated['difficulty'];
            }

            $question->update($updateData);

            if (! empty($validated['options']) && is_array($validated['options'])) {
                foreach ($validated['options'] as $optData) {
                    $letter = strtoupper(trim((string) $optData['option_letter']));
                    $isCorrect = ($keyStatus === 'verified' && $correctLetter === $letter);

                    $option = QuestionOption::where('question_id', $question->id)
                        ->where('option_letter', $letter)
                        ->first();

                    if ($option) {
                        $option->update([
                            'option_text' => trim((string) $optData['option_text']),
                            'is_correct'  => $isCorrect,
                        ]);
                    } else {
                        QuestionOption::create([
                            'id'            => (string) Str::uuid(),
                            'question_id'   => $question->id,
                            'option_letter' => $letter,
                            'option_text'   => trim((string) $optData['option_text']),
                            'is_correct'    => $isCorrect,
                        ]);
                    }
                }
            } elseif ($correctLetter || $keyStatus !== 'verified') {
                foreach ($question->options as $option) {
                    $isCorrect = ($keyStatus === 'verified' && strtoupper(trim((string) $option->option_letter)) === $correctLetter);
                    if ($option->is_correct !== $isCorrect) {
                        $option->update(['is_correct' => $isCorrect]);
                    }
                }
            }

            $fresh = $question->fresh(['options']);
            $afterSnapshot = [
                'statement'  => $fresh->statement,
                'explanation'=> $fresh->explanation,
                'subject_id' => $fresh->subject_id,
                'topic_id'   => $fresh->topic_id,
                'difficulty' => $fresh->difficulty,
                'key_status' => $fresh->key_status,
                'correct'    => $keyStatus === 'verified' ? $correctLetter : null,
                'options'    => $fresh->options->mapWithKeys(fn ($o) => [$o->option_letter => $o->option_text])->all(),
            ];

            $action = 'edited';
            if ($isManualKeyAssignment && $keyChanged) {
                $action = 'manual_key_set';
            } elseif ($hasOfficialKeyInDoc && $effectiveNewLetter && $previousCorrectLetter && $effectiveNewLetter !== $previousCorrectLetter) {
                $action = 'key_overridden';
            } elseif (! empty($validated['confirm_official_key']) && $verification?->verdict === 'official_discrepancy') {
                $action = 'discrepancy_confirmed';
            }

            $decisionReason = $overrideReason !== ''
                ? $overrideReason
                : ($action === 'manual_key_set'
                    ? 'Clave asignada manualmente por administrador tras revisión (sin clave oficial en cuadernillo).'
                    : ($validated['reason'] ?? 'Edición o calibración administrativa de reactivo.'));

            $this->recordDecision([
                'question_id'             => $question->id,
                'job_id'                  => $jobId,
                'action'                  => $action,
                'previous_status'         => $previousStatus,
                'new_status'              => $fresh->status,
                'previous_key_status'     => $previousKeyStatus,
                'new_key_status'          => $fresh->key_status,
                'previous_correct_letter' => $previousCorrectLetter,
                'new_correct_letter'      => $keyStatus === 'verified' ? $correctLetter : null,
                'manual_key_assigned'     => $isManualKeyAssignment,
                'reason'                  => $decisionReason,
                'notes'                   => $validated['notes'] ?? null,
                'changes_diff'            => ['before' => $beforeSnapshot, 'after' => $afterSnapshot],
            ], $admin);

            if ($jobId) {
                IqpeExecutionLog::record(
                    $jobId,
                    'admin_review',
                    $action === 'key_overridden' ? 'warning' : 'info',
                    "Administrador {$admin['name']} ejecutó acción '{$action}' sobre pregunta {$question->id}.",
                    ['action' => $action, 'manual_key_assigned' => $isManualKeyAssignment],
                    $question->id
                );
            }
        });

        $freshQuestion = $question->fresh(['options', 'stimulus.confirmedMediaAssets', 'stimulus.mediaAssets', 'confirmedMediaAssets', 'mediaAssets', 'subject', 'topic']);

        // Re-auditar la pregunta tras la corrección sin llamar a Gemini
        if ($jobId) {
            $job = IqpeDocumentJob::find($jobId);
            if ($job) {
                $this->qualityAuditService->auditJobQuestions($job, collect([$freshQuestion]));
            }
        }

        return $freshQuestion;
    }

    /**
     * Aprueba administrativamente una pregunta (cambia a status='review', sin publicar).
     *
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $payload
     */
    public function approveQuestion(Question $question, array $admin, array $payload = []): Question
    {
        $question->loadMissing(['options', 'stimulus.confirmedMediaAssets', 'stimulus.mediaAssets', 'confirmedMediaAssets', 'mediaAssets']);
        $ctx = $this->resolveQuestionIqpeContext($question);
        $verification = $ctx['verification'];
        $audit = $ctx['quality_audit'];
        $decisions = $ctx['decisions'];
        $jobId = $ctx['job_id'];

        if (in_array($question->status, ['review', 'published'], true)) {
            $this->abortJson(409, "Aprobación duplicada bloqueada: la pregunta ya se encuentra en estado '{$question->status}'.");
        }

        $eligibility = $this->evaluateEligibility($question, $verification, $audit, $decisions, verifyStorage: false);

        if (! $eligibility['can_approve']) {
            $this->abortJson(
                422,
                'No se puede aprobar la pregunta por problemas críticos o estructurales sin corregir: ' . implode(' | ', $eligibility['approve_blockers']),
                ['blockers' => $eligibility['approve_blockers'], 'warnings' => $eligibility['approve_warnings']]
            );
        }

        $notes = trim((string) ($payload['notes'] ?? ''));

        // Preguntas con discrepancias o posibles duplicados requieren revisión humana explícita (observación o confirmación consciente)
        if ($eligibility['has_discrepancy'] && $notes === '' && empty($payload['acknowledge_warnings'])) {
            $this->abortJson(
                422,
                'Esta pregunta presenta discrepancias entre resoluciones o clave oficial. Confirme la revisión humana (acknowledge_warnings) o registre una observación administrativa antes de aprobar.',
                ['warnings' => $eligibility['approve_warnings']]
            );
        }

        $previousStatus = $question->status;
        $correctOpt = $question->options->firstWhere('is_correct', true);
        $correctLetter = $correctOpt ? strtoupper(trim((string) $correctOpt->option_letter)) : null;

        DB::connection('question_bank')->transaction(function () use (
            $question,
            $verification,
            $admin,
            $jobId,
            $previousStatus,
            $correctLetter,
            $notes,
            $eligibility
        ) {
            $question->update(['status' => 'review']);

            if ($verification) {
                $verification->update([
                    'is_approved_by_admin'  => true,
                    'requires_admin_review' => false,
                ]);
            }

            $this->recordDecision([
                'question_id'             => $question->id,
                'job_id'                  => $jobId,
                'action'                  => 'approved',
                'previous_status'         => $previousStatus,
                'new_status'              => 'review',
                'previous_key_status'     => $question->key_status,
                'new_key_status'          => $question->key_status,
                'previous_correct_letter' => $correctLetter,
                'new_correct_letter'      => $correctLetter,
                'manual_key_assigned'     => $eligibility['manual_key_assigned'],
                'notes'                   => $notes !== '' ? $notes : null,
                'snapshot'                => [
                    'warnings_acknowledged' => $eligibility['approve_warnings'],
                    'verdict'               => $verification?->verdict,
                    'duplicate_risk'        => $eligibility['has_duplicate_risk'],
                ],
            ], $admin);

            if ($jobId) {
                IqpeExecutionLog::record(
                    $jobId,
                    'admin_review',
                    'info',
                    "Pregunta {$question->id} aprobada administrativamente por {$admin['name']} (status: {$previousStatus} -> review).",
                    ['notes' => $notes !== '' ? $notes : null, 'warnings_count' => count($eligibility['approve_warnings'])],
                    $question->id
                );
            }
        });

        return $question->fresh(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'subject', 'topic']);
    }

    /**
     * Rechaza y archiva una pregunta exigiendo motivo obligatorio sin eliminar el registro ni su historial IA.
     *
     * @param array<string, mixed> $admin
     */
    public function rejectQuestion(Question $question, array $admin, string $reason, ?string $notes = null): Question
    {
        $cleanReason = trim($reason);
        if (mb_strlen($cleanReason) < 5) {
            $this->abortJson(422, 'El motivo de rechazo es obligatorio (mínimo 5 caracteres).');
        }

        if ($question->status === 'archived') {
            $this->abortJson(409, 'La pregunta ya fue rechazada y archivada previamente.');
        }

        if ($question->status === 'published') {
            $this->abortJson(422, 'No se puede archivar directamente una pregunta publicada; devuélvala primero a revisión administrativa.');
        }

        $ctx = $this->resolveQuestionIqpeContext($question);
        $verification = $ctx['verification'];
        $jobId = $ctx['job_id'];
        $previousStatus = $question->status;

        DB::connection('question_bank')->transaction(function () use (
            $question,
            $verification,
            $admin,
            $jobId,
            $previousStatus,
            $cleanReason,
            $notes
        ) {
            $question->update(['status' => 'archived']);

            if ($verification) {
                $verification->update([
                    'is_approved_by_admin'  => false,
                    'requires_admin_review' => true,
                ]);
            }

            $this->recordDecision([
                'question_id'     => $question->id,
                'job_id'          => $jobId,
                'action'          => 'rejected',
                'previous_status' => $previousStatus,
                'new_status'      => 'archived',
                'reason'          => $cleanReason,
                'notes'           => $notes ? trim($notes) : null,
            ], $admin);

            if ($jobId) {
                IqpeExecutionLog::record(
                    $jobId,
                    'admin_review',
                    'warning',
                    "Pregunta {$question->id} rechazada/archivada por {$admin['name']}. Motivo: {$cleanReason}",
                    ['reason' => $cleanReason],
                    $question->id
                );
            }
        });

        return $question->fresh(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'subject', 'topic']);
    }

    /**
     * Devuelve una pregunta a revisión/corrección (status='draft') conservando resoluciones IA, auditorías e historial.
     *
     * @param array<string, mixed> $admin
     */
    public function returnToReview(Question $question, array $admin, string $reason, ?string $notes = null): Question
    {
        $cleanReason = trim($reason);
        if (mb_strlen($cleanReason) < 5) {
            $this->abortJson(422, 'Debe especificar un motivo para devolver la pregunta a revisión (mínimo 5 caracteres).');
        }

        if ($question->status === 'draft') {
            $this->abortJson(409, 'La pregunta ya se encuentra en estado borrador/revisión inicial.');
        }

        $ctx = $this->resolveQuestionIqpeContext($question);
        $verification = $ctx['verification'];
        $jobId = $ctx['job_id'];
        $previousStatus = $question->status;

        DB::connection('question_bank')->transaction(function () use (
            $question,
            $verification,
            $admin,
            $jobId,
            $previousStatus,
            $cleanReason,
            $notes
        ) {
            $question->update(['status' => 'draft']);

            if ($verification) {
                $verification->update([
                    'is_approved_by_admin'  => false,
                    'requires_admin_review' => true,
                ]);
            }

            $this->recordDecision([
                'question_id'     => $question->id,
                'job_id'          => $jobId,
                'action'          => 'returned_to_review',
                'previous_status' => $previousStatus,
                'new_status'      => 'draft',
                'reason'          => $cleanReason,
                'notes'           => $notes ? trim($notes) : null,
            ], $admin);

            if ($jobId) {
                IqpeExecutionLog::record(
                    $jobId,
                    'admin_review',
                    'info',
                    "Pregunta {$question->id} devuelta a revisión/corrección por {$admin['name']} (status: {$previousStatus} -> draft). Motivo: {$cleanReason}",
                    ['reason' => $cleanReason],
                    $question->id
                );
            }
        });

        return $question->fresh(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'subject', 'topic']);
    }

    /**
     * Publica explícitamente una pregunta previamente aprobada para consumo de estudiantes (status='published').
     * Acción independiente de la aprobación.
     *
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $payload
     */
    public function publishQuestion(Question $question, array $admin, array $payload = []): Question
    {
        $question->loadMissing(['options', 'stimulus.confirmedMediaAssets', 'stimulus.mediaAssets', 'confirmedMediaAssets', 'mediaAssets']);
        $ctx = $this->resolveQuestionIqpeContext($question);
        $verification = $ctx['verification'];
        $audit = $ctx['quality_audit'];
        $decisions = $ctx['decisions'];
        $jobId = $ctx['job_id'];

        if ($question->status === 'published') {
            $this->abortJson(409, 'La pregunta ya se encuentra publicada en el banco oficial de estudiantes.');
        }

        $eligibility = $this->evaluateEligibility($question, $verification, $audit, $decisions, verifyStorage: true);

        if (! $eligibility['can_publish']) {
            $this->abortJson(
                422,
                'No se cumplen los requisitos obligatorios para publicar la pregunta: ' . implode(' | ', $eligibility['publish_blockers']),
                ['blockers' => $eligibility['publish_blockers'], 'warnings' => $eligibility['publish_warnings']]
            );
        }

        $notes = trim((string) ($payload['notes'] ?? ''));
        if ($eligibility['has_duplicate_risk'] && empty($payload['confirm_duplicate_publish']) && $notes === '') {
            $this->abortJson(
                422,
                'La pregunta registra un posible duplicado en auditoría. Confirme explícitamente la publicación (confirm_duplicate_publish) o registre una observación.',
                ['warnings' => $eligibility['publish_warnings']]
            );
        }

        $previousStatus = $question->status;
        $correctOpt = $question->options->firstWhere('is_correct', true);
        $correctLetter = $correctOpt ? strtoupper(trim((string) $correctOpt->option_letter)) : null;

        DB::connection('question_bank')->transaction(function () use (
            $question,
            $admin,
            $jobId,
            $previousStatus,
            $correctLetter,
            $notes,
            $eligibility
        ) {
            $question->update(['status' => 'published']);

            $this->recordDecision([
                'question_id'             => $question->id,
                'job_id'                  => $jobId,
                'action'                  => 'published',
                'previous_status'         => $previousStatus,
                'new_status'              => 'published',
                'previous_key_status'     => $question->key_status,
                'new_key_status'          => $question->key_status,
                'previous_correct_letter' => $correctLetter,
                'new_correct_letter'      => $correctLetter,
                'manual_key_assigned'     => $eligibility['manual_key_assigned'],
                'notes'                   => $notes !== '' ? $notes : null,
                'snapshot'                => [
                    'publish_warnings'    => $eligibility['publish_warnings'],
                    'manual_key_assigned' => $eligibility['manual_key_assigned'],
                ],
            ], $admin);

            if ($jobId) {
                IqpeExecutionLog::record(
                    $jobId,
                    'published',
                    'info',
                    "Pregunta {$question->id} publicada para estudiantes por {$admin['name']} ({$previousStatus} -> published).",
                    ['notes' => $notes !== '' ? $notes : null],
                    $question->id
                );
            }
        });

        return $question->fresh(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets', 'subject', 'topic']);
    }

    /**
     * Verifica la disponibilidad real en Storage de todas las rutas visuales referenciadas.
     *
     * @return array<int, string>
     */
    protected function findMissingMediaPaths(Question $question): array
    {
        $paths = [];
        $push = function (?string $p) use (&$paths) {
            $clean = trim((string) $p);
            if ($clean !== '' && ! in_array($clean, $paths, true)) {
                $paths[] = $clean;
            }
        };

        $push($question->question_image_path);
        foreach ($question->confirmedMediaAssets as $a) {
            $push($a->storage_path);
        }
        if ($question->stimulus) {
            $push($question->stimulus->media_path);
            foreach ($question->stimulus->confirmedMediaAssets as $a) {
                $push($a->storage_path);
            }
        }
        foreach ($question->options as $opt) {
            $push($opt->option_image_path);
        }

        if (empty($paths)) {
            return [];
        }

        $bucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');
        $missing = [];
        foreach ($paths as $path) {
            try {
                if (! $this->storageService->exists($bucket, $path)) {
                    $missing[] = $path;
                }
            } catch (\Throwable) {
                $missing[] = $path;
            }
        }

        return $missing;
    }

    /**
     * @param Collection<int, IqpeAdminDecision> $decisions
     */
    protected function hasManualKeyDecision(Collection $decisions): bool
    {
        foreach ($decisions as $d) {
            if (in_array($d->action, ['manual_key_set', 'key_overridden', 'edited'], true) && $d->new_key_status !== null) {
                return (bool) $d->manual_key_assigned;
            }
        }
        return false;
    }

    /**
     * @param Collection<int, IqpeAdminDecision> $decisions
     * @param array<int, string> $actions
     */
    protected function hasDecisionAfter(Collection $decisions, array $actions, ?\DateTimeInterface $since): bool
    {
        if (! $since) {
            return $decisions->whereIn('action', $actions)->isNotEmpty();
        }
        foreach ($decisions as $d) {
            if (in_array($d->action, $actions, true) && $d->created_at && $d->created_at->gte($since)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $admin
     */
    protected function recordDecision(array $attributes, array $admin): IqpeAdminDecision
    {
        return IqpeAdminDecision::create(array_merge([
            'id'          => (string) Str::uuid(),
            'admin_id'    => (string) ($admin['id'] ?? $admin['sub'] ?? 'unknown-admin'),
            'admin_name'  => (string) ($admin['name'] ?? 'Administrador ATHENA'),
            'admin_email' => $admin['email'] ?? null,
            'admin_role'  => $admin['role'] ?? 'admin',
            'created_at'  => now(),
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function abortJson(int $status, string $message, array $extra = []): never
    {
        throw new HttpResponseException(response()->json(array_merge([
            'success' => false,
            'message' => $message,
        ], $extra), $status));
    }
}
