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
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(
        protected SupabaseStorageService $storageService,
        protected PdfImageExtractor $imageExtractor
    ) {
        $this->apiKey = (string) (config('services.gemini.api_key') ?: env('GEMINI_API_KEY'));
        $rawModel = (string) (config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-3.1-flash-lite'));
        $this->model = str_replace('models/', '', $rawModel);

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

            $manifest = [
                'source_document_id' => $document->id,
                'generated_at'       => now()->toIso8601String(),
                'total_pages'        => $extractionResult['pages'] ?? 0,
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

            // 4. Preparar prompt de extracción multimodal estricta ICFES
            $systemInstruction = $this->buildPromptInstruction();

            // 5. Llamada multimodal a la API oficial de Gemini con reintento ante picos de demanda (HTTP 503/429)
            $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = null;
            $maxAttempts = 3;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
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
                    break;
                }

                $status = $response->status();
                if (in_array($status, [429, 503], true) && $attempt < $maxAttempts) {
                    Log::warning("Gemini API retornó HTTP {$status} (intento {$attempt}/{$maxAttempts}). Reintentando en 6 segundos...");
                    sleep(6);
                    continue;
                }

                $body = $response->json();
                $errorMessage = $body['error']['message'] ?? $response->body();
                throw new RuntimeException("Error en Gemini API (HTTP {$status}): {$errorMessage}");
            }

            $responseData = $response->json();
            $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($rawText)) {
                throw new RuntimeException('Gemini retornó una respuesta vacía o sin candidatos válidos.');
            }

            // 6. Decodificar y validar JSON estructurado
            $extractedJson = json_decode($rawText, true);
            if (! is_array($extractedJson) || ! isset($extractedJson['questions'])) {
                throw new RuntimeException('La respuesta de Gemini no contiene una estructura JSON válida de preguntas.');
            }

            // 7. Almacenar preguntas, estímulos y auto-asociar imágenes en transacción
            $saveSummary = $this->persistExtractedData($document, $extractedJson, $uploadedImages);

            $endTime = now();
            $durationSeconds = $endTime->diffInSeconds($startTime);

            $metadata = [
                'status'                    => 'success',
                'model'                     => $this->model,
                'started_at'                => $startTime->toIso8601String(),
                'completed_at'              => $endTime->toIso8601String(),
                'duration_seconds'          => $durationSeconds,
                'file_size_bytes'           => $fileSizeBytes,
                'images_detected_count'     => count($uploadedImages),
                'images_associated_count'   => $saveSummary['associated_media_count'],
                'stimuli_created'           => $saveSummary['stimuli_count'],
                'questions_extracted'       => $saveSummary['questions_count'],
                'options_created'           => $saveSummary['options_count'],
                'verified_answers_count'    => $saveSummary['verified_keys_count'],
                'unverified_answers_count'  => $saveSummary['unverified_keys_count'],
            ];

            // 8. Actualizar estado del documento a 'extracted'
            $document->update([
                'status'              => 'extracted',
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
            // Evitar duplicados previos en draft
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
                    'confidence'         => 1.00,
                ]);

                $assetsByPage[(int) $img['page']][] = $asset;
            }

            $associatedMediaCount = 0;

            // 2. Crear estímulos y auto-asociar imágenes de página
            $stimuliMap = [];
            $stimuliCount = 0;

            if (!empty($data['stimuli']) && is_array($data['stimuli'])) {
                foreach ($data['stimuli'] as $stimData) {
                    $tag = $stimData['id_tag'] ?? ('STIM_' . ($stimuliCount + 1));
                    $subjectId = $this->resolveSubjectId($stimData['subject_name'] ?? 'Lectura Crítica');

                    $sourcePage = isset($stimData['source_page']) && is_numeric($stimData['source_page']) && (int) $stimData['source_page'] > 0
                        ? (int) $stimData['source_page']
                        : null;

                    $hasVisual = !empty($stimData['has_visual_media'])
                        || (isset($stimData['media_type']) && in_array(strtolower($stimData['media_type']), ['image', 'chart', 'table', 'mixed'], true))
                        || (!empty($stimData['visual_type']) && $stimData['visual_type'] !== 'none');

                    $visualTranscription = !empty($stimData['visual_transcription']) ? trim($stimData['visual_transcription']) : null;
                    $visualDescription = !empty($stimData['visual_description']) ? trim($stimData['visual_description']) : null;

                    $stimulus = Stimulus::create([
                        'id'                   => (string) Str::uuid(),
                        'subject_id'           => $subjectId,
                        'source_document_id'   => $document->id,
                        'title'                => mb_substr($stimData['title'] ?? 'Texto de Lectura', 0, 255),
                        'content_text'         => $stimData['content_text'] ?? null,
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
                    $subjectId = $this->resolveSubjectId($qData['subject_name'] ?? 'Lectura Crítica');
                    
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

                    // Crear pregunta borrador
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

                    // Crear las 4 opciones A, B, C, D
                    $rawOptions = $qData['options'] ?? [];
                    $letters = ['A', 'B', 'C', 'D'];

                    foreach ($letters as $letter) {
                        $optText = "Opción {$letter}";
                        foreach ($rawOptions as $ro) {
                            if (strtoupper(trim($ro['letter'] ?? '')) === $letter) {
                                $optText = trim($ro['text'] ?? $optText);
                                break;
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

            // 4. Recursos no asociados (por ejemplo logotipos en portada página 1)
            foreach ($assetsByPage as $pg => $unlinkedAssets) {
                foreach ($unlinkedAssets as $ua) {
                    $isCover = ($pg === 1);
                    $ua->update([
                        'confidence' => $isCover ? 0.20 : 0.50,
                        'warnings'   => $isCover ? 'Elemento de maquetación o logotipo en portada' : 'Imagen pendiente de confirmación',
                    ]);
                }
            }

            return [
                'stimuli_count'          => $stimuliCount,
                'questions_count'        => $questionsCount,
                'options_count'          => $optionsCount,
                'verified_keys_count'    => $verifiedKeysCount,
                'unverified_keys_count'  => $unverifiedKeysCount,
                'associated_media_count' => $associatedMediaCount,
            ];
        });
    }

    /**
     * Mapea el nombre del área al subject_id oficial de la base de datos.
     */
    protected function resolveSubjectId(string $subjectName): int
    {
        $normalized = mb_strtolower(trim($subjectName));

        if (str_contains($normalized, 'lectura') || str_contains($normalized, 'critica') || str_contains($normalized, 'lenguaje')) {
            return 1; // Lectura Crítica
        }
        if (str_contains($normalized, 'matem') || str_contains($normalized, 'algebra') || str_contains($normalized, 'calculo')) {
            return 2; // Matemáticas
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

        return 1; // Default a Lectura Crítica para este cuadernillo
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
    protected function buildPromptInstruction(): string
    {
        return <<<PROMPT
Eres un experto pedagógico y extractor multimodal de pruebas oficiales ICFES Saber 11 de Colombia.
Analiza el documento PDF adjunto con máxima fidelidad tanto visual como textual a lo largo de todas sus páginas.
Reconstruye unidades completas de preguntas con sus textos de lectura, estímulos, viñetas, tablas, gráficas, opciones y transcripción de elementos visuales.

INSTRUCCIÓN CRÍTICA DE COBERTURA COMPLETA:
- Este cuadernillo contiene exactamente 49 preguntas de Lectura Crítica numeradas del 1 al 49 a lo largo de las 44 páginas del documento.
- DEBES EXTRAER TODAS Y CADA UNA DE LAS 49 PREGUNTAS (desde order: 1 hasta order: 49) Y TODOS LOS ESTÍMULOS ASOCIADOS.
- NO OMITAS NINGUNA PREGUNTA. NO TE DETENGAS ANTES DE COMPLETAR LA PREGUNTA 49.
- El arreglo "questions" en el JSON DEBE contener las 49 preguntas completas.

REGLAS ESTRICTAS DE FIDELIDAD Y EXTRACCIÓN MULTIMODAL:
1. UNIDADES COMPLETAS: Extrae todas las preguntas preservando rigurosamente su orden original exacto (campo "order": 1, 2, ..., 49).
2. ESTÍMULOS COMPARTIDOS (Lecturas / Cómics / Infografías / Gráficas):
   - Si una o varias preguntas dependen de una misma lectura, texto, historieta o infografía, crea una entrada en "stimuli" con un "id_tag" único (ej: "STIM_1").
   - Cada pregunta vinculada DEBE indicar "stimulus_tag": "STIM_1".
   - Indica el número exacto de página física del PDF (1 a 44) en "source_page".
3. EXTRACCIÓN MULTIMODAL DE IMÁGENES Y CONTENIDO VISUAL:
   - Para cada estímulo o pregunta con componente gráfico (historietas, caricaturas, viñetas de cómic, infografías, tablas, diagramas, gráficas de ejes o mapas):
     - Marca "has_visual_media": true.
     - Especifica "visual_type" entre: "comic", "cartoon", "infographic", "chart", "table", "map", "diagram", "photograph", "none".
     - "visual_transcription": Transcribe con fidelidad absoluta TODO el texto legible dentro de la imagen:
       * Diálogos de historietas y globos de texto (viñeta por viñeta en orden de lectura).
       * Títulos, subtítulos, etiquetas y créditos de autor.
       * Datos numéricos, ejes X e Y, unidades de medida y leyendas en gráficos.
       * Celdas, filas y columnas en tablas.
       * Si un texto está borroso o ilegible, escribe "[texto ilegible]". NUNCA inventes texto que no puedas leer.
     - "visual_description": Descripción detallada de lo que representa la imagen (personajes, expresiones, viñetas, escenario, tendencia visual de gráficos).
     - "visual_context_hint": Frase breve de identificación (ej: "Historieta cómica 4 viñetas en página 15").
4. OPCIONES DE RESPUESTA:
   - Cada pregunta DEBE tener exactamente 4 opciones de respuesta con letras "A", "B", "C", "D".
5. CLAVE DE RESPUESTA:
   - Si el documento contiene explícitamente la tabla oficial de respuestas, marca "has_verifiable_answer": true y "correct_option_letter": "A"|"B"|"C"|"D".
   - Si el documento NO contiene la clave oficial verificable, marca "has_verifiable_answer": false y "correct_option_letter": null. NUNCA adivines ni inventes respuestas.
6. ADVERTENCIAS DE EXTRACCIÓN:
   - Si notas alguna peculiaridad, texto cortado o ambigüedad, regístrala en "extraction_warnings".

Debes responder ÚNICAMENTE con un objeto JSON válido con la siguiente estructura exacta:
{
  "document_title": "Título detectado del examen",
  "detected_exam_type": "Saber 11",
  "stimuli": [
    {
      "id_tag": "STIM_1",
      "title": "Título de la lectura o historieta",
      "content_text": "Texto íntegro acompañante si existe...",
      "media_type": "image",
      "source_page": 15,
      "instructions": "RESPONDA LAS PREGUNTAS 1 Y 2 DE ACUERDO CON LA SIGUIENTE HISTORIETA",
      "has_visual_media": true,
      "visual_type": "comic",
      "visual_transcription": "Viñeta 1: Personaje dice '...' Viñeta 2: ...",
      "visual_description": "Historieta de 4 viñetas donde...",
      "visual_context_hint": "Historieta cómica en página 15"
    }
  ],
  "questions": [
    {
      "order": 1,
      "source_page": 15,
      "subject_name": "Lectura Crítica",
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
}

