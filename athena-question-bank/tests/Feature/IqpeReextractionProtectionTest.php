<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Models\Stimulus;
use App\Services\GeminiQuestionExtractionService;
use App\Services\PdfImageExtractor;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class IqpeReextractionProtectionTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    protected string $adminToken;
    protected array $inMemoryStorage = [];
    protected array $uploadHistory = [];
    protected array $deleteHistory = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();
        $this->inMemoryStorage = [];
        $this->uploadHistory = [];
        $this->deleteHistory = [];

        config([
            'services.gemini.api_key'                        => 'test-gemini-key-p05',
            'services.gemini.model'                          => 'gemini-3-flash-preview',
            'services.gemini.fallback_model'                 => 'gemini-3.1-flash-lite',
            'services.gemini.extraction_retry_delay_seconds' => 0,
        ]);

        $loginRes = $this->postJson('/api/v1/admin/auth/login', [
            'email'    => config('services.athena.admin_email', 'admin@athena.edu.co'),
            'password' => config('services.athena.admin_password', 'admin123456'),
        ]);
        $loginRes->assertStatus(200);
        $this->adminToken = (string) $loginRes->json('token');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function makeValidPngBytes(string $seed = 'default'): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ) . "::PNG_PAYLOAD::{$seed}";
    }

    protected function makeValidPdfBytes(): string
    {
        return "%PDF-1.4\n% ATHENA-P05-TEST\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n%%EOF";
    }

    protected function putInMemoryStorage(string $bucket, string $path, string $bytes): void
    {
        $this->inMemoryStorage["{$bucket}:{$path}"] = $bytes;
    }

    protected function getFromMemoryStorage(string $bucket, string $path): ?string
    {
        return $this->inMemoryStorage["{$bucket}:{$path}"] ?? null;
    }

    protected function bindStorageAndImageMocks(SourceDocument $doc, array $extractedImages): void
    {
        $pdfBytes = $this->makeValidPdfBytes();
        $docBucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
        $this->putInMemoryStorage($docBucket, $doc->storage_path, $pdfBytes);

        $storageMock = Mockery::mock(SupabaseStorageService::class);
        $storageMock->shouldReceive('exists')
            ->andReturnUsing(function (string $bucket, string $path) {
                return array_key_exists("{$bucket}:{$path}", $this->inMemoryStorage);
            });
        $storageMock->shouldReceive('get')
            ->andReturnUsing(function (string $bucket, string $path) {
                return $this->inMemoryStorage["{$bucket}:{$path}"] ?? null;
            });
        $storageMock->shouldReceive('upload')
            ->andReturnUsing(function (string $bucket, string $path, mixed $contents) {
                $this->uploadHistory[] = "{$bucket}:{$path}";
                $this->inMemoryStorage["{$bucket}:{$path}"] = (string) $contents;
                return true;
            });
        $storageMock->shouldReceive('delete')
            ->andReturnUsing(function (string $bucket, string $path, bool $force = false) {
                if (! $force) {
                    throw new \InvalidArgumentException('Confirmación requerida');
                }
                $this->deleteHistory[] = "{$bucket}:{$path}";
                unset($this->inMemoryStorage["{$bucket}:{$path}"]);
                return true;
            });

        $this->app->instance(SupabaseStorageService::class, $storageMock);

        $imageExtractorMock = Mockery::mock(PdfImageExtractor::class);
        $imageExtractorMock->shouldReceive('extract')->andReturn([
            'pages'  => 5,
            'images' => $extractedImages,
        ]);
        $this->app->instance(PdfImageExtractor::class, $imageExtractorMock);
    }

    /**
     * PRUEBAS 1, 3, 4, 5 y 10:
     * - Una pregunta publicada conserva exactamente los mismos bytes después de una reextracción (incluso si el PDF trae bytes distintos en la misma página/objeto).
     * - Varias preguntas que comparten un Stimulus siguen utilizando el archivo original intacto.
     * - Una imagen idéntica reutiliza el archivo existente sin volver a subirlo.
     * - Una imagen con la misma ruta candidata pero bytes diferentes genera una ruta independiente con hash y no sobrescribe la protegida.
     * - El SHA-256 físico en Storage coincide con el registrado en la base de datos para todos los activos protegidos.
     */
    public function test_published_questions_stimuli_and_media_assets_preserve_exact_physical_bytes_during_forced_reextraction(): void
    {
        $mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');
        $docId = (string) Str::uuid();
        $doc = SourceDocument::create([
            'id'             => $docId,
            'title'          => 'Cuadernillo Ciencias Naturales P0-5 Published',
            'storage_path'   => "documents/2026/{$docId}.pdf",
            'exam_type'      => 'Saber 11',
            'status'         => 'extracted',
            'has_answer_key' => true,
        ]);

        // Bytes físicos originales para el Stimulus compartido (página 1, obj 1) y para Q2 directa (página 2, obj 1)
        $originalStimBytes = $this->makeValidPngBytes('ORIGINAL_PROTECTED_STIMULUS_PAGE1_OBJ1');
        $originalStimSha256 = hash('sha256', $originalStimBytes);
        $stimAssetPath = "extracted/{$docId}/page-01_obj1.png";
        $this->putInMemoryStorage($mediaBucket, $stimAssetPath, $originalStimBytes);

        $originalQ2Bytes = $this->makeValidPngBytes('ORIGINAL_PROTECTED_Q2_PAGE2_OBJ1');
        $originalQ2Sha256 = hash('sha256', $originalQ2Bytes);
        $q2AssetPath = "extracted/{$docId}/page-02_obj1.png";
        $this->putInMemoryStorage($mediaBucket, $q2AssetPath, $originalQ2Bytes);

        // Manifest original protegido
        $originalManifestBytes = json_encode(['source_document_id' => $docId, 'original' => true]);
        $this->putInMemoryStorage($mediaBucket, "extracted/{$docId}/manifest.json", $originalManifestBytes);

        // Stimulus protegido compartido por Q1 (published) y Q4 (published)
        $publishedStimulusId = (string) Str::uuid();
        Stimulus::create([
            'id'                   => $publishedStimulusId,
            'source_document_id'   => $docId,
            'subject_id'           => 3,
            'title'                => 'Experimento de fotosíntesis acuática aprobado',
            'content_text'         => 'Texto oficial aprobado del estímulo sobre Elodea y producción de oxígeno.',
            'media_type'           => 'image',
            'media_path'           => $stimAssetPath,
            'source_page'          => 1,
            'visual_transcription' => 'Tabla original aprobada',
            'visual_description'   => 'Montaje experimental original',
        ]);

        $stimMediaAssetId = (string) Str::uuid();
        MediaAsset::create([
            'id'                 => $stimMediaAssetId,
            'source_document_id' => $docId,
            'stimulus_id'        => $publishedStimulusId,
            'question_id'        => null,
            'storage_path'       => $stimAssetPath,
            'page_number'        => 1,
            'width'              => 500,
            'height'             => 400,
            'mime_type'          => 'image/png',
            'sha256'             => $originalStimSha256,
            'association_status' => 'confirmed',
            'confidence'         => 0.98,
        ]);

        // Q1 publicada con Stimulus compartido
        $publishedQ1Id = (string) Str::uuid();
        Question::create([
            'id'                   => $publishedQ1Id,
            'root_question_id'     => $publishedQ1Id,
            'version'              => 1,
            'is_current_version'   => true,
            'subject_id'           => 3,
            'stimulus_id'          => $publishedStimulusId,
            'source_document_id'   => $docId,
            'order_in_group'       => 1,
            'statement'            => 'Enunciado oficial publicado de la pregunta #1 que NO debe ser modificado.',
            'source_page'          => 1,
            'difficulty'           => 'medium',
            'explanation'          => 'Explicación curada por comité académico.',
            'key_status'           => 'verified',
            'extraction_warnings'  => null,
            'status'               => 'published',
            'published_at'         => now()->subDay(),
        ]);

        foreach (['A' => 'Opción A oficial Q1', 'B' => 'Opción B oficial Q1', 'C' => 'Opción C oficial Q1', 'D' => 'Opción D oficial Q1'] as $letter => $text) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $publishedQ1Id,
                'option_letter' => $letter,
                'option_text'   => $text,
                'is_correct'    => $letter === 'B',
            ]);
        }

        // Q4 publicada que también comparte el mismo Stimulus ($publishedStimulusId)
        $publishedQ4Id = (string) Str::uuid();
        Question::create([
            'id'                   => $publishedQ4Id,
            'root_question_id'     => $publishedQ4Id,
            'version'              => 1,
            'is_current_version'   => true,
            'subject_id'           => 3,
            'stimulus_id'          => $publishedStimulusId,
            'source_document_id'   => $docId,
            'order_in_group'       => 4,
            'statement'            => 'Enunciado oficial publicado de la pregunta #4 que comparte el mismo Stimulus.',
            'source_page'          => 1,
            'difficulty'           => 'medium',
            'key_status'           => 'verified',
            'status'               => 'published',
            'published_at'         => now()->subDay(),
        ]);
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $publishedQ4Id,
                'option_letter' => $letter,
                'option_text'   => "Opción {$letter} oficial Q4",
                'is_correct'    => $letter === 'A',
            ]);
        }

        // Q2 publicada con MediaAsset directo (sin Stimulus)
        $publishedQ2Id = (string) Str::uuid();
        Question::create([
            'id'                   => $publishedQ2Id,
            'root_question_id'     => $publishedQ2Id,
            'version'              => 1,
            'is_current_version'   => true,
            'subject_id'           => 3,
            'stimulus_id'          => null,
            'source_document_id'   => $docId,
            'order_in_group'       => 2,
            'statement'            => 'Enunciado oficial publicado de la pregunta #2 con gráfica directa.',
            'question_image_path'  => $q2AssetPath,
            'source_page'          => 2,
            'difficulty'           => 'hard',
            'key_status'           => 'verified',
            'extraction_warnings'  => null,
            'status'               => 'published',
            'published_at'         => now()->subDay(),
        ]);

        foreach (['A' => 'Opción A oficial Q2', 'B' => 'Opción B oficial Q2', 'C' => 'Opción C oficial Q2', 'D' => 'Opción D oficial Q2'] as $letter => $text) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $publishedQ2Id,
                'option_letter' => $letter,
                'option_text'   => $text,
                'is_correct'    => $letter === 'C',
            ]);
        }

        $q2MediaAssetId = (string) Str::uuid();
        MediaAsset::create([
            'id'                 => $q2MediaAssetId,
            'source_document_id' => $docId,
            'stimulus_id'        => null,
            'question_id'        => $publishedQ2Id,
            'storage_path'       => $q2AssetPath,
            'page_number'        => 2,
            'width'              => 600,
            'height'             => 350,
            'mime_type'          => 'image/png',
            'sha256'             => $originalQ2Sha256,
            'association_status' => 'confirmed',
            'confidence'         => 0.95,
        ]);

        // En la reextracción:
        // - Página 1, obj 1 viene con EXACTAMENTE los mismos bytes ($originalStimBytes) -> debe reutilizarse sin volver a subirse.
        // - Página 2, obj 1 viene con BYTES DIFERENTES ($differentPage2Bytes) -> NO debe sobrescribir $q2AssetPath, debe generar ruta con hash y asociarse a Q3 (draft en pág 2).
        $differentPage2Bytes = $this->makeValidPngBytes('NEW_DIFFERENT_IMAGE_ON_PAGE2_OBJ1_FOR_DRAFT');
        $differentPage2Sha256 = hash('sha256', $differentPage2Bytes);
        $expectedNewHashedPath = sprintf('extracted/%s/page-02_obj1_%s.png', $docId, substr($differentPage2Sha256, 0, 12));

        $this->bindStorageAndImageMocks($doc, [
            ['status' => 'ok', 'page' => 1, 'object' => 1, 'width' => 500, 'height' => 400, 'mime' => 'image/png', 'ext' => 'png', 'bytes' => $originalStimBytes],
            ['status' => 'ok', 'page' => 2, 'object' => 1, 'width' => 600, 'height' => 350, 'mime' => 'image/png', 'ext' => 'png', 'bytes' => $differentPage2Bytes],
        ]);

        $geminiPayload = [
            'stimuli' => [
                [
                    'id_tag'           => 'STIM_1',
                    'subject_name'     => 'Ciencias Naturales',
                    'title'            => 'Título reextraído que NO debe sobrescribir al publicado',
                    'content_text'     => 'Texto alterado por reextracción',
                    'has_visual_media' => true,
                    'visual_type'      => 'image',
                    'source_page'      => 1,
                ],
            ],
            'questions' => [
                [
                    'order'                 => 1,
                    'subject_name'          => 'Ciencias Naturales',
                    'stimulus_tag'          => 'STIM_1',
                    'statement'             => 'Enunciado reextraído distinto para Q1',
                    'source_page'           => 1,
                    'has_verifiable_answer' => false,
                    'correct_option_letter' => null,
                    'options'               => ['A' => 'Nueva A', 'B' => 'Nueva B', 'C' => 'Nueva C', 'D' => 'Nueva D'],
                ],
                [
                    'order'                 => 2,
                    'subject_name'          => 'Ciencias Naturales',
                    'stimulus_tag'          => null,
                    'statement'             => 'Enunciado reextraído distinto para Q2',
                    'has_visual_media'      => true,
                    'visual_type'           => 'graph',
                    'source_page'           => 2,
                    'has_verifiable_answer' => false,
                    'correct_option_letter' => null,
                    'options'               => ['A' => 'Nueva A2', 'B' => 'Nueva B2', 'C' => 'Nueva C2', 'D' => 'Nueva D2'],
                ],
                [
                    'order'                 => 3,
                    'subject_name'          => 'Ciencias Naturales',
                    'stimulus_tag'          => null,
                    'statement'             => 'Enunciado nuevo para la pregunta #3 en borrador que usa la nueva imagen de página 2.',
                    'has_visual_media'      => true,
                    'visual_type'           => 'diagram',
                    'source_page'           => 2,
                    'has_verifiable_answer' => true,
                    'correct_option_letter' => 'A',
                    'options'               => ['A' => 'Opción A3', 'B' => 'Opción B3', 'C' => 'Opción C3', 'D' => 'Opción D3'],
                ],
                [
                    'order'                 => 4,
                    'subject_name'          => 'Ciencias Naturales',
                    'stimulus_tag'          => 'STIM_1',
                    'statement'             => 'Enunciado reextraído para Q4',
                    'source_page'           => 1,
                    'has_verifiable_answer' => true,
                    'correct_option_letter' => 'A',
                    'options'               => ['A' => 'Opción A4', 'B' => 'Opción B4', 'C' => 'Opción C4', 'D' => 'Opción D4'],
                ],
            ],
        ];

        Http::fake([
            '*generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($geminiPayload, JSON_UNESCAPED_UNICODE)]]]],
                ],
            ], 200),
        ]);

        $res = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson("/api/v1/admin/documents/{$docId}/retry", [
                'force_extract' => true,
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.skip_extraction', false);

        app(GeminiQuestionExtractionService::class)->extractQuestionsFromDocument($doc->fresh());

        // 1. Verificar que los bytes físicos de los activos protegidos son EXACTAMENTE los mismos
        $this->assertSame($originalStimBytes, $this->getFromMemoryStorage($mediaBucket, $stimAssetPath));
        $this->assertSame($originalQ2Bytes, $this->getFromMemoryStorage($mediaBucket, $q2AssetPath));
        $this->assertSame($originalManifestBytes, $this->getFromMemoryStorage($mediaBucket, "extracted/{$docId}/manifest.json"));

        // 2. Verificar que la imagen idéntica ($stimAssetPath) NO fue re-subida a Storage
        $this->assertNotContains("{$mediaBucket}:{$stimAssetPath}", $this->uploadHistory);
        // Y que la imagen protegida con colisión ($q2AssetPath) TAMPOCO fue sobrescrita en Storage
        $this->assertNotContains("{$mediaBucket}:{$q2AssetPath}", $this->uploadHistory);

        // 3. Verificar que la imagen con bytes distintos en page 2 obj 1 se subió bajo la nueva ruta inmutable con hash
        $this->assertContains("{$mediaBucket}:{$expectedNewHashedPath}", $this->uploadHistory);
        $this->assertSame($differentPage2Bytes, $this->getFromMemoryStorage($mediaBucket, $expectedNewHashedPath));

        // 4. Verificar que Q1 y Q4 siguen compartiendo el mismo Stimulus y el archivo original
        $q1After = Question::find($publishedQ1Id);
        $q4After = Question::find($publishedQ4Id);
        $stimAfter = Stimulus::find($publishedStimulusId);
        $this->assertSame($publishedStimulusId, $q1After->stimulus_id);
        $this->assertSame($publishedStimulusId, $q4After->stimulus_id);
        $this->assertSame($stimAssetPath, $stimAfter->media_path);

        // 5. Verificar que Q2 publicada mantiene su ruta original intacta, mientras Q3 (draft) usa la nueva ruta con hash
        $q2After = Question::find($publishedQ2Id);
        $this->assertSame($q2AssetPath, $q2After->question_image_path);

        $q3After = Question::where('source_document_id', $docId)->where('order_in_group', 3)->first();
        $this->assertNotNull($q3After);
        $this->assertSame('draft', $q3After->status);
        $this->assertSame($expectedNewHashedPath, $q3After->question_image_path);

        // 6. Verificar que el SHA-256 físico en Storage coincide exactamente con el registrado en BD para todos los activos protegidos
        foreach ([$stimMediaAssetId, $q2MediaAssetId] as $protAssetId) {
            $assetRow = MediaAsset::find($protAssetId);
            $this->assertNotNull($assetRow);
            $physicalBytes = $this->getFromMemoryStorage($mediaBucket, $assetRow->storage_path);
            $this->assertNotNull($physicalBytes);
            $this->assertSame($assetRow->sha256, hash('sha256', $physicalBytes));
        }

        // 7. Verificar compatibilidad con MediaController::show tanto para la ruta protegida como para la nueva ruta con hash
        $mediaProtRes = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->get('/api/v1/admin/media?path=' . urlencode($q2AssetPath));
        $mediaProtRes->assertStatus(200);
        $this->assertSame($originalQ2Bytes, $mediaProtRes->getContent());

        $mediaHashRes = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->get('/api/v1/admin/media?path=' . urlencode($expectedNewHashedPath));
        $mediaHashRes->assertStatus(200);
        $this->assertSame($differentPage2Bytes, $mediaHashRes->getContent());
    }

    /**
     * PRUEBA 2 y 10:
     * Una pregunta en revisión (review) conserva exactamente los mismos bytes físicos y su SHA-256 coincide con BD,
     * incluso si durante la reextracción el PDF trae otros bytes en la misma página/objeto.
     */
    public function test_review_questions_preserve_exact_physical_bytes_during_forced_reextraction(): void
    {
        $mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');
        $docId = (string) Str::uuid();
        $doc = SourceDocument::create([
            'id'             => $docId,
            'title'          => 'Cuadernillo Lectura Crítica P0-5 Review',
            'storage_path'   => "documents/2026/{$docId}.pdf",
            'exam_type'      => 'Saber 11',
            'status'         => 'extracted',
            'has_answer_key' => true,
        ]);

        $reviewStimulusId = (string) Str::uuid();
        $reviewStimAssetPath = "extracted/{$docId}/page-01_obj1.png";
        $originalReviewBytes = $this->makeValidPngBytes('ORIGINAL_REVIEW_IMAGE_BYTES');
        $originalReviewSha256 = hash('sha256', $originalReviewBytes);
        $this->putInMemoryStorage($mediaBucket, $reviewStimAssetPath, $originalReviewBytes);

        Stimulus::create([
            'id'                 => $reviewStimulusId,
            'source_document_id' => $docId,
            'subject_id'         => 1,
            'title'              => 'Infografía en revisión sobre transición energética',
            'content_text'       => 'Contenido validado por revisor en estado review.',
            'media_type'         => 'image',
            'media_path'         => $reviewStimAssetPath,
            'source_page'        => 1,
        ]);

        $reviewMediaId = (string) Str::uuid();
        MediaAsset::create([
            'id'                 => $reviewMediaId,
            'source_document_id' => $docId,
            'stimulus_id'        => $reviewStimulusId,
            'question_id'        => null,
            'storage_path'       => $reviewStimAssetPath,
            'page_number'        => 1,
            'width'              => 800,
            'height'             => 600,
            'mime_type'          => 'image/png',
            'sha256'             => $originalReviewSha256,
            'association_status' => 'confirmed',
        ]);

        $reviewQuestionId = (string) Str::uuid();
        Question::create([
            'id'                 => $reviewQuestionId,
            'root_question_id'   => $reviewQuestionId,
            'version'            => 1,
            'is_current_version' => true,
            'subject_id'         => 1,
            'stimulus_id'        => $reviewStimulusId,
            'source_document_id' => $docId,
            'order_in_group'     => 1,
            'statement'          => 'Pregunta en estado review curada sobre la infografía de transición energética.',
            'source_page'        => 1,
            'difficulty'         => 'medium',
            'key_status'         => 'verified',
            'status'             => 'review',
        ]);

        foreach (['A', 'B', 'C', 'D'] as $letter) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $reviewQuestionId,
                'option_letter' => $letter,
                'option_text'   => "Opción {$letter} en review",
                'is_correct'    => $letter === 'D',
            ]);
        }

        // Simular que el PDF reextraído trae bytes distintos para page 1 obj 1
        $alteredBytesAttempt = $this->makeValidPngBytes('ALTERED_BYTES_THAT_MUST_NOT_OVERWRITE_REVIEW');
        $this->bindStorageAndImageMocks($doc, [
            ['status' => 'ok', 'page' => 1, 'object' => 1, 'width' => 800, 'height' => 600, 'mime' => 'image/png', 'ext' => 'png', 'bytes' => $alteredBytesAttempt],
        ]);

        $geminiPayload = [
            'stimuli' => [
                [
                    'id_tag'           => 'STIM_1',
                    'subject_name'     => 'Lectura Crítica',
                    'title'            => 'Otro título distinto',
                    'content_text'     => 'Otro texto distinto',
                    'has_visual_media' => true,
                    'visual_type'      => 'infographic',
                    'source_page'      => 1,
                ],
            ],
            'questions' => [
                [
                    'order'                 => 1,
                    'subject_name'          => 'Lectura Crítica',
                    'stimulus_tag'          => 'STIM_1',
                    'statement'             => 'Intento de sobrescribir la pregunta en review #1.',
                    'source_page'           => 1,
                    'has_verifiable_answer' => true,
                    'correct_option_letter' => 'A',
                    'options'               => ['A' => 'X1', 'B' => 'X2', 'C' => 'X3', 'D' => 'X4'],
                ],
            ],
        ];

        Http::fake([
            '*generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($geminiPayload, JSON_UNESCAPED_UNICODE)]]]],
                ],
            ], 200),
        ]);

        app(GeminiQuestionExtractionService::class)->extractQuestionsFromDocument($doc->fresh());

        // Verificar que los bytes físicos del activo en review permanecen 100% idénticos y su SHA-256 coincide con BD
        $physicalBytesAfter = $this->getFromMemoryStorage($mediaBucket, $reviewStimAssetPath);
        $this->assertSame($originalReviewBytes, $physicalBytesAfter);

        $mediaReviewAfter = MediaAsset::find($reviewMediaId);
        $this->assertNotNull($mediaReviewAfter);
        $this->assertSame($originalReviewSha256, $mediaReviewAfter->sha256);
        $this->assertSame($mediaReviewAfter->sha256, hash('sha256', $physicalBytesAfter));
    }

    /**
     * PRUEBA 9:
     * Una reextracción de borradores (draft) continúa funcionando limpiamente y sin duplicados.
     */
    public function test_regenerable_draft_questions_are_cleaned_and_regenerated_without_duplicates(): void
    {
        $mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');
        $docId = (string) Str::uuid();
        $doc = SourceDocument::create([
            'id'             => $docId,
            'title'          => 'Cuadernillo Mixto P0-5 Draft Regeneration',
            'storage_path'   => "documents/2026/{$docId}.pdf",
            'exam_type'      => 'Saber 11',
            'status'         => 'extracted',
            'has_answer_key' => true,
        ]);

        $protectedStimId = (string) Str::uuid();
        $protectedStimPath = "extracted/{$docId}/page-01_obj1.png";
        $protBytes = $this->makeValidPngBytes('prot-s1');
        $protSha256 = hash('sha256', $protBytes);
        $this->putInMemoryStorage($mediaBucket, $protectedStimPath, $protBytes);

        Stimulus::create([
            'id'                 => $protectedStimId,
            'source_document_id' => $docId,
            'subject_id'         => 1,
            'title'              => 'Texto compartido protegido S1',
            'content_text'       => 'Contenido del estímulo compartido S1 que debe preservarse.',
            'media_type'         => 'image',
            'media_path'         => $protectedStimPath,
            'source_page'        => 1,
        ]);

        $protectedMediaId = (string) Str::uuid();
        MediaAsset::create([
            'id'                 => $protectedMediaId,
            'source_document_id' => $docId,
            'stimulus_id'        => $protectedStimId,
            'storage_path'       => $protectedStimPath,
            'page_number'        => 1,
            'width'              => 500,
            'height'             => 300,
            'mime_type'          => 'image/png',
            'sha256'             => $protSha256,
            'association_status' => 'confirmed',
        ]);

        $q1Id = (string) Str::uuid();
        Question::create([
            'id'                 => $q1Id,
            'root_question_id'   => $q1Id,
            'version'            => 1,
            'is_current_version' => true,
            'subject_id'         => 1,
            'stimulus_id'        => $protectedStimId,
            'source_document_id' => $docId,
            'order_in_group'     => 1,
            'statement'          => 'Pregunta #1 en review asociada al estímulo compartido S1.',
            'source_page'        => 1,
            'difficulty'         => 'medium',
            'key_status'         => 'verified',
            'status'             => 'review',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $q1Id,
                'option_letter' => $letter,
                'option_text'   => "Opción {$letter} Q1",
                'is_correct'    => $letter === 'A',
            ]);
        }

        $draftStimId = (string) Str::uuid();
        $draftAssetPath = "extracted/{$docId}/page-02_obj1.png";
        $draftBytes = $this->makeValidPngBytes('draft-s2');
        $draftSha256 = hash('sha256', $draftBytes);
        $this->putInMemoryStorage($mediaBucket, $draftAssetPath, $draftBytes);

        Stimulus::create([
            'id'                 => $draftStimId,
            'source_document_id' => $docId,
            'subject_id'         => 1,
            'title'              => 'Estímulo borrador antiguo S2 que debe limpiarse',
            'content_text'       => 'Texto de borrador antiguo.',
            'media_type'         => 'image',
            'media_path'         => $draftAssetPath,
            'source_page'        => 2,
        ]);

        $oldDraftMediaId = (string) Str::uuid();
        MediaAsset::create([
            'id'                 => $oldDraftMediaId,
            'source_document_id' => $docId,
            'stimulus_id'        => $draftStimId,
            'storage_path'       => $draftAssetPath,
            'page_number'        => 2,
            'width'              => 450,
            'height'             => 350,
            'mime_type'          => 'image/png',
            'sha256'             => $draftSha256,
            'association_status' => 'confirmed',
        ]);

        $oldDraftQ2Id = (string) Str::uuid();
        Question::create([
            'id'                 => $oldDraftQ2Id,
            'root_question_id'   => $oldDraftQ2Id,
            'version'            => 1,
            'is_current_version' => true,
            'subject_id'         => 1,
            'stimulus_id'        => $draftStimId,
            'source_document_id' => $docId,
            'order_in_group'     => 2,
            'statement'          => 'Pregunta #2 en draft defectuosa que debe ser regenerada limpiamente.',
            'source_page'        => 2,
            'difficulty'         => 'easy',
            'key_status'         => 'unverified',
            'status'             => 'draft',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $oldDraftQ2Id,
                'option_letter' => $letter,
                'option_text'   => "Opción vieja {$letter} Q2",
                'is_correct'    => null,
            ]);
        }

        $this->bindStorageAndImageMocks($doc, [
            ['status' => 'ok', 'page' => 1, 'object' => 1, 'width' => 500, 'height' => 300, 'mime' => 'image/png', 'ext' => 'png', 'bytes' => $protBytes],
            ['status' => 'ok', 'page' => 2, 'object' => 1, 'width' => 450, 'height' => 350, 'mime' => 'image/png', 'ext' => 'png', 'bytes' => $draftBytes],
        ]);

        $geminiPayload = [
            'stimuli' => [
                [
                    'id_tag'           => 'STIM_1',
                    'subject_name'     => 'Lectura Crítica',
                    'title'            => 'Texto compartido protegido S1',
                    'content_text'     => 'Contenido del estímulo compartido S1 que debe preservarse.',
                    'has_visual_media' => true,
                    'visual_type'      => 'image',
                    'source_page'      => 1,
                ],
                [
                    'id_tag'           => 'STIM_2',
                    'subject_name'     => 'Lectura Crítica',
                    'title'            => 'Nuevo estímulo S2 regenerado limpiamente',
                    'content_text'     => 'Texto regenerado para la pregunta 2.',
                    'has_visual_media' => true,
                    'visual_type'      => 'image',
                    'source_page'      => 2,
                ],
            ],
            'questions' => [
                [
                    'order'                 => 1,
                    'subject_name'          => 'Lectura Crítica',
                    'stimulus_tag'          => 'STIM_1',
                    'statement'             => 'Pregunta #1 en review asociada al estímulo compartido S1.',
                    'source_page'           => 1,
                    'has_verifiable_answer' => true,
                    'correct_option_letter' => 'A',
                    'options'               => ['A' => 'Opción A Q1', 'B' => 'Opción B Q1', 'C' => 'Opción C Q1', 'D' => 'Opción D Q1'],
                ],
                [
                    'order'                 => 2,
                    'subject_name'          => 'Lectura Crítica',
                    'stimulus_tag'          => 'STIM_2',
                    'statement'             => 'Pregunta #2 regenerada con enunciado completo y opciones verificadas.',
                    'source_page'           => 2,
                    'has_verifiable_answer' => true,
                    'correct_option_letter' => 'C',
                    'options'               => ['A' => 'Nueva A2', 'B' => 'Nueva B2', 'C' => 'Nueva C2', 'D' => 'Nueva D2'],
                ],
                [
                    'order'                 => 3,
                    'subject_name'          => 'Lectura Crítica',
                    'stimulus_tag'          => 'STIM_1',
                    'statement'             => 'Pregunta #3 nueva en borrador que comparte el estímulo protegido S1.',
                    'source_page'           => 1,
                    'has_verifiable_answer' => true,
                    'correct_option_letter' => 'B',
                    'options'               => ['A' => 'Opción A3', 'B' => 'Opción B3', 'C' => 'Opción C3', 'D' => 'Opción D3'],
                ],
            ],
        ];

        Http::fake([
            '*generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($geminiPayload, JSON_UNESCAPED_UNICODE)]]]],
                ],
            ], 200),
        ]);

        app(GeminiQuestionExtractionService::class)->extractQuestionsFromDocument($doc->fresh());

        $this->assertNull(Question::find($oldDraftQ2Id));
        $this->assertNull(Stimulus::find($draftStimId));
        $this->assertNull(MediaAsset::find($oldDraftMediaId));

        $newQ2 = Question::where('source_document_id', $docId)->where('order_in_group', 2)->first();
        $this->assertNotNull($newQ2);
        $this->assertSame('draft', $newQ2->status);
        $this->assertSame('Pregunta #2 regenerada con enunciado completo y opciones verificadas.', $newQ2->statement);

        $newQ3 = Question::where('source_document_id', $docId)->where('order_in_group', 3)->first();
        $this->assertNotNull($newQ3);
        $this->assertSame($protectedStimId, $newQ3->stimulus_id);

        $this->assertSame($protBytes, $this->getFromMemoryStorage($mediaBucket, $protectedStimPath));
        $this->assertSame(3, Question::where('source_document_id', $docId)->count());
        $this->assertSame(2, Stimulus::where('source_document_id', $docId)->count());
        $this->assertSame(2, MediaAsset::where('source_document_id', $docId)->count());
    }

    /**
     * PRUEBA 6 y 7:
     * Un error HTTP 500 de Gemini o un JSON inválido de Gemini NO modifica Storage (0 escrituras en Storage y
     * los bytes físicos protegidos permanecen intactos).
     */
    public function test_gemini_http_500_or_invalid_json_never_modifies_storage(): void
    {
        $mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');
        $docId = (string) Str::uuid();
        $doc = SourceDocument::create([
            'id'             => $docId,
            'title'          => 'Cuadernillo Fallo Gemini P0-5',
            'storage_path'   => "documents/2026/{$docId}.pdf",
            'exam_type'      => 'Saber 11',
            'status'         => 'extracted',
            'has_answer_key' => true,
        ]);

        $protectedPath = "extracted/{$docId}/page-01_obj1.png";
        $originalBytes = $this->makeValidPngBytes('PROTECTED_ORIGINAL_BEFORE_500');
        $originalSha256 = hash('sha256', $originalBytes);
        $this->putInMemoryStorage($mediaBucket, $protectedPath, $originalBytes);

        $qId = (string) Str::uuid();
        Question::create([
            'id'                  => $qId,
            'root_question_id'    => $qId,
            'version'             => 1,
            'is_current_version'  => true,
            'subject_id'          => 1,
            'source_document_id'  => $docId,
            'order_in_group'      => 1,
            'statement'           => 'Pregunta publicada protegida ante fallo de Gemini.',
            'question_image_path' => $protectedPath,
            'source_page'         => 1,
            'status'              => 'published',
        ]);

        $assetId = (string) Str::uuid();
        MediaAsset::create([
            'id'                 => $assetId,
            'source_document_id' => $docId,
            'question_id'        => $qId,
            'storage_path'       => $protectedPath,
            'page_number'        => 1,
            'width'              => 500,
            'height'             => 300,
            'mime_type'          => 'image/png',
            'sha256'             => $originalSha256,
            'association_status' => 'confirmed',
        ]);

        $newCandidateBytes = $this->makeValidPngBytes('NEW_BYTES_DURING_FAILED_GEMINI_CALL');
        $this->bindStorageAndImageMocks($doc, [
            ['status' => 'ok', 'page' => 1, 'object' => 1, 'width' => 500, 'height' => 300, 'mime' => 'image/png', 'ext' => 'png', 'bytes' => $newCandidateBytes],
            ['status' => 'ok', 'page' => 2, 'object' => 1, 'width' => 500, 'height' => 300, 'mime' => 'image/png', 'ext' => 'png', 'bytes' => $newCandidateBytes],
        ]);

        // Caso A: Error HTTP 500 de Gemini
        Http::fake([
            '*generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Internal Server Error']], 500),
        ]);

        $http500Failed = false;
        try {
            app(GeminiQuestionExtractionService::class)->extractQuestionsFromDocument($doc->fresh());
        } catch (RuntimeException $e) {
            $http500Failed = true;
        }

        $this->assertTrue($http500Failed);
        $this->assertEmpty($this->uploadHistory, 'Un error HTTP 500 de Gemini no debe ejecutar ningún upload en Storage.');
        $this->assertSame($originalBytes, $this->getFromMemoryStorage($mediaBucket, $protectedPath));
        $this->assertSame($originalSha256, hash('sha256', (string) $this->getFromMemoryStorage($mediaBucket, $protectedPath)));

        // Caso B: JSON inválido devuelto por Gemini
        Http::fake([
            '*generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'ESTO NO ES UN JSON VALIDO {{{']]]],
                ],
            ], 200),
        ]);

        $invalidJsonFailed = false;
        try {
            app(GeminiQuestionExtractionService::class)->extractQuestionsFromDocument($doc->fresh());
        } catch (RuntimeException $e) {
            $invalidJsonFailed = true;
        }

        $this->assertTrue($invalidJsonFailed);
        $this->assertEmpty($this->uploadHistory, 'Un JSON inválido de Gemini no debe ejecutar ningún upload en Storage.');
        $this->assertSame($originalBytes, $this->getFromMemoryStorage($mediaBucket, $protectedPath));
    }

    /**
     * PRUEBA 8:
     * Un rollback SQL no deja referencias rotas ni elimina o altera archivos anteriores (ni protegidos ni borradores previos),
     * y limpia exclusivamente los objetos nuevos creados en esa operación fallida.
     */
    public function test_sql_rollback_preserves_existing_files_and_cleans_up_only_newly_created_storage_objects(): void
    {
        $mediaBucket = config('filesystems.disks.question_bank_media.bucket', 'icfes-media');
        $docId = (string) Str::uuid();
        $doc = SourceDocument::create([
            'id'             => $docId,
            'title'          => 'Cuadernillo Rollback Transaccional P0-5',
            'storage_path'   => "documents/2026/{$docId}.pdf",
            'exam_type'      => 'Saber 11',
            'status'         => 'extracted',
            'has_answer_key' => true,
        ]);

        // 1. Activo físico y registros de pregunta publicada (página 1)
        $pubPath = "extracted/{$docId}/page-01_obj1.png";
        $pubBytes = $this->makeValidPngBytes('PUBLISHED_FILE_V1');
        $pubSha256 = hash('sha256', $pubBytes);
        $this->putInMemoryStorage($mediaBucket, $pubPath, $pubBytes);

        $pubStimId = (string) Str::uuid();
        Stimulus::create([
            'id'                 => $pubStimId,
            'source_document_id' => $docId,
            'subject_id'         => 2,
            'title'              => 'Estímulo publicado',
            'content_text'       => 'Contenido publicado',
            'media_type'         => 'image',
            'media_path'         => $pubPath,
            'source_page'        => 1,
        ]);

        $pubMediaId = (string) Str::uuid();
        MediaAsset::create([
            'id'                 => $pubMediaId,
            'source_document_id' => $docId,
            'stimulus_id'        => $pubStimId,
            'storage_path'       => $pubPath,
            'page_number'        => 1,
            'width'              => 500,
            'height'             => 300,
            'mime_type'          => 'image/png',
            'sha256'             => $pubSha256,
            'association_status' => 'confirmed',
        ]);

        $pubQId = (string) Str::uuid();
        Question::create([
            'id'                 => $pubQId,
            'root_question_id'   => $pubQId,
            'version'            => 1,
            'is_current_version' => true,
            'subject_id'         => 2,
            'stimulus_id'        => $pubStimId,
            'source_document_id' => $docId,
            'order_in_group'     => 1,
            'statement'          => 'Pregunta publicada #1.',
            'difficulty'         => 'medium',
            'key_status'         => 'verified',
            'status'             => 'published',
        ]);

        // 2. Activo físico y registros de borrador previo al fallo (página 2)
        $draftPath = "extracted/{$docId}/page-02_obj1.png";
        $draftBytes = $this->makeValidPngBytes('EXISTING_DRAFT_FILE_V1');
        $draftSha256 = hash('sha256', $draftBytes);
        $this->putInMemoryStorage($mediaBucket, $draftPath, $draftBytes);

        $draftStimId = (string) Str::uuid();
        Stimulus::create([
            'id'                 => $draftStimId,
            'source_document_id' => $docId,
            'subject_id'         => 2,
            'title'              => 'Estímulo draft previo al fallo',
            'content_text'       => 'No debe desaparecer si la transacción falla',
            'media_type'         => 'image',
            'media_path'         => $draftPath,
            'source_page'        => 2,
        ]);

        $draftMediaId = (string) Str::uuid();
        MediaAsset::create([
            'id'                 => $draftMediaId,
            'source_document_id' => $docId,
            'stimulus_id'        => $draftStimId,
            'storage_path'       => $draftPath,
            'page_number'        => 2,
            'width'              => 450,
            'height'             => 300,
            'mime_type'          => 'image/png',
            'sha256'             => $draftSha256,
            'association_status' => 'confirmed',
        ]);

        $draftQId = (string) Str::uuid();
        Question::create([
            'id'                 => $draftQId,
            'root_question_id'   => $draftQId,
            'version'            => 1,
            'is_current_version' => true,
            'subject_id'         => 2,
            'stimulus_id'        => $draftStimId,
            'source_document_id' => $docId,
            'order_in_group'     => 2,
            'statement'          => 'Pregunta draft previa al fallo.',
            'difficulty'         => 'easy',
            'key_status'         => 'unverified',
            'status'             => 'draft',
        ]);

        // Durante la reextracción fallida:
        // - Página 1 trae los mismos bytes protegidos
        // - Página 2 trae bytes distintos ($newPage2Bytes)
        // - Página 3 trae una imagen nueva ($newPage3Bytes)
        $newPage2Bytes = $this->makeValidPngBytes('NEW_UNCOMMITTED_PAGE2_BYTES');
        $newPage3Bytes = $this->makeValidPngBytes('NEW_UNCOMMITTED_PAGE3_BYTES');
        $newPage3Path = "extracted/{$docId}/page-03_obj1.png";

        $this->bindStorageAndImageMocks($doc, [
            ['status' => 'ok', 'page' => 1, 'object' => 1, 'width' => 500, 'height' => 300, 'mime' => 'image/png', 'ext' => 'png', 'bytes' => $pubBytes],
            ['status' => 'ok', 'page' => 2, 'object' => 1, 'width' => 450, 'height' => 300, 'mime' => 'image/png', 'ext' => 'png', 'bytes' => $newPage2Bytes],
            ['status' => 'ok', 'page' => 3, 'object' => 1, 'width' => 400, 'height' => 300, 'mime' => 'image/png', 'ext' => 'png', 'bytes' => $newPage3Bytes],
        ]);

        Http::fake([
            '*generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'stimuli'   => [],
                            'questions' => [
                                [
                                    'order'        => 2,
                                    'subject_name' => 'TRIGGER_TRANSACTION_FAILURE',
                                    'statement'    => 'Pregunta que provoca fallo en la transacción SQL.',
                                    'options'      => ['A' => '1', 'B' => '2', 'C' => '3', 'D' => '4'],
                                ],
                            ],
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        // Servicio que lanza excepción a mitad de persistExtractedData() (después de los deletes en la transacción)
        $failingService = new class(
            $this->app->make(SupabaseStorageService::class),
            $this->app->make(PdfImageExtractor::class)
        ) extends GeminiQuestionExtractionService {
            protected function resolveSubjectId(string $subjectName, ?SourceDocument $document = null): int
            {
                if ($subjectName === 'TRIGGER_TRANSACTION_FAILURE') {
                    throw new RuntimeException('Fallo simulado a mitad de persistExtractedData luego del borrado.');
                }
                return parent::resolveSubjectId($subjectName, $document);
            }
        };

        $exceptionThrown = false;
        try {
            $failingService->extractQuestionsFromDocument($doc->fresh());
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'Se esperaba que la excepción simulada fuera lanzada.');

        // 1. Rollback SQL completo: tanto los registros publicados como los de borrador previos siguen intactos
        $this->assertNotNull(Question::find($pubQId));
        $this->assertNotNull(Stimulus::find($pubStimId));
        $this->assertNotNull(MediaAsset::find($pubMediaId));
        $this->assertNotNull(Question::find($draftQId));
        $this->assertNotNull(Stimulus::find($draftStimId));
        $this->assertNotNull(MediaAsset::find($draftMediaId));

        // 2. Archivos físicos preexistentes (publicado y borrador anterior) están intactos con el mismo SHA-256
        $this->assertSame($pubBytes, $this->getFromMemoryStorage($mediaBucket, $pubPath));
        $this->assertSame($pubSha256, hash('sha256', (string) $this->getFromMemoryStorage($mediaBucket, $pubPath)));
        $this->assertSame($draftBytes, $this->getFromMemoryStorage($mediaBucket, $draftPath));
        $this->assertSame($draftSha256, hash('sha256', (string) $this->getFromMemoryStorage($mediaBucket, $draftPath)));

        // 3. Los objetos nuevos creados durante la operación fallida fueron eliminados en el rollback compensatorio
        $this->assertNull($this->getFromMemoryStorage($mediaBucket, $newPage3Path));
        $this->assertContains("{$mediaBucket}:{$newPage3Path}", $this->deleteHistory);
        $this->assertNotContains("{$mediaBucket}:{$pubPath}", $this->deleteHistory);
        $this->assertNotContains("{$mediaBucket}:{$draftPath}", $this->deleteHistory);
    }
}
