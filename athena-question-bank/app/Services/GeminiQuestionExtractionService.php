<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Models\Stimulus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeminiQuestionExtractionService
{
    protected string $apiKey;
    protected string $model;
    protected string $fallbackModel;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(
        protected SupabaseStorageService $storageService,
        protected PdfImageExtractor $imageExtractor
    ) {
        $this->apiKey = (string) (config('services.gemini.api_key') ?: env('GEMINI_API_KEY'));
        
        $rawModel = (string) (config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-3-flash-preview'));
        $this->model = str_replace('models/', '', $rawModel);

        $rawFallback = (string) (config('services.gemini.fallback_model') ?: env('GEMINI_FALLBACK_MODEL', 'gemini-3.1-flash-lite'));
        $this->fallbackModel = str_replace('models/', '', $rawFallback);

        if (empty($this->apiKey)) {
            throw new RuntimeException('Configuración incompleta: GEMINI_API_KEY no está definida en el archivo .env.');
        }
    }

    /**
     * Procesa un documento PDF almacenado en Supabase Storage y extrae preguntas mediante Gemini multimodal.
     *
     * @param SourceDocument $document
     * @return array Resumen del proceso de extracción
     */
    public function extractQuestionsFromDocument(SourceDocument $document): array
    {
        $startTime = now();

        // 1. Marcar documento en procesamiento
        $document->update([
            'status' => 'processing',
            'extraction_metadata' => [
                'status'     => 'processing',
                'started_at' => $startTime->toIso8601String(),
                'model'      => $this->model,
            ],
        ]);

        try {
            // 2. Recuperar el archivo binario desde el bucket privado de Supabase Storage
            $docBucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
            $mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');
            
            if (! $this->storageService->exists($docBucket, $document->storage_path)) {
                throw new RuntimeException("El archivo físico no existe en Supabase Storage ({$docBucket}: {$document->storage_path}).");
            }

            $pdfBytes = $this->storageService->get($docBucket, $document->storage_path);
            if (empty($pdfBytes)) {
                throw new RuntimeException("El archivo PDF recuperado de Storage está vacío.");
            }

            $fileSizeBytes = strlen($pdfBytes);
            $base64Pdf = base64_encode($pdfBytes);

            // 3. Extracción de imágenes físicas incrustadas en el PDF
            $extractionResult = $this->imageExtractor->extract($pdfBytes);
            $extractedImages = $extractionResult['images'] ?? [];
            $uploadedImages = [];
            $totalPages = (int) ($extractionResult['pages'] ?? 0);

            $manifest = [
                'source_document_id' => $document->id,
                'generated_at'       => now()->toIso8601String(),
                'total_pages'        => $totalPages,
                'images'             => [],
            ];

            foreach ($extractedImages as $img) {
                if (($img['status'] ?? '') === 'ok') {
                    $storagePath = sprintf('extracted/%s/page-%02d_obj%d.%s', $document->id, $img['page'], $img['object'], $img['ext']);
                    $sha256 = hash('sha256', $img['bytes']);

                    // Subir a icfes-media
                    $this->storageService->upload($mediaBucket, $storagePath, $img['bytes'], [
                        'ContentType' => $img['mime'],
                    ]);

                    $uploadedEntry = [
                        'page'         => (int) $img['page'],
                        'object'       => (int) $img['object'],
                        'width'        => (int) $img['width'],
                        'height'       => (int) $img['height'],
                        'mime'         => $img['mime'],
                        'sha256'       => $sha256,
                        'storage_path' => $storagePath,
                    ];

                    $uploadedImages[] = $uploadedEntry;
                    $manifest['images'][] = array_merge($uploadedEntry, [
                        'status'             => 'ok',
                        'association_status' => 'pending',
                    ]);
                }
            }

            // Guardar manifest.json en el bucket de medios
            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $this->storageService->upload($mediaBucket, "extracted/{$document->id}/manifest.json", $manifestJson, [
                'ContentType' => 'application/json',
            ]);

            // 4. Preparar prompt de extracción multimodal estricta ICFES contextual al documento
            $systemInstruction = $this->buildPromptInstruction($document, $totalPages);

            // 5. Llamada multimodal a Gemini con reintentos y fallback automático ante sobredemanda (HTTP 503 / 429)
            $candidateModels = array_values(array_unique(array_filter([
                $this->model,
                $this->fallbackModel,
                'gemini-3-flash-preview',
                'gemini-3.1-flash-lite',
            ])));

            $response = null;
            $usedModel = null;
            $lastExceptionMessage = null;
            $configuredDelay = config('services.gemini.extraction_retry_delay_seconds');

            foreach ($candidateModels as $currentModel) {
                $endpoint = "{$this->baseUrl}/models/{$currentModel}:generateContent?key={$this->apiKey}";
                $maxAttempts = 2;

                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    Log::info("Invocando Gemini multimodal (modelo: {$currentModel}, intento {$attempt}/{$maxAttempts}) para documento {$document->id}...");

                    try {
                        $response = Http::timeout(300)
                            ->withHeaders(['Content-Type' => 'application/json'])
                            ->post($endpoint, [
                                'contents' => [
                                    [
                                        'parts' => [
                                            [
                                                'inlineData' => [
                                                    'mimeType' => 'application/pdf',
                                                    'data'     => $base64Pdf,
                                                ],
                                            ],
                                            [
                                                'text' => $systemInstruction,
                                            ],
                                        ],
                                    ],
                                ],
                                'generationConfig' => [
                                    'responseMimeType' => 'application/json',
                                    'temperature'      => 0.1,
                                    'maxOutputTokens'  => 65536,
                                ],
                            ]);

                        if ($response->successful()) {
                            $usedModel = $currentModel;
                            break 2; // Éxito con este modelo, continuar procesamiento
                        }

                        $status = $response->status();
                        $body = $response->json();
                        $errorMessage = $body['error']['message'] ?? $response->body();
                        $lastExceptionMessage = "Error en Gemini API (HTTP {$status}, modelo {$currentModel}): {$errorMessage}";

                        if (in_array($status, [429, 503], true)) {
                            if ($attempt < $maxAttempts) {
                                $delay = $configuredDelay !== null ? (int) $configuredDelay : (($attempt === 1) ? 6 : 12);
                                Log::warning("Gemini API retornó HTTP {$status} en {$currentModel} (intento {$attempt}/{$maxAttempts}). Reintentando en {$delay} segundos...");
                                if ($delay > 0) {
                                    sleep($delay);
                                }
                                continue;
                            } else {
                                Log::warning("Modelo '{$currentModel}' no disponible temporalmente (HTTP {$status}). Probando siguiente modelo de respaldo...");
                            }
                        } else {
                            Log::warning("Error en Gemini API con {$currentModel} (HTTP {$status}): {$errorMessage}. Evaluando siguiente modelo...");
                            break;
                        }
                    } catch (Throwable $netEx) {
                        $lastExceptionMessage = "Excepción de conexión con Gemini ({$currentModel}): " . $netEx->getMessage();
                        Log::warning($lastExceptionMessage);
                        break;
                    }
                }
            }

            if (! $response || ! $response->successful()) {
                throw new RuntimeException($lastExceptionMessage ?: 'No se pudo obtener respuesta válida de ningún modelo de Gemini disponible.');
            }

            $responseData = $response->json();
            $parts = $responseData['candidates'][0]['content']['parts'] ?? [];
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
            if ($rawText === '') {
                $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
            }
            $usageMeta = $responseData['usageMetadata'] ?? [];

            if (empty($rawText)) {
                throw new RuntimeException('Gemini retornó una respuesta vacía o sin candidatos válidos.');
            }

            // 6. Decodificar y validar JSON estructurado con limpieza de markdown
            $extractedJson = $this->cleanAndDecodeJson($rawText);
            if (! is_array($extractedJson) || empty($extractedJson['questions'])) {
                Log::error('Fallo al interpretar JSON de preguntas retornado por Gemini.', [
                    'raw_sample' => substr($rawText, 0, 800),
                    'raw_tail'   => substr($rawText, -500),
                    'length'     => strlen($rawText),
                ]);
                throw new RuntimeException('La respuesta de Gemini no contiene una estructura JSON válida de preguntas.');
            }

            // 7. Almacenar preguntas, estímulos y auto-asociar imágenes en transacción
            $saveSummary = $this->persistExtractedData($document, $extractedJson, $uploadedImages);

            // 8. Ejecutar auditoría automática de calidad y verificación contra el PDF original
            $auditReport = $this->runAutomatedQualityAudit($document, $pdfBytes);

            $endTime = now();
            $durationSeconds = max(0, (int) ($endTime->timestamp - $startTime->timestamp));

            $metadata = [
                'status'                    => 'success',
                'model'                     => $usedModel ?: $this->model,
                'started_at'                => $startTime->toIso8601String(),
                'completed_at'              => $endTime->toIso8601String(),
                'duration_seconds'          => $durationSeconds,
                'file_size_bytes'           => $fileSizeBytes,
                'prompt_tokens'             => isset($usageMeta['promptTokenCount']) ? (int) $usageMeta['promptTokenCount'] : null,
                'completion_tokens'         => isset($usageMeta['candidatesTokenCount']) ? (int) $usageMeta['candidatesTokenCount'] : null,
                'total_tokens'              => isset($usageMeta['totalTokenCount']) ? (int) $usageMeta['totalTokenCount'] : null,
                'images_detected_count'     => count($uploadedImages),
                'images_associated_count'   => $saveSummary['associated_media_count'],
                'stimuli_created'           => $saveSummary['stimuli_count'],
                'questions_extracted'       => $saveSummary['questions_count'],
                'options_created'           => $saveSummary['options_count'],
                'verified_answers_count'    => $auditReport['verified_keys_count'] ?? $saveSummary['verified_keys_count'],
                'unverified_answers_count'  => $auditReport['unverified_keys_count'] ?? $saveSummary['unverified_keys_count'],
                'quality_audit'             => $auditReport,
            ];

            // 9. Actualizar estado del documento a 'extracted'
            $document->update([
                'status'              => 'extracted',
                'has_answer_key'      => $auditReport['has_official_answer_key'] ?? false,
                'extraction_metadata' => $metadata,
            ]);

            return $metadata;

        } catch (Throwable $e) {
            $endTime = now();
            $document->update([
                'status'              => 'failed',
                'extraction_metadata' => [
                    'status'       => 'failed',
                    'model'        => $this->model,
                    'started_at'   => $startTime->toIso8601String(),
                    'failed_at'    => $endTime->toIso8601String(),
                    'error_detail' => $e->getMessage(),
                ],
            ]);

            Log::error('Fallo en extracción con Gemini: ' . $e->getMessage(), [
                'document_id' => $document->id,
            ]);

            throw $e;
        }
    }

    /**
     * Persiste los estímulos, preguntas y recursos visuales auto-asociados en Supabase PostgreSQL.
     */
    protected function persistExtractedData(SourceDocument $document, array $data, array $uploadedImages = []): array
    {
        return DB::connection('question_bank')->transaction(function () use ($document, $data, $uploadedImages) {
            // Evitar duplicados previos en draft al reintentar
            $oldQuestions = Question::where('source_document_id', $document->id)
                ->where('status', 'draft')
                ->get();

            foreach ($oldQuestions as $oldQ) {
                QuestionOption::where('question_id', $oldQ->id)->delete();
                $oldQ->delete();
            }

            Stimulus::where('source_document_id', $document->id)->delete();
            MediaAsset::where('source_document_id', $document->id)->delete();

            // 1. Registrar todos los assets multimedia físicos detectados con estado 'pending'
            $assetsByPage = [];
            foreach ($uploadedImages as $img) {
                $asset = MediaAsset::create([
                    'id'                 => (string) Str::uuid(),
                    'source_document_id' => $document->id,
                    'storage_path'       => $img['storage_path'],
                    'page_number'        => (int) $img['page'],
                    'width'              => (int) $img['width'],
                    'height'             => (int) $img['height'],
                    'mime_type'          => $img['mime'],
                    'sha256'             => $img['sha256'],
                    'association_status' => 'pending',
                ]);

                $page = (int) $img['page'];
                if (!isset($assetsByPage[$page])) {
                    $assetsByPage[$page] = [];
                }
                $assetsByPage[$page][] = $asset;
            }

            // 2. Crear estímulos compartidos
            $stimuliMap = [];
            $stimuliCount = 0;
            $associatedMediaCount = 0;

            if (!empty($data['stimuli']) && is_array($data['stimuli'])) {
                foreach ($data['stimuli'] as $stimData) {
                    $tag = $stimData['id_tag'] ?? ('STIM_' . ($stimuliCount + 1));
                    $subjectId = $this->resolveSubjectId($stimData['subject_name'] ?? '', $document);

                    $hasVisual = !empty($stimData['has_visual_media']) || (!empty($stimData['visual_type']) && $stimData['visual_type'] !== 'none');
                    $sourcePage = isset($stimData['source_page']) && is_numeric($stimData['source_page']) && (int) $stimData['source_page'] > 0
                        ? (int) $stimData['source_page']
                        : null;

                    $visualTranscription = !empty($stimData['visual_transcription']) ? trim($stimData['visual_transcription']) : null;
                    $visualDescription = !empty($stimData['visual_description']) ? trim($stimData['visual_description']) : null;

                    $stimulus = Stimulus::create([
                        'id'                   => (string) Str::uuid(),
                        'source_document_id'   => $document->id,
                        'subject_id'           => $subjectId,
                        'title'                => trim($stimData['title'] ?? 'Estímulo compartido'),
                        'content_text'         => trim($stimData['content_text'] ?? ''),
                        'media_type'           => $this->normalizeMediaType($stimData['media_type'] ?? ($hasVisual ? 'image' : 'none')),
                        'source_page'          => $sourcePage,
                        'instructions'         => $stimData['instructions'] ?? null,
                        'visual_transcription' => $visualTranscription,
                        'visual_description'   => $visualDescription,
                    ]);

                    // Auto-asociación determinística: solo si el estímulo es explícitamente visual y coincide en su página física exacta
                    $matchedAsset = null;
                    if ($hasVisual && $sourcePage && !empty($assetsByPage[$sourcePage])) {
                        $matchedAsset = array_shift($assetsByPage[$sourcePage]);
                    }

                    if ($matchedAsset) {
                        $matchedAsset->update([
                            'stimulus_id'          => $stimulus->id,
                            'question_id'          => null,
                            'association_status'   => 'confirmed',
                            'confidence'           => 0.95,
                            'visual_transcription' => $visualTranscription,
                            'visual_description'   => $visualDescription,
                            'associated_at'        => now(),
                        ]);

                        $stimulus->update([
                            'media_type' => 'image',
                            'media_path' => $matchedAsset->storage_path,
                        ]);

                        $associatedMediaCount++;
                    }

                    $stimuliMap[$tag] = $stimulus;
                    $stimuliCount++;
                }
            }

            // 3. Crear preguntas y auto-asociar imágenes de pregunta directa
            $questionsCount = 0;
            $optionsCount = 0;
            $verifiedKeysCount = 0;
            $unverifiedKeysCount = 0;

            if (!empty($data['questions']) && is_array($data['questions'])) {
                foreach ($data['questions'] as $qData) {
                    $subjectId = $this->resolveSubjectId($qData['subject_name'] ?? '', $document);
                    
                    // Asociar estímulo si existe
                    $stimulusId = null;
                    if (!empty($qData['stimulus_tag']) && isset($stimuliMap[$qData['stimulus_tag']])) {
                        $stimulus = $stimuliMap[$qData['stimulus_tag']];
                        $stimulusId = $stimulus->id;
                        $subjectId = $stimulus->subject_id;
                    }

                    // Clave de respuesta verificable
                    $hasVerifiable = !empty($qData['has_verifiable_answer']) && !empty($qData['correct_option_letter']);
                    $keyStatus = $hasVerifiable ? 'verified' : 'unverified';
                    $correctLetter = $hasVerifiable ? strtoupper(trim($qData['correct_option_letter'])) : null;

                    if ($hasVerifiable) {
                        $verifiedKeysCount++;
                    } else {
                        $unverifiedKeysCount++;
                    }

                    $qSourcePage = isset($qData['source_page']) && is_numeric($qData['source_page']) && (int) $qData['source_page'] > 0
                        ? (int) $qData['source_page']
                        : null;

                    $qVisualTrans = !empty($qData['visual_transcription']) ? trim($qData['visual_transcription']) : null;
                    $qVisualDesc = !empty($qData['visual_description']) ? trim($qData['visual_description']) : null;
                    $qWarnings = !empty($qData['extraction_warnings']) ? trim($qData['extraction_warnings']) : null;
                    $qHasVisual = !empty($qData['has_visual_media']) || (!empty($qData['visual_type']) && $qData['visual_type'] !== 'none');

                    // Crear pregunta en estado borrador (draft) para revisión administrativa
                    $question = Question::create([
                        'id'                   => (string) Str::uuid(),
                        'root_question_id'     => (string) Str::uuid(),
                        'version'              => 1,
                        'is_current_version'   => true,
                        'subject_id'           => $subjectId,
                        'topic_id'             => null,
                        'stimulus_id'          => $stimulusId,
                        'source_document_id'   => $document->id,
                        'order_in_group'       => (int) ($qData['order'] ?? ($questionsCount + 1)),
                        'statement'            => trim($qData['statement'] ?? 'Pregunta sin enunciado'),
                        'source_page'          => $qSourcePage,
                        'visual_transcription' => $qVisualTrans,
                        'visual_description'   => $qVisualDesc,
                        'extraction_warnings'  => $qWarnings,
                        'difficulty'           => $this->normalizeDifficulty($qData['difficulty'] ?? 'medium'),
                        'explanation'          => $qData['explanation'] ?? null,
                        'key_status'           => $keyStatus,
                        'status'               => 'draft',
                    ]);

                    // Si la pregunta tiene recurso gráfico propio (no compartido en estímulo) y coincide en página
                    if ($qHasVisual && empty($stimulusId) && $qSourcePage) {
                        $candidatePages = [$qSourcePage, $qSourcePage + 1, $qSourcePage - 1, $qSourcePage + 2, $qSourcePage - 2];
                        $matchedQAsset = null;
                        foreach ($candidatePages as $p) {
                            if (!empty($assetsByPage[$p])) {
                                $matchedQAsset = array_shift($assetsByPage[$p]);
                                break;
                            }
                        }

                        if ($matchedQAsset) {
                            $matchedQAsset->update([
                                'question_id'          => $question->id,
                                'stimulus_id'          => null,
                                'association_status'   => 'confirmed',
                                'confidence'           => ($matchedQAsset->page_number === $qSourcePage) ? 0.90 : 0.85,
                                'visual_transcription' => $qVisualTrans,
                                'visual_description'   => $qVisualDesc,
                                'associated_at'        => now(),
                            ]);

                            $question->update([
                                'question_image_path' => $matchedQAsset->storage_path,
                            ]);

                            $associatedMediaCount++;
                        }
                    }

                    $questionsCount++;

                    // Crear las 4 opciones A, B, C, D soportando cualquier formato devuelto por Gemini
                    $rawOptions = $qData['options'] ?? [];
                    $letters = ['A', 'B', 'C', 'D'];

                    foreach ($letters as $letter) {
                        $optText = "Opción {$letter}";

                        if (is_array($rawOptions)) {
                            // Caso 1: Array de objetos [ { "letter": "A", "text": "..." } ]
                            foreach ($rawOptions as $k => $ro) {
                                if (is_array($ro)) {
                                    $l = strtoupper(trim($ro['letter'] ?? $ro['option_letter'] ?? ''));
                                    if ($l === $letter) {
                                        $optText = trim($ro['text'] ?? $ro['option_text'] ?? $optText);
                                        break;
                                    }
                                } elseif (is_string($k) && strtoupper(trim($k)) === $letter) {
                                    // Caso 2: Objeto asociativo { "A": "...", "B": "..." }
                                    $optText = trim($ro);
                                    break;
                                } elseif (is_string($ro)) {
                                    // Caso 3: Array de strings [ "A) ...", "B) ..." ]
                                    if (preg_match('/^([A-Da-d])[\)\.\:\-\s]+(.*)$/u', trim($ro), $m)) {
                                        if (strtoupper($m[1]) === $letter) {
                                            $optText = trim($m[2]);
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        $isCorrect = null;
                        if ($hasVerifiable) {
                            $isCorrect = ($letter === $correctLetter);
                        }

                        QuestionOption::create([
                            'id'            => (string) Str::uuid(),
                            'question_id'   => $question->id,
                            'option_letter' => $letter,
                            'option_text'   => $optText,
                            'is_correct'    => $isCorrect,
                        ]);

                        $optionsCount++;
                    }
                }
            }

            return [
                'stimuli_count'          => $stimuliCount,
                'questions_count'        => $questionsCount,
                'options_count'          => $optionsCount,
                'associated_media_count' => $associatedMediaCount,
                'verified_keys_count'    => $verifiedKeysCount,
                'unverified_keys_count'  => $unverifiedKeysCount,
            ];
        });
    }

    /**
     * Mapea el nombre del área o título del documento al subject_id oficial de la base de datos.
     */
    protected function resolveSubjectId(string $subjectName, ?SourceDocument $document = null): int
    {
        $normalized = mb_strtolower(trim($subjectName));

        if (str_contains($normalized, 'matem') || str_contains($normalized, 'algebra') || str_contains($normalized, 'calculo')) {
            return 2; // Matemáticas
        }
        if (str_contains($normalized, 'lectura') || str_contains($normalized, 'critica') || str_contains($normalized, 'lenguaje')) {
            return 1; // Lectura Crítica
        }
        if (str_contains($normalized, 'natural') || str_contains($normalized, 'biolog') || str_contains($normalized, 'quimic') || str_contains($normalized, 'fisic')) {
            return 3; // Ciencias Naturales
        }
        if (str_contains($normalized, 'social') || str_contains($normalized, 'ciudadan') || str_contains($normalized, 'constituc')) {
            return 4; // Sociales y Ciudadanas
        }
        if (str_contains($normalized, 'ingl') || str_contains($normalized, 'english')) {
            return 5; // Inglés
        }

        // Si la pregunta no especifica área, inferir del título del documento
        if ($document) {
            $docNormalized = mb_strtolower(trim($document->title));
            if (str_contains($docNormalized, 'matem')) return 2;
            if (str_contains($docNormalized, 'natural') || str_contains($docNormalized, 'ciencias')) return 3;
            if (str_contains($docNormalized, 'social')) return 4;
            if (str_contains($docNormalized, 'ingl')) return 5;
            if (str_contains($docNormalized, 'lectura') || str_contains($docNormalized, 'critica')) return 1;
        }

        return 2; // Default a Matemáticas si no se detectó
    }

    protected function normalizeDifficulty(string $diff): string
    {
        $d = strtolower(trim($diff));
        return in_array($d, ['easy', 'medium', 'hard']) ? $d : 'medium';
    }

    protected function normalizeMediaType(string $type): string
    {
        $t = strtolower(trim($type));
        return in_array($t, ['none', 'image', 'chart', 'table', 'mixed']) ? $t : 'none';
    }

    /**
     * Prompt del sistema con reglas estrictas de fidelidad para pruebas ICFES Saber 11 multimodal.
     */
    protected function buildPromptInstruction(SourceDocument $document, int $totalPages = 0): string
    {
        $docTitle = trim($document->title ?: 'Cuadernillo ICFES');
        $subjectHint = $this->detectSubjectHint($docTitle);
        $pagesText = $totalPages > 0 ? "a lo largo de las {$totalPages} páginas del documento" : "a lo largo de todas las páginas del documento";

        return <<<PROMPT
Eres un experto pedagógico y extractor multimodal de pruebas oficiales ICFES Saber 11 de Colombia.
Analiza el documento PDF adjunto ("{$docTitle}") con máxima fidelidad tanto visual como textual {$pagesText}.
Reconstruye unidades completas de preguntas con sus textos de lectura, estímulos, viñetas, tablas, gráficas, opciones, fórmulas y transcripción de elementos visuales.

ÁREA PRINCIPAL DEL DOCUMENTO:
- Se detecta como materia de evaluación: "{$subjectHint}".
- Asigna el campo "subject_name" adecuadamente para cada pregunta ("Matemáticas", "Lectura Crítica", "Ciencias Naturales", "Sociales y Ciudadanas" o "Inglés").

INSTRUCCIÓN CRÍTICA DE COBERTURA COMPLETA:
- Extrae TODAS Y CADA UNA de las preguntas presentes en este cuadernillo desde la primera (order: 1) hasta la última (order: N) en riguroso orden correlativo.
- NO OMITAS NINGUNA PREGUNTA. NO TE DETENGAS ANTES DE COMPLETAR EL CUADERNILLO.
- El arreglo "questions" en el JSON DEBE contener todas las preguntas extraídas completas con sus 4 opciones A, B, C, D.

REGLAS ESTRICTAS DE FIDELIDAD Y EXTRACCIÓN MULTIMODAL:
1. UNIDADES COMPLETAS: Extrae todas las preguntas preservando su orden original exacto (campo "order": 1, 2, ..., N).
2. ESTÍMULOS COMPARTIDOS (Lecturas / Cómics / Infografías / Gráficas / Tablas de Datos):
   - Si una o varias preguntas dependen de una misma lectura, texto, gráfica, tabla de datos o contexto común, crea una entrada en "stimuli" con un "id_tag" único (ej: "STIM_1").
   - Cada pregunta vinculada DEBE indicar "stimulus_tag": "STIM_1".
   - Indica el número exacto de página física del PDF en "source_page".
3. ELEMENTOS MATEMÁTICOS Y GRÁFICOS:
   - Para fórmulas, ecuaciones o expresiones matemáticas, transcríbelas de forma clara y legible (usando notación estándar, e.g. x^2, f(x), fracciones o fórmulas aritméticas).
   - Para gráficas, figuras geométricas, diagramas o tablas:
     - Marca "has_visual_media": true.
     - Especifica "visual_type" entre: "chart", "table", "diagram", "geometric_figure", "comic", "infographic", "none".
     - "visual_transcription": Transcribe con fidelidad absoluta todos los números, ejes X e Y, tablas y datos legibles.
     - "visual_description": Describe qué representa la figura, gráfica o diagrama geométrico.
4. OPCIONES DE RESPUESTA:
   - Cada pregunta DEBE tener exactamente 4 opciones de respuesta con letras "A", "B", "C", "D" dentro del arreglo "options".
5. CLAVE DE RESPUESTA:
   - Si el documento contiene explícitamente la tabla oficial de respuestas, marca "has_verifiable_answer": true y "correct_option_letter": "A"|"B"|"C"|"D".
   - Si el documento NO contiene la clave oficial verificable, marca "has_verifiable_answer": false y "correct_option_letter": null. NUNCA adivines ni inventes respuestas.
6. ADVERTENCIAS DE EXTRACCIÓN:
   - Si notas alguna peculiaridad o ambigüedad, regístrala en "extraction_warnings".

Debes responder ÚNICAMENTE con un objeto JSON válido con la siguiente estructura exacta:
{
  "document_title": "Título detectado del examen",
  "detected_exam_type": "Saber 11",
  "stimuli": [
    {
      "id_tag": "STIM_1",
      "title": "Título del contexto, texto o gráfica",
      "content_text": "Texto íntegro acompañante si existe...",
      "media_type": "chart",
      "source_page": 6,
      "instructions": "RESPONDA LAS PREGUNTAS 1 Y 2 DE ACUERDO CON LA SIGUIENTE INFORMACIÓN",
      "has_visual_media": true,
      "visual_type": "table",
      "visual_transcription": "Tabla de datos...",
      "visual_description": "Tabla de frecuencias que...",
      "visual_context_hint": "Tabla en página 6"
    }
  ],
  "questions": [
    {
      "order": 1,
      "source_page": 6,
      "subject_name": "{$subjectHint}",
      "stimulus_tag": "STIM_1",
      "statement": "Enunciado de la pregunta...",
      "difficulty": "medium",
      "has_verifiable_answer": false,
      "correct_option_letter": null,
      "explanation": null,
      "has_visual_media": false,
      "visual_type": "none",
      "visual_transcription": null,
      "visual_description": null,
      "extraction_warnings": null,
      "options": [
        { "letter": "A", "text": "Texto de la opción A" },
        { "letter": "B", "text": "Texto de la opción B" },
        { "letter": "C", "text": "Texto de la opción C" },
        { "letter": "D", "text": "Texto de la opción D" }
      ]
    }
  ]
}
PROMPT;
    }

    protected function detectSubjectHint(string $title): string
    {
        $normalized = mb_strtolower(trim($title));
        if (str_contains($normalized, 'matem') || str_contains($normalized, 'algebra') || str_contains($normalized, 'calculo')) {
            return 'Matemáticas';
        }
        if (str_contains($normalized, 'lectura') || str_contains($normalized, 'critica') || str_contains($normalized, 'lenguaje')) {
            return 'Lectura Crítica';
        }
        if (str_contains($normalized, 'natural') || str_contains($normalized, 'biolog') || str_contains($normalized, 'quimic') || str_contains($normalized, 'fisic')) {
            return 'Ciencias Naturales';
        }
        if (str_contains($normalized, 'social') || str_contains($normalized, 'ciudadan')) {
            return 'Sociales y Ciudadanas';
        }
        if (str_contains($normalized, 'ingl') || str_contains($normalized, 'english')) {
            return 'Inglés';
        }

        return 'Matemáticas';
    }

    /**
     * Limpia y decodifica el JSON retornado por Gemini, tolerando bloques markdown,
     * caracteres de escape y texto circundante.
     */
    protected function cleanAndDecodeJson(string $rawText): ?array
    {
        $text = trim($rawText);

        // 1. Quitar delimitadores de bloque markdown ```json ... ``` o ``` ... ```
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $text, $matches)) {
            $candidate = trim($matches[1]);
            $decoded = json_decode($candidate, true, 512, JSON_INVALID_UTF8_IGNORE);
            if (is_array($decoded)) {
                return $this->normalizeDecodedQuestions($decoded);
            }
        }

        // 2. Intento directo sobre el texto completo
        $decoded = json_decode($text, true, 512, JSON_INVALID_UTF8_IGNORE);
        if (is_array($decoded)) {
            return $this->normalizeDecodedQuestions($decoded);
        }

        // 3. Extraer desde la primera '{' hasta la última '}'
        $firstBrace = strpos($text, '{');
        $lastBrace = strrpos($text, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $candidate = substr($text, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($candidate, true, 512, JSON_INVALID_UTF8_IGNORE);
            if (is_array($decoded)) {
                return $this->normalizeDecodedQuestions($decoded);
            }

            // Limpieza adicional de trailing commas comunes en respuestas LLM
            $cleaned = preg_replace('/,\s*([\]}])/m', '$1', $candidate);
            $decoded = json_decode($cleaned, true, 512, JSON_INVALID_UTF8_IGNORE);
            if (is_array($decoded)) {
                return $this->normalizeDecodedQuestions($decoded);
            }
        }

        // 4. Si Gemini devolvió un array plano [...] de preguntas
        $firstBracket = strpos($text, '[');
        $lastBracket = strrpos($text, ']');
        if ($firstBracket !== false && $lastBracket !== false && $lastBracket > $firstBracket) {
            $candidate = substr($text, $firstBracket, $lastBracket - $firstBracket + 1);
            $decoded = json_decode($candidate, true, 512, JSON_INVALID_UTF8_IGNORE);
            if (is_array($decoded)) {
                return $this->normalizeDecodedQuestions(['questions' => $decoded]);
            }
        }

        return null;
    }

    /**
     * Normaliza la estructura decodificada asegurando las claves 'questions' y 'stimuli'.
     */
    protected function normalizeDecodedQuestions(array $decoded): array
    {
        if (array_is_list($decoded) && ! empty($decoded) && isset($decoded[0]['statement'])) {
            return [
                'document_title' => 'Documento procesado',
                'detected_exam_type' => 'Saber 11',
                'stimuli' => [],
                'questions' => $decoded,
            ];
        }

        $questions = $decoded['questions'] ?? $decoded['preguntas'] ?? [];
        $stimuli = $decoded['stimuli'] ?? $decoded['estimulos'] ?? [];

        if (! is_array($questions)) {
            $questions = [];
        }
        if (! is_array($stimuli)) {
            $stimuli = [];
        }

        $decoded['questions'] = $questions;
        $decoded['stimuli'] = $stimuli;

        return $decoded;
    }

    /**
     * Ejecuta una auditoría de calidad automática sobre las preguntas y opciones extraídas,
     * contrastando la integridad con el PDF original y las claves oficiales.
     */
    public function runAutomatedQualityAudit(SourceDocument $document, ?string $pdfBytes = null): array
    {
        $docId = $document->id;
        $title = $document->title ?: 'Cuadernillo';
        $detectedSubject = $this->detectSubjectHint($title);

        // 1. Obtener bytes del PDF si no fueron pasados
        if (empty($pdfBytes)) {
            $bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
            $pdfBytes = $this->storageService->get($bucket, $document->storage_path);
        }

        // 2. Extraer claves oficiales del PDF si existen
        $officialKeys = $this->parseOfficialAnswerKeysFromPdf($pdfBytes);
        $hasOfficialKey = ! empty($officialKeys);

        // 3. Cargar preguntas extraídas
        $questions = Question::where('source_document_id', $docId)
            ->with(['options' => function ($q) {
                $q->orderBy('option_letter', 'asc');
            }, 'stimulus'])
            ->orderBy('order_in_group', 'asc')
            ->get();

        $totalQuestions = $questions->count();
        $readyForApprovalCount = 0;
        $requiresReviewCount = 0;
        $verifiedKeysCount = 0;
        $unverifiedKeysCount = 0;
        $issues = [];
        $ordersSeen = [];

        foreach ($questions as $q) {
            $qIssues = [];
            $order = $q->order_in_group;
            $page = $q->source_page;
            $ordersSeen[] = $order;

            // Validación de opciones
            $options = $q->options;
            if ($options->count() !== 4) {
                $qIssues[] = "Tiene {$options->count()} opciones en lugar de 4 (A, B, C, D).";
            }

            $markedKey = null;

            foreach ($options as $opt) {
                $text = trim($opt->option_text ?? '');
                if ($text === '') {
                    $qIssues[] = "La opción {$opt->option_letter} está vacía.";
                } elseif (preg_match('/^(opci[oó]n\s+[A-D]|gr[aá]fica\s+[A-D]|diseño\s+[A-D]|figura\s+[A-D])$/iu', $text) && empty($opt->option_image_path)) {
                    $qIssues[] = "La opción {$opt->option_letter} ('{$text}') es un marcador visual sin imagen asociada.";
                }

                if ($opt->is_correct) {
                    $markedKey = $opt->option_letter;
                }
            }

            // Validación de longitud de enunciado
            if (mb_strlen(trim($q->statement)) < 40 && empty($q->stimulus_id)) {
                $qIssues[] = "El enunciado es muy breve (" . mb_strlen(trim($q->statement)) . " caracteres) y no cuenta con estímulo asociado.";
            }

            // Verificación de clave oficial
            if ($hasOfficialKey && isset($officialKeys[$order])) {
                $expectedKey = $officialKeys[$order];
                if ($markedKey === $expectedKey) {
                    $q->update(['key_status' => 'verified']);
                    $verifiedKeysCount++;
                } else {
                    $q->update(['key_status' => 'unverified']);
                    $unverifiedKeysCount++;
                    $qIssues[] = "Discrepancia en clave: marcada {$markedKey}, oficial en PDF es {$expectedKey}.";
                }
            } elseif ($markedKey !== null && $q->key_status === 'verified') {
                $verifiedKeysCount++;
            } else {
                $q->update(['key_status' => 'unverified']);
                $unverifiedKeysCount++;
            }

            // Clasificación de la pregunta
            if (empty($qIssues)) {
                $readyForApprovalCount++;
                $q->update(['extraction_warnings' => null]);
            } else {
                $requiresReviewCount++;
                $reason = implode(' | ', $qIssues);
                $q->update(['extraction_warnings' => $reason]);
                $issues[] = [
                    'order'       => $order,
                    'question_id' => $q->id,
                    'page'        => $page,
                    'reason'      => $reason,
                ];
            }
        }

        // Validación de orden correlativo y duplicados
        $orderIssues = [];
        $counts = array_count_values($ordersSeen);
        foreach ($counts as $ord => $cnt) {
            if ($cnt > 1) {
                $orderIssues[] = "El número de pregunta #{$ord} está duplicado ({$cnt} veces).";
            }
        }

        $stimuliCount = Stimulus::where('source_document_id', $docId)->count();
        $pendingMediaCount = MediaAsset::where('source_document_id', $docId)->where('association_status', 'pending')->count();

        $auditReport = [
            'status'                   => 'completed',
            'audited_at'               => now()->toIso8601String(),
            'detected_subject'         => $detectedSubject,
            'total_questions'          => $totalQuestions,
            'ready_for_approval_count' => $readyForApprovalCount,
            'requires_review_count'    => $requiresReviewCount,
            'verified_keys_count'      => $verifiedKeysCount,
            'unverified_keys_count'    => $unverifiedKeysCount,
            'stimuli_count'            => $stimuliCount,
            'pending_media_count'      => $pendingMediaCount,
            'has_official_answer_key'  => $hasOfficialKey || $verifiedKeysCount > 0,
            'official_keys_found'      => count($officialKeys),
            'order_anomalies'          => $orderIssues,
            'issues_count'             => count($issues),
            'issues'                   => $issues,
        ];

        // Guardar en metadatos del documento
        $currentMeta = $document->extraction_metadata ?? [];
        $currentMeta['quality_audit'] = $auditReport;
        $document->update([
            'has_answer_key'      => $hasOfficialKey,
            'extraction_metadata' => $currentMeta,
        ]);

        return $auditReport;
    }

    /**
     * Busca y analiza la tabla oficial de respuestas del PDF si está presente.
     *
     * @return array<int, string> Mapa orden => letra oficial
     */
    protected function parseOfficialAnswerKeysFromPdf(string $pdfBytes): array
    {
        $officialKeys = [];

        // Descomprimir streams de texto del PDF
        preg_match_all('#stream[\r\n]+(.*?)[\r\n]+endstream#s', $pdfBytes, $matches);
        if (empty($matches[1])) {
            return [];
        }

        $rawText = '';
        foreach ($matches[1] as $stream) {
            $decomp = @gzuncompress($stream);
            if ($decomp === false) {
                $decomp = @gzinflate(substr($stream, 2));
            }
            if ($decomp !== false) {
                preg_match_all('#\((.*?)\)\s*(?:Tj|\')#s', $decomp, $tjMatches);
                if (! empty($tjMatches[1])) {
                    $rawText .= implode(' ', $tjMatches[1]) . "\n";
                }
                preg_match_all('#\[(.*?)\]\s*TJ#s', $decomp, $tjArrayMatches);
                if (! empty($tjArrayMatches[1])) {
                    foreach ($tjArrayMatches[1] as $arr) {
                        preg_match_all('#\((.*?)\)#s', $arr, $inner);
                        if (! empty($inner[1])) {
                            $rawText .= implode('', $inner[1]) . ' ';
                        }
                    }
                    $rawText .= "\n";
                }
            }
        }

        // Buscar encabezado "Tabla de respuestas correctas"
        $pos = stripos($rawText, 'Tabla de respuestas correctas');
        if ($pos === false) {
            return [];
        }

        $sub = substr($rawText, $pos, 40000);

        preg_match_all('/\((\d{1,3})\s+es-ES\).*?\(([A-Da-d])[\s\r\n]*es-ES/s', $sub, $parsed, PREG_SET_ORDER);
        foreach ($parsed as $p) {
            $num = (int) $p[1];
            if ($num >= 1 && $num <= 200) {
                $officialKeys[$num] = strtoupper(trim($p[2]));
            }
        }

        return $officialKeys;
    }
}
