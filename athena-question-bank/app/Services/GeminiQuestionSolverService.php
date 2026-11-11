<?php

namespace App\Services;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\IqpeQuestionResolution;
use App\Models\Question;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeminiQuestionSolverService
{
    protected string $apiKey;
    protected string $model;
    protected string $fallbackModel;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(
        protected SupabaseStorageService $storageService
    ) {
        $this->apiKey = (string) (config('services.gemini.api_key') ?: env('GEMINI_API_KEY'));
        $rawModel = (string) (config('services.gemini.solver_model') ?: env('GEMINI_SOLVER_MODEL', 'gemini-3-flash-preview'));
        $this->model = str_replace('models/', '', $rawModel);

        $rawFallback = (string) (config('services.gemini.solver_fallback_model') ?: env('GEMINI_SOLVER_FALLBACK_MODEL', 'gemini-3.1-flash-lite'));
        $this->fallbackModel = str_replace('models/', '', $rawFallback);

        if (empty($this->apiKey)) {
            throw new RuntimeException('Configuración incompleta: GEMINI_API_KEY no está definida.');
        }
    }

    /**
     * Retorna el nombre real del modelo configurado para los resolutores R1 y R2.
     */
    public function getModelName(): string
    {
        return $this->model;
    }

    /**
     * Ejecuta un lote de resoluciones independientes (R1 o R2) controlando concurrencia,
     * límites de tasa de Gemini y reintentos con backoff exponencial.
     *
     * @param IqpeDocumentJob $job
     * @param Collection<int, Question> $questions
     * @param int $roundNumber 1 (R1) o 2 (R2)
     * @param int $concurrency Máximo de llamadas simultáneas en Http::pool
     * @return array{total:int, skipped:int, success:int, uncertain:int, failed:int, total_prompt_tokens:int, total_completion_tokens:int}
     */
    public function solveBatchForJob(
        IqpeDocumentJob $job,
        Collection $questions,
        int $roundNumber,
        ?int $concurrency = null
    ): array {
        if (! in_array($roundNumber, [1, 2], true)) {
            throw new RuntimeException("Ronda de resolución no válida para Bloque 3: {$roundNumber}. Solo se permite R1 (1) o R2 (2).");
        }

        $effectiveConcurrency = max(1, $concurrency ?? (int) config('services.gemini.solver_concurrency', 1));
        $cooldownMs = max(0, (int) config('services.gemini.solver_cooldown_ms', 2000));

        $stats = [
            'total'                   => $questions->count(),
            'skipped'                 => 0,
            'success'                 => 0,
            'uncertain'               => 0,
            'failed'                  => 0,
            'total_prompt_tokens'     => 0,
            'total_completion_tokens' => 0,
        ];

        $stageName = "solver_r{$roundNumber}";

        // Identificar preguntas ya resueltas con éxito o incertidumbre válida para no sobrescribirlas
        $existingCompleted = IqpeQuestionResolution::where('job_id', $job->id)
            ->where('round_number', $roundNumber)
            ->whereIn('status', ['success', 'uncertain'])
            ->get()
            ->keyBy('question_id');

        $pendingQuestions = [];
        foreach ($questions as $question) {
            if ($existingCompleted->has($question->id)) {
                $existing = $existingCompleted->get($question->id);
                $stats['skipped']++;
                if ($existing->status === 'success') {
                    $stats['success']++;
                } else {
                    $stats['uncertain']++;
                }
                continue;
            }
            $pendingQuestions[] = $question;
        }

        if (empty($pendingQuestions)) {
            IqpeExecutionLog::record(
                $job->id,
                $stageName,
                'info',
                "Ronda {$roundNumber}: todas las {$stats['total']} preguntas ya contaban con resolución persistida."
            );
            return $stats;
        }

        $totalPending = count($pendingQuestions);
        $processed = 0;

        foreach (array_chunk($pendingQuestions, $effectiveConcurrency) as $chunk) {
            $prepared = [];
            $toRequest = [];

            foreach ($chunk as $q) {
                $validation = $this->validateAndBuildPayload($job->id, $q, $roundNumber);
                if (! $validation['ready']) {
                    $this->accumulateResolutionStats($stats, $validation['resolution']);
                    $processed++;
                    continue;
                }

                $prepared[$q->id] = [
                    'question' => $q,
                    'payload'  => $validation['payload'],
                ];
                $toRequest[] = $q->id;
            }

            if (! empty($toRequest)) {
                if ($effectiveConcurrency === 1) {
                    foreach ($toRequest as $qId) {
                        $item = $prepared[$qId];
                        $resolution = $this->executeSingleWithRetry(
                            $job->id,
                            $item['question'],
                            $roundNumber,
                            $item['payload']
                        );
                        $this->accumulateResolutionStats($stats, $resolution);
                        $processed++;
                    }
                } else {
                    $latencies = [];
                    $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
                    $poolStart = microtime(true);

                    $responses = Http::pool(function (Pool $pool) use ($toRequest, $prepared, $endpoint, &$latencies) {
                        foreach ($toRequest as $qId) {
                            $pool->as($qId)
                                ->timeout(60)
                                ->withOptions([
                                    'on_stats' => function (TransferStats $transferStats) use (&$latencies, $qId) {
                                        $latencies[$qId] = (int) round($transferStats->getTransferTime() * 1000);
                                    },
                                ])
                                ->withHeaders(['Content-Type' => 'application/json'])
                                ->post($endpoint, $prepared[$qId]['payload']);
                        }
                    });

                    $poolFallbackMs = max(1, (int) round((microtime(true) - $poolStart) * 1000));

                    foreach ($toRequest as $qId) {
                        $item = $prepared[$qId];
                        $resp = $responses[$qId] ?? null;
                        $latencyMs = (! empty($latencies[$qId]) && $latencies[$qId] > 0)
                            ? $latencies[$qId]
                            : $poolFallbackMs;

                        $resolution = $this->handlePoolResponseOrRetry(
                            $job->id,
                            $item['question'],
                            $roundNumber,
                            $item['payload'],
                            $resp,
                            $latencyMs
                        );
                        $this->accumulateResolutionStats($stats, $resolution);
                        $processed++;
                    }
                }

                // Cooldown controlado entre lotes para cuidar cuota RPM/TPM de Gemini
                if ($processed < $totalPending && $cooldownMs > 0) {
                    usleep($cooldownMs * 1000);
                }
            }

            if ($effectiveConcurrency > 1 || $processed === $totalPending || $processed % 5 === 0) {
                IqpeExecutionLog::record(
                    $job->id,
                    $stageName,
                    'info',
                    "Ronda {$roundNumber} en progreso: {$processed}/{$totalPending} preguntas procesadas en esta ejecución."
                );
            }
        }

        return $stats;
    }

    /**
     * Resuelve de manera aislada una única pregunta para la ronda indicada (R1 o R2).
     */
    public function solveSingleQuestionRound(
        string $jobId,
        Question $question,
        int $roundNumber
    ): IqpeQuestionResolution {
        if (! in_array($roundNumber, [1, 2], true)) {
            throw new RuntimeException("Ronda de resolución no válida para Bloque 3: {$roundNumber}.");
        }

        $existing = IqpeQuestionResolution::where('job_id', $jobId)
            ->where('question_id', $question->id)
            ->where('round_number', $roundNumber)
            ->first();

        if ($existing && in_array($existing->status, ['success', 'uncertain'], true)) {
            return $existing;
        }

        $validation = $this->validateAndBuildPayload($jobId, $question, $roundNumber);
        if (! $validation['ready']) {
            return $validation['resolution'];
        }

        return $this->executeSingleWithRetry($jobId, $question, $roundNumber, $validation['payload']);
    }

    /**
     * Valida completitud académica e integridad visual de una pregunta antes de invocar a Gemini.
     * Si está incompleta o falta un recurso gráfico/tabular, persiste estado 'uncertain' para revisión.
     *
     * @return array{ready:bool, payload?:array, resolution?:IqpeQuestionResolution}
     */
    protected function validateAndBuildPayload(string $jobId, Question $question, int $roundNumber): array
    {
        $context = $this->buildSanitizedQuestionContext($question);

        $completenessIssue = $this->validateQuestionCompleteness($context);
        if ($completenessIssue !== null) {
            IqpeExecutionLog::record(
                $jobId,
                "solver_r{$roundNumber}",
                'warning',
                "Q#{$question->order_in_group} ({$question->id}) marcada como uncertain para revisión (Ronda {$roundNumber}): {$completenessIssue}",
                ['check' => 'completeness'],
                $question->id
            );

            $resolution = $this->persistResolution($jobId, $question->id, $roundNumber, [
                'ai_model'              => $this->model,
                'prompt_tokens'         => 0,
                'completion_tokens'     => 0,
                'latency_ms'            => 0,
                'solved_option_letter'  => null,
                'confidence_score'      => null,
                'solution_explanation'  => $completenessIssue,
                'visual_interpretation' => null,
                'status'                => 'uncertain',
                'raw_metadata'          => [
                    'requires_review'    => true,
                    'completeness_check' => 'failed',
                ],
            ]);

            return ['ready' => false, 'resolution' => $resolution];
        }

        $visualCheck = $this->prepareVisualResources($context);
        if ($visualCheck['has_missing_or_corrupt']) {
            IqpeExecutionLog::record(
                $jobId,
                "solver_r{$roundNumber}",
                'warning',
                "Q#{$question->order_in_group} ({$question->id}) marcada como uncertain por recurso visual/tabular ausente o ilegible (Ronda {$roundNumber}).",
                ['missing_paths' => $visualCheck['missing_paths']],
                $question->id
            );

            $resolution = $this->persistResolution($jobId, $question->id, $roundNumber, [
                'ai_model'              => $this->model,
                'prompt_tokens'         => 0,
                'completion_tokens'     => 0,
                'latency_ms'            => 0,
                'solved_option_letter'  => null,
                'confidence_score'      => null,
                'solution_explanation'  => $visualCheck['reason'],
                'visual_interpretation' => null,
                'status'                => 'uncertain',
                'raw_metadata'          => [
                    'requires_review'        => true,
                    'visual_integrity_check' => 'failed',
                    'missing_paths'          => $visualCheck['missing_paths'],
                ],
            ]);

            return ['ready' => false, 'resolution' => $resolution];
        }

        return [
            'ready'   => true,
            'payload' => $this->buildGeminiPayload($context, $visualCheck['parts'], $roundNumber),
        ];
    }

    /**
     * Construye un DTO académico estrictamente sanitizado.
     * PROHIBICIÓN ABSOLUTA: Nunca consulta ni incluye is_correct, explanation, key_status,
     * extraction_warnings ni respuestas previas.
     */
    public function buildSanitizedQuestionContext(Question $question): array
    {
        $question->loadMissing([
            'options' => fn ($q) => $q->select([
                'id',
                'question_id',
                'option_letter',
                'option_text',
                'option_image_path',
            ])->orderBy('option_letter', 'asc'),
            'stimulus.confirmedMediaAssets',
            'stimulus.mediaAssets',
            'confirmedMediaAssets',
            'mediaAssets',
            'subject',
        ]);

        // Opciones sanitizadas (sin is_correct ni metadata de verificación)
        $sanitizedOptions = [];
        foreach ($question->options->sortBy('option_letter') as $opt) {
            $sanitizedOptions[] = [
                'letter'     => strtoupper(trim((string) $opt->option_letter)),
                'text'       => (string) $opt->option_text,
                'image_path' => $opt->option_image_path ?: null,
            ];
        }

        // Estímulo sanitizado
        $stimulusData = null;
        if ($question->stimulus) {
            $stimulusData = [
                'title'                => $question->stimulus->title,
                'instructions'         => $question->stimulus->instructions,
                'content_text'         => $question->stimulus->content_text,
                'media_type'           => $question->stimulus->media_type,
                'media_path'           => $question->stimulus->media_path,
                'visual_transcription' => $question->stimulus->visual_transcription,
                'visual_description'   => $question->stimulus->visual_description,
            ];
        }

        // Recolección ordenada y deduplicada de rutas visuales reales
        $imageEntries = [];
        $seenPaths = [];

        $addImage = function (?string $path, string $label) use (&$imageEntries, &$seenPaths) {
            $cleanPath = trim((string) $path);
            if ($cleanPath === '' || isset($seenPaths[$cleanPath])) {
                return;
            }
            $seenPaths[$cleanPath] = true;
            $imageEntries[] = [
                'path'  => $cleanPath,
                'label' => $label,
            ];
        };

        if ($question->stimulus) {
            $addImage($question->stimulus->media_path, 'Imagen principal del estímulo compartido');
            $stimAssets = $question->stimulus->confirmedMediaAssets->isNotEmpty()
                ? $question->stimulus->confirmedMediaAssets
                : $question->stimulus->mediaAssets;
            foreach ($stimAssets as $asset) {
                $addImage($asset->storage_path, 'Recurso visual del estímulo (Página ' . ($asset->page_number ?? '?') . ')');
            }
        }

        $addImage($question->question_image_path, 'Imagen del enunciado de la pregunta');
        $qAssets = $question->confirmedMediaAssets->isNotEmpty()
            ? $question->confirmedMediaAssets
            : $question->mediaAssets;
        foreach ($qAssets as $asset) {
            $addImage($asset->storage_path, 'Recurso visual de la pregunta (Página ' . ($asset->page_number ?? '?') . ')');
        }

        foreach ($sanitizedOptions as $opt) {
            if (! empty($opt['image_path'])) {
                $addImage($opt['image_path'], "Imagen gráfica de la Opción {$opt['letter']}");
            }
        }

        return [
            'question_id'          => $question->id,
            'order_in_group'       => $question->order_in_group,
            'subject_name'         => $question->subject?->name ?? 'Competencia ICFES Saber 11',
            'statement'            => (string) $question->statement,
            'visual_transcription' => $question->visual_transcription,
            'visual_description'   => $question->visual_description,
            'stimulus'             => $stimulusData,
            'options'              => $sanitizedOptions,
            'image_entries'        => $imageEntries,
        ];
    }

    /**
     * Verifica completitud estructural mínima del reactivo antes de consultar al modelo.
     * Evita que Gemini invente respuestas sobre enunciados vacíos u opciones faltantes/marcadores sin imagen.
     */
    protected function validateQuestionCompleteness(array $context): ?string
    {
        $statement = trim((string) ($context['statement'] ?? ''));
        if ($statement === '' || mb_strtolower($statement) === 'pregunta sin enunciado') {
            return 'Reactivo incompleto para revisión: el enunciado de la pregunta está vacío o sin contenido.';
        }

        $options = $context['options'] ?? [];
        if (count($options) !== 4) {
            return 'Reactivo incompleto para revisión: la pregunta tiene ' . count($options) . ' opción(es) en lugar de 4 (A, B, C, D).';
        }

        $letters = [];
        foreach ($options as $opt) {
            $letter = strtoupper(trim((string) ($opt['letter'] ?? '')));
            $text = trim((string) ($opt['text'] ?? ''));
            $hasImg = ! empty($opt['image_path']);

            if (in_array($letter, ['A', 'B', 'C', 'D'], true)) {
                $letters[$letter] = true;
            }

            if ($text === '' && ! $hasImg) {
                return "Reactivo incompleto para revisión: la opción {$letter} está vacía y no tiene imagen asociada.";
            }

            if (! $hasImg && preg_match('/^(opci[oó]n(\s+gr[aá]fica)?|gr[aá]fica|figura|dise[ñn]o|imagen)\s+[A-D]$/iu', $text)) {
                return "Reactivo incompleto para revisión: la opción {$letter} ('{$text}') es un marcador visual sin archivo de imagen asociado.";
            }
        }

        if (count($letters) !== 4) {
            return 'Reactivo incompleto para revisión: no están presentes las cuatro letras de opción A, B, C y D.';
        }

        return null;
    }

    /**
     * Descarga y valida los recursos binarios reales desde Supabase Storage (icfes-media).
     * Garantiza que si una pregunta depende de un recurso visual ausente o ilegible,
     * se marque como 'uncertain' en lugar de alucinar con solo texto.
     */
    protected function prepareVisualResources(array $context): array
    {
        $mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');
        $imageEntries = $context['image_entries'] ?? [];
        $parts = [];
        $missingPaths = [];

        // 1. Verificar si el contexto declara explícitamente dependencia visual/tabular sin datos asociados
        $stimulusType = $context['stimulus']['media_type'] ?? 'none';
        $declaresVisualMedia = in_array($stimulusType, ['image', 'chart', 'mixed'], true);
        $missingTableContent = $stimulusType === 'table'
            && empty($imageEntries)
            && trim((string) ($context['stimulus']['content_text'] ?? '')) === ''
            && trim((string) ($context['stimulus']['visual_transcription'] ?? '')) === '';
        $hasTextualVisualCue = $this->detectsMissingVisualReference($context);

        if (empty($imageEntries) && ($declaresVisualMedia || $missingTableContent || $hasTextualVisualCue)) {
            return [
                'has_missing_or_corrupt' => true,
                'reason'                 => 'Información visual o tabular insuficiente: la pregunta o su estímulo hacen referencia explícita a una imagen, figura, diagrama, tabla o gráfica que no se encuentra asociada o disponible.',
                'missing_paths'          => ['[recurso_visual_ausente]'],
                'parts'                  => [],
            ];
        }

        // 2. Descargar y validar binariamente cada recurso visual requerido
        foreach ($imageEntries as $entry) {
            $path = $entry['path'];
            $label = $entry['label'];

            try {
                if (! $this->storageService->exists($mediaBucket, $path)) {
                    $missingPaths[] = $path;
                    continue;
                }

                $bytes = $this->storageService->get($mediaBucket, $path);
                if (empty($bytes)) {
                    $missingPaths[] = $path;
                    continue;
                }

                $imgInfo = @getimagesizefromstring($bytes);
                if ($imgInfo === false || empty($imgInfo['mime'])) {
                    $missingPaths[] = $path;
                    continue;
                }

                $mime = $imgInfo['mime'];
                $parts[] = ['text' => "[RECURSO VISUAL ADJUNTO: {$label}]"];
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $mime,
                        'data'     => base64_encode($bytes),
                    ],
                ];
            } catch (Throwable $e) {
                Log::warning("No se pudo recuperar recurso visual '{$path}': " . $e->getMessage());
                $missingPaths[] = $path;
            }
        }

        if (! empty($missingPaths)) {
            return [
                'has_missing_or_corrupt' => true,
                'reason'                 => 'No fue posible recuperar o decodificar el recurso visual requerido (' . implode(', ', $missingPaths) . '). Se marca como uncertain para evitar inferencias sin evidencia visual real.',
                'missing_paths'          => $missingPaths,
                'parts'                  => [],
            ];
        }

        return [
            'has_missing_or_corrupt' => false,
            'reason'                 => null,
            'missing_paths'          => [],
            'parts'                  => $parts,
        ];
    }

    /**
     * Detecta si el enunciado u opciones dependen inequívocamente de una figura, diagrama, tabla o gráfica ausente.
     */
    protected function detectsMissingVisualReference(array $context): bool
    {
        $textToCheck = $context['statement'] . "\n" . ($context['stimulus']['instructions'] ?? '');

        $visualPattern = '/\b(en\s+la\s+siguiente\s+(imagen|figura|gr[aá]fica|ilustraci[oó]n|historieta|vi[ñn]eta|caricatura|infograf[ií]a|fotograf[ií]a)|observa\s+la\s+(siguiente\s+)?(imagen|figura|gr[aá]fica|historieta|ilustraci[oó]n)|de\s+acuerdo\s+con\s+la\s+(imagen|figura|gr[aá]fica|ilustraci[oó]n|historieta|vi[ñn]eta|caricatura|infograf[ií]a)|seg[uú]n\s+la\s+(imagen|figura|gr[aá]fica|ilustraci[oó]n|historieta|vi[ñn]eta)|en\s+el\s+gr[aá]fico\s+anterior|en\s+la\s+figura\s+se\s+muestra|(observa|seg[uú]n|de\s+acuerdo\s+con|en)\s+el\s+(siguiente\s+)?(diagrama|esquema|mapa|plano|gr[aá]fico))\b/iu';

        if (preg_match($visualPattern, $textToCheck)) {
            return true;
        }

        $tablePattern = '/\b(en\s+la\s+siguiente\s+tabla|observa\s+la\s+(siguiente\s+)?tabla|de\s+acuerdo\s+con\s+la\s+tabla|seg[uú]n\s+la\s+tabla)\b/iu';
        if (preg_match($tablePattern, $textToCheck)) {
            $stimContent = trim((string) ($context['stimulus']['content_text'] ?? ''));
            $stimTrans = trim((string) ($context['stimulus']['visual_transcription'] ?? ''));
            $qTrans = trim((string) ($context['visual_transcription'] ?? ''));
            $hasInlineTable = str_contains($context['statement'], '|')
                || str_contains($stimContent, '|')
                || $stimTrans !== ''
                || $qTrans !== ''
                || mb_strlen($stimContent) >= 40;

            if (! $hasInlineTable) {
                return true;
            }
        }

        foreach ($context['options'] ?? [] as $opt) {
            if (preg_match('/^\s*(gr[aá]fica|figura|imagen|opci[oó]n\s+gr[aá]fica)\s+[abcd]\b/iu', trim($opt['text'])) && empty($opt['image_path'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Construye el payload completo para Gemini respetando independencia absoluta entre R1 y R2.
     */
    public function buildGeminiPayload(array $context, array $visualParts, int $roundNumber): array
    {
        $systemInstruction = $this->getSystemPromptForRound($roundNumber);
        $academicTextPrompt = $this->formatAcademicMaterialText($context);

        $parts = [];
        $parts[] = ['text' => $systemInstruction . "\n\n" . $academicTextPrompt];

        foreach ($visualParts as $vp) {
            $parts[] = $vp;
        }

        return [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature'      => $roundNumber === 1 ? 0.1 : 0.2,
                'maxOutputTokens'  => 4096,
            ],
        ];
    }

    /**
     * Retorna el prompt de sistema específico e independiente para cada ronda (R1 o R2).
     * NINGUNA ronda recibe referencia, pista ni confirmación sobre otras ejecuciones.
     */
    public function getSystemPromptForRound(int $roundNumber): string
    {
        if ($roundNumber === 1) {
            return <<<'PROMPT'
Eres un experto evaluador académico de pruebas ICFES Saber 11 en Colombia.
Tu tarea es analizar y resolver la siguiente pregunta de selección múltiple desde cero con máximo rigor lógico, lector, matemático o científico según el área indicada.

REGLAS ESTRICTAS:
1. Analiza exclusivamente el material académico provisto en esta petición (enunciado, estímulo, imágenes adjuntas y opciones A, B, C, D).
2. Si existen imágenes, gráficas, tablas o historietas adjuntas, debes observarlas directamente para fundamentar tu respuesta; no asumas detalles que no se vean o lean claramente.
3. Nunca inventes información faltante ni adivines al azar.
4. Si la información textual o visual es insuficiente, ilegible o incompleta para elegir una única respuesta con fundamento sólido, devuelve obligatoriamente "status": "uncertain" y "solved_option_letter": null.
5. Responde ÚNICAMENTE con un objeto JSON válido con esta estructura exacta:
{
  "status": "success" | "uncertain",
  "solved_option_letter": "A" | "B" | "C" | "D" | null,
  "solution_explanation": "Explicación pedagógica breve, clara y verificable del razonamiento que lleva a la respuesta (o motivo por el cual es incierto).",
  "visual_interpretation": "Interpretación directa de la figura, gráfica o imagen analizada (o null si la pregunta es puramente textual).",
  "confidence_score": 0.00
}
Nota: confidence_score es un valor decimal orientativo entre 0.000 y 1.000 (o null si status es uncertain).
PROMPT;
        }

        return <<<'PROMPT'
Eres un especialista disciplinar en evaluación por competencias ICFES Saber 11.
Debes resolver de forma completamente autónoma la siguiente pregunta de examen aplicando razonamiento analítico y verificación por descarte sobre cada una de las cuatro opciones A, B, C y D.

REGLAS ESTRICTAS:
1. Basa tu análisis exclusivamente en el enunciado, estímulo, recursos visuales adjuntos y opciones presentadas en esta solicitud.
2. Si la pregunta incluye o depende de imágenes, gráficas, esquemas o historietas, examina directamente los recursos visuales adjuntos sin asumir datos ausentes.
3. Evalúa la validez lógica de las opciones y descarta brevemente los distractores cuando la evidencia sea suficiente.
4. Si falta un dato indispensable, un gráfico requerido no es legible o el material resulta insuficiente para determinar una única respuesta segura, devuelve obligatoriamente "status": "uncertain" y "solved_option_letter": null.
5. Responde ÚNICAMENTE con un objeto JSON válido con esta estructura exacta:
{
  "status": "success" | "uncertain",
  "solved_option_letter": "A" | "B" | "C" | "D" | null,
  "solution_explanation": "Explicación pedagógica breve, verificable y paso a paso de la solución y descarte (o motivo por el cual no puede resolverse).",
  "visual_interpretation": "Descripción analítica directa de los elementos visuales observados (o null si no hay recursos visuales).",
  "confidence_score": 0.00
}
Nota: confidence_score es un valor decimal orientativo entre 0.000 y 1.000 (o null si status es uncertain).
PROMPT;
    }

    /**
     * Formatea el material académico sanitizado en texto claro para el modelo.
     */
    protected function formatAcademicMaterialText(array $context): string
    {
        $lines = [];
        $lines[] = "=== CONTEXTO ACADÉMICO ===";
        $lines[] = "Área académica: " . $context['subject_name'];

        if (! empty($context['stimulus'])) {
            $stim = $context['stimulus'];
            $lines[] = "\n--- ESTÍMULO COMPARTIDO ---";
            if (! empty($stim['title'])) {
                $lines[] = "Título: " . $stim['title'];
            }
            if (! empty($stim['instructions'])) {
                $lines[] = "Instrucciones: " . $stim['instructions'];
            }
            if (! empty($stim['content_text'])) {
                $lines[] = "Texto del estímulo:\n" . $stim['content_text'];
            }
            if (! empty($stim['visual_transcription'])) {
                $lines[] = "Transcripción visual de apoyo del estímulo:\n" . $stim['visual_transcription'];
            }
            if (! empty($stim['visual_description'])) {
                $lines[] = "Descripción visual de apoyo del estímulo: " . $stim['visual_description'];
            }
        }

        $lines[] = "\n--- PREGUNTA ---";
        $lines[] = "Enunciado:\n" . $context['statement'];

        if (! empty($context['visual_transcription'])) {
            $lines[] = "Transcripción visual de apoyo de la pregunta: " . $context['visual_transcription'];
        }
        if (! empty($context['visual_description'])) {
            $lines[] = "Descripción visual de apoyo de la pregunta: " . $context['visual_description'];
        }

        $lines[] = "\n--- OPCIONES DE RESPUESTA ---";
        foreach ($context['options'] as $opt) {
            $optLine = "{$opt['letter']}) {$opt['text']}";
            if (! empty($opt['image_path'])) {
                $optLine .= " [Ver imagen adjunta de la Opción {$opt['letter']}]";
            }
            $lines[] = $optLine;
        }

        return implode("\n", $lines);
    }

    /**
     * Procesa la respuesta del pool o reintenta con backoff exponencial si hubo error temporal.
     */
    protected function handlePoolResponseOrRetry(
        string $jobId,
        Question $question,
        int $roundNumber,
        array $payload,
        mixed $responseOrException,
        ?int $latencyMs
    ): IqpeQuestionResolution {
        if ($responseOrException instanceof Response && $responseOrException->successful()) {
            $parsed = $this->parseGeminiResponse($responseOrException, $this->model, $latencyMs);
            if ($parsed['is_valid_json']) {
                return $this->persistResolution($jobId, $question->id, $roundNumber, $parsed['attributes']);
            }
        }

        // Log fallo temporal e intentar recuperación secuencial controlada con backoff
        $errorHint = $responseOrException instanceof Throwable
            ? $responseOrException->getMessage()
            : ($responseOrException instanceof Response ? "HTTP {$responseOrException->status()}" : 'Respuesta inválida');

        Log::warning("Reintentando llamada individual para Q={$question->id} R{$roundNumber} tras fallo en pool: {$errorHint}");

        return $this->executeSingleWithRetry($jobId, $question, $roundNumber, $payload);
    }

    /**
     * Ejecuta una petición individual con backoff exponencial ante HTTP 429, 5xx o timeouts,
     * evitando esperas inútiles ante cuotas diarias agotadas y utilizando modelo de respaldo si aplica.
     */
    protected function executeSingleWithRetry(
        string $jobId,
        Question $question,
        int $roundNumber,
        array $payload
    ): IqpeQuestionResolution {
        $modelsToTry = [$this->model];
        if (! empty($this->fallbackModel) && $this->fallbackModel !== $this->model) {
            $modelsToTry[] = $this->fallbackModel;
        }

        $configuredBackoffs = config('services.gemini.solver_backoff_seconds', [3, 8, 0]);
        $lastError = 'Error desconocido al invocar Gemini.';
        $lastStatus = 'failed';
        $lastRaw = [];
        $lastModel = $this->model;
        $lastLatencyMs = null;

        foreach ($modelsToTry as $modelIndex => $currentModel) {
            $lastModel = $currentModel;
            $attemptsAllowed = ($modelIndex === 0) ? 3 : 1;

            for ($attempt = 1; $attempt <= $attemptsAllowed; $attempt++) {
                $endpoint = "{$this->baseUrl}/models/{$currentModel}:generateContent?key={$this->apiKey}";
                $start = microtime(true);
                $waitSeconds = (int) ($configuredBackoffs[$attempt - 1] ?? 5);

                try {
                    $response = Http::timeout(60)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->post($endpoint, $payload);

                    $lastLatencyMs = max(1, (int) round((microtime(true) - $start) * 1000));

                    if ($response->successful()) {
                        $parsed = $this->parseGeminiResponse($response, $currentModel, $lastLatencyMs);
                        if ($parsed['is_valid_json']) {
                            return $this->persistResolution($jobId, $question->id, $roundNumber, $parsed['attributes']);
                        }

                        $lastError = 'Gemini retornó JSON malformado o esquema inválido en la resolución.';
                        $lastStatus = 'failed';
                        $lastRaw = [
                            'model'         => $currentModel,
                            'finish_reason' => $response->json('candidates.0.finishReason'),
                            'raw_length'    => strlen($response->body()),
                        ];
                    } else {
                        $httpCode = $response->status();
                        $lastError = "Error HTTP {$httpCode} de Gemini ({$currentModel}): " . substr($response->body(), 0, 500);
                        $lastStatus = 'failed';
                        $lastRaw = [
                            'model'       => $currentModel,
                            'http_status' => $httpCode,
                            'body'        => substr($response->body(), 0, 1000),
                        ];

                        // Si no es error temporal reintentable (ej. 400 Bad Request, 404 Not Found), abortar
                        if (! in_array($httpCode, [429, 500, 502, 503, 504], true)) {
                            break 2;
                        }

                        if ($httpCode === 429) {
                            if ($this->isDailyQuotaExhausted($response)) {
                                Log::warning("Cuota diaria agotada en modelo {$currentModel} para Q={$question->id} R{$roundNumber}. Evaluando respaldo o abortando sin espera prolongada.");
                                break;
                            }

                            $waitSeconds = $this->extractRetryDelaySeconds($response, 22 * $attempt);
                        }
                    }
                } catch (ConnectionException $e) {
                    $lastLatencyMs = max(1, (int) round((microtime(true) - $start) * 1000));
                    $lastError = 'Timeout o error de conexión con Gemini: ' . $e->getMessage();
                    $lastStatus = 'timeout';
                    $lastRaw = ['model' => $currentModel, 'exception' => get_class($e)];
                } catch (Throwable $e) {
                    $lastLatencyMs = max(1, (int) round((microtime(true) - $start) * 1000));
                    $lastError = 'Excepción inesperada en Solver: ' . $e->getMessage();
                    $lastStatus = 'failed';
                    $lastRaw = ['model' => $currentModel, 'exception' => get_class($e)];
                }

                if ($attempt < $attemptsAllowed && $waitSeconds > 0) {
                    sleep($waitSeconds);
                }
            }
        }

        $lastRaw['error'] = $lastError;

        IqpeExecutionLog::record(
            $jobId,
            "solver_r{$roundNumber}",
            'error',
            "Fallo técnico persistente en Q#{$question->order_in_group} ({$question->id}) Ronda {$roundNumber}: {$lastError}",
            $lastRaw,
            $question->id
        );

        return $this->persistResolution($jobId, $question->id, $roundNumber, [
            'ai_model'              => $lastModel,
            'prompt_tokens'         => null,
            'completion_tokens'     => null,
            'latency_ms'            => $lastLatencyMs,
            'solved_option_letter'  => null,
            'confidence_score'      => null,
            'solution_explanation'  => "Error técnico en ejecución IA (Ronda {$roundNumber}): {$lastError}",
            'visual_interpretation' => null,
            'status'                => $lastStatus,
            'raw_metadata'          => $lastRaw,
        ]);
    }

    /**
     * Acumula métricas de estado y consumo de tokens por cada resolución procesada.
     */
    protected function accumulateResolutionStats(array &$stats, IqpeQuestionResolution $resolution): void
    {
        if ($resolution->status === 'success') {
            $stats['success']++;
        } elseif ($resolution->status === 'uncertain') {
            $stats['uncertain']++;
        } else {
            $stats['failed']++;
        }

        $stats['total_prompt_tokens'] += (int) ($resolution->prompt_tokens ?? 0);
        $stats['total_completion_tokens'] += (int) ($resolution->completion_tokens ?? 0);
    }

    /**
     * Determina si un HTTP 429 corresponde a cuota diaria agotada (no recuperable en segundos).
     */
    protected function isDailyQuotaExhausted(Response $response): bool
    {
        $details = $response->json('error.details', []);
        if (is_array($details)) {
            foreach ($details as $detail) {
                if (! empty($detail['retryDelay']) && preg_match('/(\d+)/', (string) $detail['retryDelay'], $m)) {
                    if ((int) $m[1] > 120) {
                        return true;
                    }
                }
                foreach ($detail['violations'] ?? [] as $violation) {
                    $quotaId = (string) ($violation['quotaId'] ?? '');
                    if (stripos($quotaId, 'PerDay') !== false) {
                        return true;
                    }
                }
            }
        }

        $message = (string) $response->json('error.message', '');
        return stripos($message, 'per day') !== false;
    }

    /**
     * Extrae el tiempo de espera sugerido ante errores HTTP 429 (Retry-After / RetryInfo).
     */
    protected function extractRetryDelaySeconds(Response $response, int $fallbackSeconds = 22): int
    {
        $maxCap = config('services.gemini.solver_max_retry_wait_seconds');
        if ($maxCap !== null && (int) $maxCap === 0) {
            return 0;
        }
        $cap = $maxCap !== null ? max(1, (int) $maxCap) : 65;

        $retryAfter = $response->header('Retry-After');
        if (is_numeric($retryAfter)) {
            return max(10, min($cap, (int) $retryAfter));
        }

        $details = $response->json('error.details', []);
        if (is_array($details)) {
            foreach ($details as $detail) {
                if (! empty($detail['retryDelay']) && preg_match('/(\d+)/', (string) $detail['retryDelay'], $m)) {
                    return max(10, min($cap, (int) $m[1] + 2));
                }
            }
        }

        return max(10, min($cap, $fallbackSeconds));
    }

    /**
     * Interpreta y normaliza el JSON devuelto por Gemini y captura tokens y latencia reales.
     * Excluye cualquier parte marcada como pensamiento interno privado ('thought' => true).
     */
    protected function parseGeminiResponse(Response $response, string $usedModel, ?int $latencyMs): array
    {
        $body = $response->json();
        $parts = $body['candidates'][0]['content']['parts'] ?? [];
        $rawText = '';

        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (! empty($part['thought'])) {
                    continue;
                }
                if (isset($part['text']) && is_string($part['text'])) {
                    $rawText .= $part['text'];
                }
            }
        }

        $usage = $body['usageMetadata'] ?? [];
        $promptTokens = isset($usage['promptTokenCount']) ? (int) $usage['promptTokenCount'] : null;
        $completionTokens = isset($usage['candidatesTokenCount']) ? (int) $usage['candidatesTokenCount'] : null;

        $cleaned = trim($rawText);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded) || empty($decoded['status'])) {
            return ['is_valid_json' => false];
        }

        $rawStatus = strtolower(trim((string) $decoded['status']));
        $status = $rawStatus === 'success' ? 'success' : 'uncertain';

        $letter = isset($decoded['solved_option_letter'])
            ? strtoupper(trim((string) $decoded['solved_option_letter']))
            : null;

        if ($status === 'success') {
            if (! in_array($letter, ['A', 'B', 'C', 'D'], true)) {
                return ['is_valid_json' => false];
            }
        } else {
            $letter = null;
        }

        $confidence = null;
        if ($status === 'success' && isset($decoded['confidence_score']) && is_numeric($decoded['confidence_score'])) {
            $val = (float) $decoded['confidence_score'];
            $confidence = round(max(0.0, min(1.0, $val)), 3);
        }

        $explanation = trim((string) ($decoded['solution_explanation'] ?? ''));
        if ($explanation === '') {
            $explanation = $status === 'success'
                ? 'Resolución generada sin explicación detallada adicional.'
                : 'El modelo determinó que la información disponible es insuficiente para resolver con certeza.';
        }

        $visualInterpretation = isset($decoded['visual_interpretation']) && trim((string) $decoded['visual_interpretation']) !== ''
            ? trim((string) $decoded['visual_interpretation'])
            : null;

        return [
            'is_valid_json' => true,
            'attributes'    => [
                'ai_model'              => $usedModel,
                'prompt_tokens'         => $promptTokens,
                'completion_tokens'     => $completionTokens,
                'latency_ms'            => $latencyMs,
                'solved_option_letter'  => $letter,
                'confidence_score'      => $confidence,
                'solution_explanation'  => $explanation,
                'visual_interpretation' => $visualInterpretation,
                'status'                => $status,
                'raw_metadata'          => [
                    'usage_metadata'        => $usage,
                    'confidence_disclaimer' => 'Valor orientativo generado por el modelo; no representa probabilidad calibrada.',
                    'finish_reason'         => $body['candidates'][0]['finishReason'] ?? null,
                ],
            ],
        ];
    }

    /**
     * Persiste la resolución respetando la restricción única (job_id, question_id, round_number)
     * sin sobrescribir resoluciones exitosas o inciertas previas.
     */
    protected function persistResolution(
        string $jobId,
        string $questionId,
        int $roundNumber,
        array $attributes
    ): IqpeQuestionResolution {
        $existing = IqpeQuestionResolution::where('job_id', $jobId)
            ->where('question_id', $questionId)
            ->where('round_number', $roundNumber)
            ->first();

        if ($existing && in_array($existing->status, ['success', 'uncertain'], true)) {
            return $existing;
        }

        if ($existing) {
            $existing->update($attributes);
            return $existing;
        }

        return IqpeQuestionResolution::create(array_merge([
            'id'           => (string) Str::uuid(),
            'job_id'       => $jobId,
            'question_id'  => $questionId,
            'round_number' => $roundNumber,
        ], $attributes));
    }
}

