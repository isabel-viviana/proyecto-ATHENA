<?php

namespace App\Services;

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
        protected SupabaseStorageService $storageService
    ) {
        $this->apiKey = (string) (config('services.gemini.api_key') ?: env('GEMINI_API_KEY'));
        $rawModel = (string) (config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-2.5-flash'));
        $this->model = str_replace('models/', '', $rawModel);

        if (empty($this->apiKey)) {
            throw new RuntimeException('Configuración incompleta: GEMINI_API_KEY no está definida en el archivo .env.');
        }
    }

    /**
     * Procesa un documento PDF almacenado en Supabase Storage y extrae preguntas mediante Gemini.
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
            $bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
            
            if (! $this->storageService->exists($bucket, $document->storage_path)) {
                throw new RuntimeException("El archivo físico no existe en Supabase Storage ({$bucket}: {$document->storage_path}).");
            }

            $pdfBytes = $this->storageService->get($bucket, $document->storage_path);
            if (empty($pdfBytes)) {
                throw new RuntimeException("El archivo PDF recuperado de Storage está vacío.");
            }

            $base64Pdf = base64_encode($pdfBytes);
            $fileSizeBytes = strlen($pdfBytes);

            // 3. Preparar prompt de extracción estricta ICFES
            $systemInstruction = $this->buildPromptInstruction();

            // 4. Llamada multimodal a la API oficial de Gemini
            $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(180)
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
                        'temperature'      => 0.1, // Temperatura baja para máxima fidelidad
                    ],
                ]);

            if (! $response->successful()) {
                $status = $response->status();
                $body = $response->json();
                $errorMessage = $body['error']['message'] ?? $response->body();
                throw new RuntimeException("Error en Gemini API (HTTP {$status}): {$errorMessage}");
            }

            $responseData = $response->json();
            $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($rawText)) {
                throw new RuntimeException('Gemini retornó una respuesta vacía o sin candidatos válidos.');
            }

            // 5. Decodificar y validar JSON estructurado
            $extractedJson = json_decode($rawText, true);
            if (! is_array($extractedJson) || ! isset($extractedJson['questions'])) {
                throw new RuntimeException('La respuesta de Gemini no contiene una estructura JSON válida de preguntas.');
            }

            // 6. Almacenar preguntas y estímulos en transacción de base de datos
            $saveSummary = $this->persistExtractedData($document, $extractedJson);

            $endTime = now();
            $durationSeconds = $endTime->diffInSeconds($startTime);

            $metadata = [
                'status'                    => 'success',
                'model'                     => $this->model,
                'started_at'                => $startTime->toIso8601String(),
                'completed_at'              => $endTime->toIso8601String(),
                'duration_seconds'          => $durationSeconds,
                'file_size_bytes'           => $fileSizeBytes,
                'stimuli_created'           => $saveSummary['stimuli_count'],
                'questions_extracted'       => $saveSummary['questions_count'],
                'options_created'           => $saveSummary['options_count'],
                'verified_answers_count'    => $saveSummary['verified_keys_count'],
                'unverified_answers_count'  => $saveSummary['unverified_keys_count'],
            ];

            // 7. Actualizar estado del documento a 'extracted'
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
     * Persiste los estímulos y preguntas en la base de datos Supabase PostgreSQL.
     */
    protected function persistExtractedData(SourceDocument $document, array $data): array
    {
        return DB::connection('question_bank')->transaction(function () use ($document, $data) {
            // Evitar duplicados: Si el documento ya tenía preguntas en draft vinculadas, limpiar antes
            $oldQuestions = Question::where('source_document_id', $document->id)
                ->where('status', 'draft')
                ->get();

            foreach ($oldQuestions as $oldQ) {
                QuestionOption::where('question_id', $oldQ->id)->delete();
                $oldQ->delete();
            }

            Stimulus::where('source_document_id', $document->id)->delete();

            // Mapa de estímulos creados: tag => Stimulus model
            $stimuliMap = [];
            $stimuliCount = 0;

            if (!empty($data['stimuli']) && is_array($data['stimuli'])) {
                foreach ($data['stimuli'] as $stimData) {
                    $tag = $stimData['id_tag'] ?? ('STIM_' . ($stimuliCount + 1));
                    $subjectId = $this->resolveSubjectId($stimData['subject_name'] ?? 'Lectura Crítica');

                    $sourcePage = isset($stimData['source_page']) && is_numeric($stimData['source_page']) && (int) $stimData['source_page'] > 0
                        ? (int) $stimData['source_page']
                        : null;

                    $stimulus = Stimulus::create([
                        'id'                 => (string) Str::uuid(),
                        'subject_id'         => $subjectId,
                        'source_document_id' => $document->id,
                        'title'              => mb_substr($stimData['title'] ?? 'Texto de Lectura', 0, 255),
                        'content_text'       => $stimData['content_text'] ?? null,
                        'media_type'         => $this->normalizeMediaType($stimData['media_type'] ?? 'none'),
                        'source_page'        => $sourcePage,
                        'instructions'       => $stimData['instructions'] ?? null,
                    ]);

                    $stimuliMap[$tag] = $stimulus;
                    $stimuliCount++;
                }
            }

            $questionsCount = 0;
            $optionsCount = 0;
            $verifiedKeysCount = 0;
            $unverifiedKeysCount = 0;

            if (!empty($data['questions']) && is_array($data['questions'])) {
                foreach ($data['questions'] as $qData) {
                    $subjectId = $this->resolveSubjectId($qData['subject_name'] ?? 'Matemáticas');
                    
                    // Asociar estímulo si existe
                    $stimulusId = null;
                    if (!empty($qData['stimulus_tag']) && isset($stimuliMap[$qData['stimulus_tag']])) {
                        $stimulus = $stimuliMap[$qData['stimulus_tag']];
                        $stimulusId = $stimulus->id;
                        // Forzar que el subject_id coincida con el estímulo para respetar la FK compuesta
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

                    // Crear pregunta borrador
                    $question = Question::create([
                        'id'                 => (string) Str::uuid(),
                        'root_question_id'   => (string) Str::uuid(),
                        'version'            => 1,
                        'is_current_version' => true,
                        'subject_id'         => $subjectId,
                        'topic_id'           => null, // Nullable en borrador autorizado
                        'stimulus_id'        => $stimulusId,
                        'source_document_id' => $document->id,
                        'order_in_group'     => (int) ($qData['order'] ?? ($questionsCount + 1)),
                        'statement'          => trim($qData['statement'] ?? 'Pregunta sin enunciado'),
                        'source_page'        => $qSourcePage,
                        'difficulty'         => $this->normalizeDifficulty($qData['difficulty'] ?? 'medium'),
                        'explanation'        => $qData['explanation'] ?? null,
                        'key_status'         => $keyStatus,
                        'status'             => 'draft',
                    ]);

                    $questionsCount++;

                    // Crear las 4 opciones A, B, C, D
                    $rawOptions = $qData['options'] ?? [];
                    $letters = ['A', 'B', 'C', 'D'];

                    foreach ($letters as $letter) {
                        // Buscar la opción correspondiente
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

            return [
                'stimuli_count'         => $stimuliCount,
                'questions_count'       => $questionsCount,
                'options_count'         => $optionsCount,
                'verified_keys_count'   => $verifiedKeysCount,
                'unverified_keys_count' => $unverifiedKeysCount,
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

        return 2; // Default a Matemáticas si no se detecta
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
     * Prompt del sistema con reglas estrictas de fidelidad para pruebas ICFES Saber 11.
     */
    protected function buildPromptInstruction(): string
    {
        return <<<PROMPT
Eres un experto pedagógico en las pruebas oficiales ICFES Saber 11 de Colombia.
Analiza el documento PDF adjunto y extrae con máxima fidelidad todas las preguntas, textos de lectura, estímulos y opciones de respuesta.

REGLAS ESTRICTAS DE FIDELIDAD:
1. NO inventes preguntas, opciones ni respuestas que no aparezcan en el documento.
2. Si varias preguntas dependen de una misma lectura, texto, contexto o gráfica, debes extraer el texto compartido como un estímulo en "stimuli" asignándole un "id_tag" único (ej: "STIM_1"). Luego, cada pregunta asociada debe indicar "stimulus_tag": "STIM_1".
3. Identifica el área académica ("subject_name") entre: "Lectura Crítica", "Matemáticas", "Ciencias Naturales", "Sociales y Ciudadanas", "Inglés".
4. Cada pregunta DEBE tener exactamente 4 opciones de respuesta con letras "A", "B", "C", "D".
5. CLAVE DE RESPUESTA:
   - Solo marca "has_verifiable_answer": true y "correct_option_letter": "A"|"B"|"C"|"D" si el documento fuente incluye explícitamente la tabla o clave de respuestas oficial.
   - Si el documento NO incluye la clave de respuestas verificable, DEBES marcar "has_verifiable_answer": false y "correct_option_letter": null. NUNCA adivines ni inventes la respuesta.
6. Preserva el orden original de las preguntas mediante el campo "order".
7. Estima la dificultad pedagógica ("easy", "medium", "hard").

Debes responder ÚNICAMENTE con un objeto JSON válido con la siguiente estructura exacta:
{
  "document_title": "Título detectado del examen",
  "detected_exam_type": "Saber 11",
  "stimuli": [
    {
      "id_tag": "STIM_1",
      "title": "Título de la lectura o gráfica",
      "content_text": "Texto íntegro del estímulo...",
      "media_type": "none",
      "source_page": 1,
      "instructions": "RESPONDA LAS PREGUNTAS 1 A 2 DE ACUERDO CON EL SIGUIENTE TEXTO"
    }
  ],
  "questions": [
    {
      "order": 1,
      "source_page": 1,
      "subject_name": "Lectura Crítica",
      "stimulus_tag": "STIM_1",
      "statement": "Enunciado de la pregunta...",
      "difficulty": "medium",
      "has_verifiable_answer": false,
      "correct_option_letter": null,
      "explanation": null,
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

