<?php

namespace Tests\Feature;

use App\Jobs\ProcessIqpeDocumentJob;
use App\Models\ExamAttempt;
use App\Models\IqpeAdminDecision;
use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\IqpeQualityAudit;
use App\Models\IqpeQuestionResolution;
use App\Models\IqpeVerification;
use App\Models\MediaAsset;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Models\Stimulus;
use App\Models\StudentAnswer;
use App\Services\GeminiQuestionExtractionService;
use App\Services\GeminiQuestionSolverService;
use App\Services\IqpeQualityAuditService;
use App\Services\IqpeVerificationService;
use App\Services\PdfImageExtractor;
use App\Services\StudentTokenService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class IqpeBloque7EndToEndMvpTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    protected string $adminToken;
    protected string $studentToken;
    protected string $studentId = 'student-mvp-e2e-001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();

        config([
            'services.gemini.api_key'                        => 'test-gemini-key-b7',
            'services.gemini.model'                          => 'gemini-3-flash-preview',
            'services.gemini.fallback_model'                 => 'gemini-3.1-flash-lite',
            'services.gemini.solver_model'                   => 'gemini-3-flash-preview',
            'services.gemini.solver_fallback_model'          => 'gemini-3.1-flash-lite',
            'services.gemini.solver_concurrency'             => 1,
            'services.gemini.solver_cooldown_ms'             => 0,
            'services.gemini.solver_backoff_seconds'         => [0, 0, 0],
            'services.gemini.solver_max_retry_wait_seconds'  => 0,
            'services.gemini.extraction_retry_delay_seconds' => 0,
        ]);

        // 1. Login administrativo real para obtener token JWT
        $loginRes = $this->postJson('/api/v1/admin/auth/login', [
            'email'    => config('services.athena.admin_email', 'admin@athena.edu.co'),
            'password' => config('services.athena.admin_password', 'admin123456'),
        ]);
        $loginRes->assertStatus(200);
        $this->adminToken = (string) $loginRes->json('token');

        // Token de estudiante válido para pruebas de endpoints estudiantiles
        $this->studentToken = StudentTokenService::generateToken([
            'id'    => $this->studentId,
            'email' => 'estudiante.e2e@athena.edu.co',
            'name'  => 'Estudiante E2E MVP',
            'role'  => 'student',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Genera un PNG 1x1 binario válido para pruebas de recursos visuales.
     */
    protected function makeValidPngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
    }

    /**
     * Genera bytes de un PDF sintético válido con firma %PDF-1.4.
     */
    protected function makeValidPdfBytes(string $extraComment = 'ATHENA-IQPE-B7-E2E'): string
    {
        return "%PDF-1.4\n% {$extraComment}\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 2 >>\nendobj\n%%EOF";
    }

    /**
     * SECCIÓN 2 & 5: PRUEBA COMPLETA DEL FLUJO E2E (17 PASOS) + INTEGRIDAD Y SEGURIDAD
     *
     * Verifica desde el login administrativo y la subida del PDF hasta la disponibilidad
     * y resolución en prácticas y simulacros estudiantiles, preservando las 49 preguntas
     * publicadas de Lectura Crítica y 10 respuestas estudiantiles históricas.
     */
    public function test_complete_17_step_e2e_flow_from_pdf_upload_to_student_practice_and_mock_exam(): void
    {
        // =====================================================================
        // PRE-CONDICIÓN: Sembrar base histórica protegida (49 publicadas + 10 respuestas + 9 media_assets)
        // =====================================================================
        $protectedDocId = 'f59ac90b-509a-493d-a75b-ae85c4c0cadf';
        SourceDocument::create([
            'id'             => $protectedDocId,
            'title'          => 'cuadernillo saber 11 lectura critica',
            'storage_path'   => 'documents/2026/lectura_critica.pdf',
            'exam_type'      => 'Saber 11',
            'status'         => 'extracted',
            'has_answer_key' => true,
        ]);

        $protectedQIds = [];
        for ($i = 1; $i <= 49; $i++) {
            $pQId = (string) Str::uuid();
            $protectedQIds[] = $pQId;
            Question::create([
                'id'                 => $pQId,
                'root_question_id'   => $pQId,
                'version'            => 1,
                'is_current_version' => true,
                'subject_id'         => 1,
                'source_document_id' => $protectedDocId,
                'order_in_group'     => $i,
                'statement'          => "Pregunta oficial protegida #{$i} de Lectura Crítica sobre análisis semántico literario #{$i}.",
                'difficulty'         => 'medium',
                'key_status'         => 'verified',
                'status'             => 'published',
                'published_at'       => now()->subDays(10),
            ]);

            foreach (['A', 'B', 'C', 'D'] as $letter) {
                QuestionOption::create([
                    'id'            => (string) Str::uuid(),
                    'question_id'   => $pQId,
                    'option_letter' => $letter,
                    'option_text'   => "Opción {$letter} protegida #{$i}",
                    'is_correct'    => $letter === 'A',
                ]);
            }
        }

        for ($i = 0; $i < 10; $i++) {
            $qId = $protectedQIds[$i];
            $optA = QuestionOption::where('question_id', $qId)->where('option_letter', 'A')->first();
            DB::connection('question_bank')->table('student_answers')->insert([
                'id'                     => (string) Str::uuid(),
                'student_id'             => 'student-historic-' . ($i + 1),
                'question_id'            => $qId,
                'selected_option_id'     => $optA->id,
                'selected_option_letter' => 'A',
                'is_correct'             => true,
                'practice_mode'          => 'practice',
                'time_spent_seconds'     => 35,
                'answered_at'            => now()->subDays(5),
                'created_at'             => now()->subDays(5),
                'updated_at'             => now()->subDays(5),
            ]);
        }

        for ($i = 1; $i <= 9; $i++) {
            MediaAsset::create([
                'id'                 => (string) Str::uuid(),
                'source_document_id' => $protectedDocId,
                'storage_path'       => "extracted/{$protectedDocId}/page-0{$i}_obj1.png",
                'page_number'        => $i,
                'width'              => 400,
                'height'             => 300,
                'mime_type'          => 'image/png',
                'sha256'             => hash('sha256', "protected-asset-{$i}"),
                'association_status' => 'confirmed',
            ]);
        }

        // Sembrar 1 pregunta base de Matemáticas para que Q4 sea detectada como duplicado exacto
        $existingMathDupId = (string) Str::uuid();
        Question::create([
            'id'                 => $existingMathDupId,
            'root_question_id'   => $existingMathDupId,
            'version'            => 1,
            'is_current_version' => true,
            'subject_id'         => 2,
            'source_document_id' => $protectedDocId,
            'order_in_group'     => 99,
            'statement'          => 'En un triángulo rectángulo los catetos miden 6 cm y 8 cm. ¿Cuál es la longitud de la hipotenusa?',
            'difficulty'         => 'easy',
            'key_status'         => 'verified',
            'status'             => 'review',
        ]);
        foreach (['A' => '10 cm', 'B' => '12 cm', 'C' => '14 cm', 'D' => '16 cm'] as $l => $t) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $existingMathDupId,
                'option_letter' => $l,
                'option_text'   => $t,
                'is_correct'    => $l === 'A',
            ]);
        }

        // =====================================================================
        // Configurar Mocks de SupabaseStorageService y PdfImageExtractor
        // =====================================================================
        $pngBytes = $this->makeValidPngBytes();
        $pdfBytes = $this->makeValidPdfBytes();
        $inMemoryStorage = [];

        $storageMock = Mockery::mock(SupabaseStorageService::class);
        $storageMock->shouldReceive('upload')
            ->andReturnUsing(function (string $bucket, string $path, string $contents) use (&$inMemoryStorage) {
                $inMemoryStorage["{$bucket}:{$path}"] = $contents;
                return true;
            });
        $storageMock->shouldReceive('exists')
            ->andReturnUsing(function (string $bucket, string $path) use (&$inMemoryStorage) {
                return isset($inMemoryStorage["{$bucket}:{$path}"]);
            });
        $storageMock->shouldReceive('get')
            ->andReturnUsing(function (string $bucket, string $path) use (&$inMemoryStorage) {
                return $inMemoryStorage["{$bucket}:{$path}"] ?? '';
            });
        $this->app->instance(SupabaseStorageService::class, $storageMock);

        $imageExtractorMock = Mockery::mock(PdfImageExtractor::class);
        $imageExtractorMock->shouldReceive('extract')
            ->andReturn([
                'pages'  => 4,
                'images' => [
                    [
                        'status' => 'ok',
                        'page'   => 1,
                        'object' => 1,
                        'width'  => 640,
                        'height' => 480,
                        'mime'   => 'image/png',
                        'ext'    => 'png',
                        'bytes'  => $pngBytes,
                    ],
                ],
            ]);
        $this->app->instance(PdfImageExtractor::class, $imageExtractorMock);

        // =====================================================================
        // PASO 1: Verificar sesión de login administrativo (/api/v1/admin/auth/me)
        // =====================================================================
        $meRes = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson('/api/v1/admin/auth/me');
        $meRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('admin.email', strtolower(config('services.athena.admin_email', 'admin@athena.edu.co')));

        // =====================================================================
        // PASO 2 & 3: Carga de PDF de prueba y creación del trabajo IQPE
        // =====================================================================
        Queue::fake();

        $fakePdf = UploadedFile::fake()->createWithContent('cuadernillo_matematicas_e2e_mvp.pdf', $pdfBytes);

        $uploadRes = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson('/api/v1/admin/documents', [
                'file'           => $fakePdf,
                'title'          => 'Cuadernillo Matemáticas Saber 11 — Prueba E2E Bloque 7',
                'year'           => 2026,
                'exam_type'      => 'Saber 11',
                'total_pages'    => 4,
                'has_answer_key' => true,
            ]);

        $uploadRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'queued');

        $docId = $uploadRes->json('data.document_id');
        $jobId = $uploadRes->json('data.job_id');
        $this->assertNotEmpty($docId);
        $this->assertNotEmpty($jobId);

        Queue::assertPushed(ProcessIqpeDocumentJob::class, function (ProcessIqpeDocumentJob $job) use ($docId, $jobId) {
            return $job->documentId === $docId && $job->jobId === $jobId;
        });

        // =====================================================================
        // PASO 4 al 11: Ejecución asíncrona del Job IQPE (Extracción, R1, R2, Verificación, R3, Auditoría y Duplicados)
        // =====================================================================
        $extractedPayload = [
            'stimuli' => [
                [
                    'id_tag'               => 'STIM_MATH_1',
                    'subject_name'         => 'Matemáticas',
                    'title'                => 'Modelación de costos lineales de producción',
                    'instructions'         => 'Responda las preguntas 1 y 2 de acuerdo con la siguiente información y gráfica.',
                    'content_text'         => 'Una planta industrial modela su costo diario C(x) = 15x + 120 miles de pesos para producir x unidades.',
                    'media_type'           => 'image',
                    'has_visual_media'     => true,
                    'source_page'          => 1,
                    'visual_transcription' => 'Recta creciente con intercepto en (0, 120) y punto (10, 270).',
                    'visual_description'   => 'Plano cartesiano que relaciona unidades producidas x con costo C(x).',
                ],
            ],
            'questions' => [
                // Q1: Escenario A (con clave oficial B), R1=B, R2=B -> verified_candidate, ready_for_approval
                [
                    'order'                  => 1,
                    'subject_name'           => 'Matemáticas',
                    'stimulus_tag'           => 'STIM_MATH_1',
                    'statement'              => 'Si la planta produce exactamente 20 unidades en un día, ¿cuál es el costo total diario de producción según el modelo lineal?',
                    'source_page'            => 1,
                    'difficulty'             => 'easy',
                    'has_verifiable_answer'  => true,
                    'correct_option_letter'  => 'B',
                    'explanation'            => 'Al evaluar C(20) = 15(20) + 120 = 300 + 120 = 420 miles de pesos.',
                    'options'                => [
                        ['letter' => 'A', 'text' => '300 miles de pesos'],
                        ['letter' => 'B', 'text' => '420 miles de pesos'],
                        ['letter' => 'C', 'text' => '450 miles de pesos'],
                        ['letter' => 'D', 'text' => '540 miles de pesos'],
                    ],
                ],
                // Q2: Escenario B (sin clave oficial), R1=C, R2=C, R3=C -> verified_candidate_no_official_key, ready_for_approval
                [
                    'order'                  => 2,
                    'subject_name'           => 'Matemáticas',
                    'stimulus_tag'           => 'STIM_MATH_1',
                    'statement'              => '¿Cuántas unidades se produjeron en un día cuyo costo total fue de 570 miles de pesos?',
                    'source_page'            => 2,
                    'difficulty'             => 'medium',
                    'has_verifiable_answer'  => false,
                    'correct_option_letter'  => null,
                    'explanation'            => 'Al despejar 15x + 120 = 570 se obtiene 15x = 450, por lo tanto x = 30 unidades.',
                    'options'                => [
                        ['letter' => 'A', 'text' => '20 unidades'],
                        ['letter' => 'B', 'text' => '25 unidades'],
                        ['letter' => 'C', 'text' => '30 unidades'],
                        ['letter' => 'D', 'text' => '38 unidades'],
                    ],
                ],
                // Q3: Escenario A con discrepancia (clave oficial A, pero R1=D y R2=D) -> requires_admin_review
                [
                    'order'                  => 3,
                    'subject_name'           => 'Matemáticas',
                    'statement'              => 'La probabilidad de extraer una balota roja de una urna con 3 rojas y 7 azules es:',
                    'source_page'            => 3,
                    'difficulty'             => 'easy',
                    'has_verifiable_answer'  => true,
                    'correct_option_letter'  => 'A',
                    'explanation'            => 'Hay 3 casos favorables de 10 posibles, es decir 3/10.',
                    'options'                => [
                        ['letter' => 'A', 'text' => '7/10'],
                        ['letter' => 'B', 'text' => '3/7'],
                        ['letter' => 'C', 'text' => '1/3'],
                        ['letter' => 'D', 'text' => '3/10'],
                    ],
                ],
                // Q4: Duplicado exacto de $existingMathDupId -> duplicate_risk_flag = true
                [
                    'order'                  => 4,
                    'subject_name'           => 'Matemáticas',
                    'statement'              => 'En un triángulo rectángulo los catetos miden 6 cm y 8 cm. ¿Cuál es la longitud de la hipotenusa?',
                    'source_page'            => 4,
                    'difficulty'             => 'easy',
                    'has_verifiable_answer'  => true,
                    'correct_option_letter'  => 'A',
                    'explanation'            => 'Por el teorema de Pitágoras sqrt(36 + 64) = sqrt(100) = 10 cm.',
                    'options'                => [
                        ['letter' => 'A', 'text' => '10 cm'],
                        ['letter' => 'B', 'text' => '12 cm'],
                        ['letter' => 'C', 'text' => '14 cm'],
                        ['letter' => 'D', 'text' => '16 cm'],
                    ],
                ],
            ],
        ];

        $capturedSolverPayloads = [];

        Http::fake(function ($request) use ($extractedPayload, &$capturedSolverPayloads) {
            $body = $request->data();
            $parts = $body['contents'][0]['parts'] ?? [];
            $firstPart = $parts[0] ?? [];

            // Llamada 1: Extracción multimodal (primer part tiene inlineData application/pdf)
            if (($firstPart['inlineData']['mimeType'] ?? '') === 'application/pdf') {
                return Http::response([
                    'candidates' => [[
                        'content' => [
                            'parts' => [
                                ['thought' => true, 'text' => 'Analizando cuadernillo PDF...'],
                                ['text' => json_encode($extractedPayload, JSON_UNESCAPED_UNICODE)],
                            ],
                        ],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => [
                        'promptTokenCount'     => 1850,
                        'candidatesTokenCount' => 920,
                        'totalTokenCount'      => 2770,
                    ],
                ], 200);
            }

            // Llamadas siguientes: Solver R1, R2 y R3
            $promptText = (string) ($firstPart['text'] ?? '');
            $capturedSolverPayloads[] = $promptText;

            $letter = 'A';
            $explanation = 'Resolución paso a paso verificada.';
            if (str_contains($promptText, 'exactamente 20 unidades')) {
                $letter = 'B';
                $explanation = 'Sustituyendo x=20 en C(x)=15x+120 se obtiene 15(20)+120 = 420 miles de pesos.';
            } elseif (str_contains($promptText, '570 miles de pesos')) {
                $letter = 'C';
                $explanation = 'Resolviendo 15x+120=570 -> 15x=450 -> x=30 unidades.';
            } elseif (str_contains($promptText, 'balota roja')) {
                $letter = 'D';
                $explanation = '3 balotas rojas sobre un total de 10 balotas equivale a 3/10.';
            } elseif (str_contains($promptText, 'catetos miden 6 cm y 8 cm')) {
                $letter = 'A';
                $explanation = 'Aplicando Pitágoras: sqrt(6^2 + 8^2) = 10 cm.';
            }

            return Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'status'                => 'success',
                                'solved_option_letter'  => $letter,
                                'solution_explanation'  => $explanation,
                                'visual_interpretation' => 'Gráfica lineal verificada correctamente.',
                                'confidence_score'      => 0.96,
                            ], JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => [
                    'promptTokenCount'     => 410,
                    'candidatesTokenCount' => 115,
                    'totalTokenCount'      => 525,
                ],
            ], 200);
        });

        // Ejecutar el Job asíncrono
        $jobInstance = new ProcessIqpeDocumentJob($docId, $jobId);
        $jobInstance->handle(
            app(GeminiQuestionExtractionService::class),
            app(GeminiQuestionSolverService::class),
            app(IqpeVerificationService::class),
            app(IqpeQualityAuditService::class)
        );

        // Verificar que ningún payload de solver filtró is_correct ni clave oficial
        $this->assertCount(9, $capturedSolverPayloads, 'Deben ejecutarse exactamente 4 R1 + 4 R2 + 1 R3 = 9 llamadas al Solver');
        foreach ($capturedSolverPayloads as $solverPrompt) {
            $this->assertStringNotContainsString('is_correct', $solverPrompt);
            $this->assertStringNotContainsString('key_status', $solverPrompt);
        }

        // Verificar estado final del trabajo y del documento
        $iqpeJob = IqpeDocumentJob::find($jobId);
        $this->assertSame('completed', $iqpeJob->status);
        $this->assertSame(4, $iqpeJob->total_questions_detected);
        $this->assertSame('extracted', SourceDocument::find($docId)->status);

        $extractedQuestions = Question::where('source_document_id', $docId)
            ->orderBy('order_in_group', 'asc')
            ->get();
        $this->assertCount(4, $extractedQuestions);

        // Ninguna pregunta se aprueba ni publica automáticamente
        foreach ($extractedQuestions as $eq) {
            $this->assertSame('draft', $eq->status);
        }

        $q1 = $extractedQuestions->firstWhere('order_in_group', 1);
        $q2 = $extractedQuestions->firstWhere('order_in_group', 2);
        $q3 = $extractedQuestions->firstWhere('order_in_group', 3);
        $q4 = $extractedQuestions->firstWhere('order_in_group', 4);

        // Verificar veredictos de verificación
        $v1 = IqpeVerification::where('job_id', $jobId)->where('question_id', $q1->id)->first();
        $v2 = IqpeVerification::where('job_id', $jobId)->where('question_id', $q2->id)->first();
        $v3 = IqpeVerification::where('job_id', $jobId)->where('question_id', $q3->id)->first();

        $this->assertSame('verified_candidate', $v1->verdict);
        $this->assertFalse($v1->requires_admin_review);

        $this->assertSame('verified_candidate_no_official_key', $v2->verdict);
        $this->assertSame('verified_candidate', $v2->recommended_status);
        $this->assertTrue($v2->requires_admin_review);
        $this->assertSame('C', $v2->resolution_3_letter);

        $this->assertSame('official_discrepancy', $v3->verdict);
        $this->assertTrue($v3->requires_admin_review);

        // Verificar auditoría de calidad y detección de duplicados
        $a1 = IqpeQualityAudit::where('job_id', $jobId)->where('question_id', $q1->id)->first();
        $a2 = IqpeQualityAudit::where('job_id', $jobId)->where('question_id', $q2->id)->first();
        $a3 = IqpeQualityAudit::where('job_id', $jobId)->where('question_id', $q3->id)->first();
        $a4 = IqpeQualityAudit::where('job_id', $jobId)->where('question_id', $q4->id)->first();

        $this->assertTrue($a1->passed_quality_check);
        $this->assertTrue($a2->passed_quality_check);
        $this->assertFalse($a3->passed_quality_check);
        $this->assertTrue($a4->duplicate_risk_flag);
        $this->assertNotNull(Question::find($q4->id), 'El duplicado Q4 nunca debe eliminarse automáticamente');

        // =====================================================================
        // PASO 12: Aparición en el panel de procesamiento y bandeja administrativa
        // =====================================================================
        $statusRes = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson("/api/v1/admin/documents/{$docId}/iqpe-status");
        $statusRes->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.r1_completed_count', 4)
            ->assertJsonPath('data.r2_completed_count', 4)
            ->assertJsonPath('data.r3_completed_count', 1)
            ->assertJsonPath('data.verification_summary.verified_candidate_count', 2)
            ->assertJsonPath('data.verification_summary.verified_candidate_no_official_count', 1)
            ->assertJsonPath('data.verification_summary.requires_admin_review_count', 1)
            ->assertJsonPath('data.quality_audit_summary.passed_quality_count', 2)
            ->assertJsonPath('data.quality_audit_summary.duplicate_risk_count', 1);

        $queueRes = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson("/api/v1/admin/iqpe/review-queue?source_document_id={$docId}&filter=ready_for_approval");
        $queueRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('filter_counts.ready_for_approval', 1)
            ->assertJsonPath('filter_counts.requires_review', 3)
            ->assertJsonPath('filter_counts.with_official_key', 3)
            ->assertJsonPath('filter_counts.without_official_key', 1)
            ->assertJsonPath('filter_counts.discrepancies', 3)
            ->assertJsonPath('filter_counts.duplicates', 1);

        // =====================================================================
        // PASO 13: Revisión del detalle administrativo (/iqpe-detail)
        // =====================================================================
        $detailQ2 = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson("/api/v1/admin/questions/{$q2->id}/iqpe-detail");
        $detailQ2->assertStatus(200)
            ->assertJsonPath('data.resolutions.r1.solved_option_letter', 'C')
            ->assertJsonPath('data.resolutions.r2.solved_option_letter', 'C')
            ->assertJsonPath('data.resolutions.r3.solved_option_letter', 'C')
            ->assertJsonPath('data.verification.verdict', 'verified_candidate_no_official_key')
            ->assertJsonPath('data.eligibility.can_approve', true);

        // =====================================================================
        // PASO 14: Aprobación administrativa (draft -> review, NUNCA directo a published)
        // =====================================================================
        $approveQ1 = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson("/api/v1/admin/questions/{$q1->id}/approve", [
                'confirmed_option_letter' => 'B',
                'notes'                   => 'Aprobada en prueba E2E Bloque 7 con clave oficial B.',
            ]);
        $approveQ1->assertStatus(200)
            ->assertJsonPath('data.status', 'review');

        // Para Q2 (sin clave oficial original), el administrador asigna y confirma manualmente la clave candidata 'C' (R1=R2=R3=C)
        $setKeyQ2 = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->putJson("/api/v1/admin/questions/{$q2->id}", [
                'key_status'        => 'verified',
                'correct_option'    => 'C',
                'key_change_reason' => 'Clave C asignada manualmente tras consenso R1=R2=R3=C en verificación IQPE.',
            ]);
        $setKeyQ2->assertStatus(200)
            ->assertJsonPath('data.key_status', 'verified');

        $approveQ2 = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson("/api/v1/admin/questions/{$q2->id}/approve", [
                'notes'                => 'Aprobada en prueba E2E Bloque 7 por consenso R1=R2=R3=C.',
                'acknowledge_warnings' => true,
            ]);
        $approveQ2->assertStatus(200)
            ->assertJsonPath('data.status', 'review');

        // Confirmar que las preguntas en 'review' AÚN NO aparecen en endpoints estudiantiles
        $studentBeforePublish = $this->getJson('/api/v1/student/questions?subject_id=2');
        $studentBeforePublish->assertStatus(200)
            ->assertJsonPath('meta.total', 0);

        // =====================================================================
        // PASO 15: Publicación independiente (review -> published)
        // =====================================================================
        $publishQ1 = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson("/api/v1/admin/questions/{$q1->id}/publish", [
                'notes' => 'Publicada para banco estudiantil de Matemáticas.',
            ]);
        $publishQ1->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $publishQ2 = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson("/api/v1/admin/questions/{$q2->id}/publish", [
                'notes' => 'Publicada para banco estudiantil de Matemáticas.',
            ]);
        $publishQ2->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->assertGreaterThanOrEqual(5, IqpeAdminDecision::whereIn('question_id', [$q1->id, $q2->id])->count());

        // =====================================================================
        // PASO 16: Consulta desde los endpoints estudiantiles (seguridad de claves)
        // =====================================================================
        $studentSubjects = $this->getJson('/api/v1/student/subjects');
        $studentSubjects->assertStatus(200);
        $mathSubject = collect($studentSubjects->json('data'))->firstWhere('id', 2);
        $this->assertSame(2, $mathSubject['published_count']);

        $studentList = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->getJson('/api/v1/student/questions?subject_id=2');
        $studentList->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        foreach ($studentList->json('data') as $sQuestion) {
            $this->assertSame('published', $sQuestion['status']);
            $this->assertArrayNotHasKey('explanation', $sQuestion);
            foreach ($sQuestion['options'] as $sOpt) {
                $this->assertArrayNotHasKey('is_correct', $sOpt, 'Student listing must NEVER expose is_correct');
            }
        }

        // Q3 (draft) debe retornar 404 para el estudiante
        $this->getJson("/api/v1/student/questions/{$q3->id}")->assertStatus(404);

        // =====================================================================
        // PASO 17A: Resolución en módulo de Prácticas Estudiantiles
        // =====================================================================
        $answerRes = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->postJson("/api/v1/student/questions/{$q1->id}/answer", [
                'selected_option_letter' => 'B',
                'practice_mode'          => 'practice',
                'time_spent_seconds'     => 42,
            ]);
        $answerRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_correct', true)
            ->assertJsonPath('data.correct_option_letter', 'B');
        $this->assertNotEmpty($answerRes->json('data.explanation'));

        // =====================================================================
        // PASO 17B: Aparición y ejecución en Simulacros Estudiantiles (Mock Exams)
        // =====================================================================
        $examConfig = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->getJson('/api/v1/student/exams/config-options');
        $examConfig->assertStatus(200);
        $mathArea = collect($examConfig->json('data.areas'))->firstWhere('id', 2);
        $this->assertTrue($mathArea['available']);
        $this->assertSame(2, $mathArea['published_count']);

        $startExam = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->postJson('/api/v1/student/exams/start', [
                'subject_id'       => 2,
                'mode'             => 'area',
                'questions_count'  => 10,
                'duration_minutes' => 20,
            ]);
        $startExam->assertStatus(201)
            ->assertJsonPath('data.attempt.questions_count', 2)
            ->assertJsonPath('data.attempt.status', 'in_progress');

        $attemptId = $startExam->json('data.attempt.id');
        $examQuestions = $startExam->json('data.questions');
        $this->assertCount(2, $examQuestions);

        // Verificar que durante el simulacro activo no se expone is_correct ni explanation
        foreach ($examQuestions as $eqItem) {
            $this->assertArrayNotHasKey('explanation', $eqItem);
            foreach ($eqItem['options'] as $eqOpt) {
                $this->assertArrayNotHasKey('is_correct', $eqOpt);
            }
        }

        // Guardar respuestas en el simulacro
        $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/answers", [
                'question_id'            => $q1->id,
                'selected_option_letter' => 'B',
                'time_spent_seconds'     => 30,
            ])->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/answers", [
                'question_id'            => $q2->id,
                'selected_option_letter' => 'C',
                'time_spent_seconds'     => 45,
            ])->assertStatus(200);

        // Finalizar simulacro y verificar calificación 100% en backend
        $finishExam = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/finish");
        $finishExam->assertStatus(200)
            ->assertJsonPath('data.attempt.status', 'completed')
            ->assertJsonPath('data.attempt.correct_answers', 2)
            ->assertJsonPath('data.attempt.incorrect_answers', 0)
            ->assertJsonPath('data.attempt.accuracy', 100);

        // =====================================================================
        // VERIFICACIÓN DE INTEGRIDAD HISTÓRICA (SECCIÓN 5)
        // =====================================================================
        $this->assertSame(
            49,
            Question::where('source_document_id', $protectedDocId)->where('status', 'published')->count(),
            'Las 49 preguntas publicadas originales de Lectura Crítica deben permanecer intactas.'
        );
        $this->assertSame(
            10,
            StudentAnswer::where('student_id', 'like', 'student-historic-%')->count(),
            'Las 10 respuestas estudiantiles históricas deben permanecer intactas.'
        );
        $this->assertSame(
            9,
            MediaAsset::where('source_document_id', $protectedDocId)->count(),
            'Los 9 media_assets originales deben permanecer intactos.'
        );
    }

    /**
     * SECCIÓN 6 (ESCENARIOS 1, 2, 3, 4 y 7): PRUEBAS DE RECUPERACIÓN ANTE FALLOS
     *
     * Verifica:
     * - Error 429 transitorio de Gemini (reintento automático exitoso).
     * - Agotamiento de cuota diaria (429 PerDay -> cambio inmediato a modelo fallback gemini-3.1-flash-lite).
     * - Fallo en R2 -> preservación de R1 y recuperación selectiva con /retry-resolutions sin repetir extracción ni R1.
     * - Fallo en R3 -> preservación de R1/R2 y recuperación selectiva sin duplicar registros.
     */
    public function test_recovery_from_gemini_429_quota_exhaustion_r2_failure_and_r3_failure_without_duplicating_stages(): void
    {
        $storageMock = Mockery::mock(SupabaseStorageService::class);
        $storageMock->shouldReceive('exists')->andReturn(true);
        $storageMock->shouldReceive('get')->andReturn($this->makeValidPngBytes());
        $this->app->instance(SupabaseStorageService::class, $storageMock);

        $docId = (string) Str::uuid();
        $jobId = (string) Str::uuid();

        SourceDocument::create([
            'id'             => $docId,
            'title'          => 'Documento Recuperación R1/R2/R3 Bloque 7',
            'storage_path'   => 'documents/2026/recovery_b7.pdf',
            'exam_type'      => 'Saber 11',
            'status'         => 'extracted',
            'has_answer_key' => false,
        ]);

        // Q1: Con clave oficial A (Escenario A)
        $q1Id = (string) Str::uuid();
        Question::create([
            'id'                 => $q1Id,
            'root_question_id'   => $q1Id,
            'version'            => 1,
            'is_current_version' => true,
            'subject_id'         => 2,
            'source_document_id' => $docId,
            'order_in_group'     => 1,
            'statement'          => '¿Cuál es el valor de x en la ecuación 2x + 6 = 14?',
            'difficulty'         => 'easy',
            'key_status'         => 'verified',
            'status'             => 'draft',
        ]);
        foreach (['A' => '4', 'B' => '5', 'C' => '6', 'D' => '7'] as $l => $t) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $q1Id,
                'option_letter' => $l,
                'option_text'   => $t,
                'is_correct'    => $l === 'A',
            ]);
        }

        // Q2: Sin clave oficial (Escenario B -> requiere R3)
        $q2Id = (string) Str::uuid();
        Question::create([
            'id'                 => $q2Id,
            'root_question_id'   => $q2Id,
            'version'            => 1,
            'is_current_version' => true,
            'subject_id'         => 2,
            'source_document_id' => $docId,
            'order_in_group'     => 2,
            'statement'          => '¿Cuál es el área de un cuadrado cuyo lado mide 9 cm?',
            'difficulty'         => 'easy',
            'key_status'         => 'unverified',
            'status'             => 'draft',
        ]);
        foreach (['A' => '18 cm²', 'B' => '36 cm²', 'C' => '81 cm²', 'D' => '90 cm²'] as $l => $t) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $q2Id,
                'option_letter' => $l,
                'option_text'   => $t,
                'is_correct'    => null,
            ]);
        }

        IqpeDocumentJob::create([
            'id'                       => $jobId,
            'source_document_id'       => $docId,
            'run_number'               => 1,
            'status'                   => 'queued',
            'total_questions_detected' => 2,
        ]);

        // ---------------------------------------------------------------------
        // CORRIDA 1:
        // - Q1 R1: recibe 429 transitorio en intento 1, se recupera en intento 2 -> 'A'
        // - Q2 R1: recibe 429 de cuota diaria agotada (PerDay) en modelo primario, cambia a fallback -> 'C'
        // - Q1 R2: éxito -> 'A'
        // - Q2 R2: falla todos los intentos con HTTP 500 -> Job termina en 'failed' preservando R1 y Q1-R2
        // ---------------------------------------------------------------------
        $phase = 1;
        $callCounter = 0;
        $run2Calls = 0;
        $run3Calls = 0;

        Http::fake(function ($request) use (&$phase, &$callCounter, &$run2Calls, &$run3Calls) {
            $url = $request->url();

            if ($phase === 1) {
                $callCounter++;

                // Llamada 1: Q1 R1 (intento 1) -> 429 Rate Limit transitorio
                if ($callCounter === 1) {
                    return Http::response([
                        'error' => [
                            'code'    => 429,
                            'message' => 'Resource has been exhausted (e.g. check quota).',
                            'details' => [['retryDelay' => '1s']],
                        ],
                    ], 429);
                }

                // Llamada 2: Q1 R1 (intento 2) -> Éxito con 'A'
                if ($callCounter === 2) {
                    return Http::response([
                        'candidates' => [[
                            'content' => ['parts' => [['text' => json_encode([
                                'status'               => 'success',
                                'solved_option_letter' => 'A',
                                'solution_explanation' => '2(4) + 6 = 14.',
                                'confidence_score'     => 0.98,
                            ])]]],
                        ]],
                    ], 200);
                }

                // Llamada 3: Q2 R1 (intento 1 en modelo primario) -> 429 Cuota Diaria Agotada (PerDay)
                if ($callCounter === 3) {
                    return Http::response([
                        'error' => [
                            'code'    => 429,
                            'message' => 'Quota exceeded for metric: GenerateRequestsPerDayPerProjectPerModel',
                            'details' => [[
                                'violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']],
                            ]],
                        ],
                    ], 429);
                }

                // Llamada 4: Q2 R1 (intento inmediato en fallback gemini-3.1-flash-lite) -> Éxito con 'C'
                if ($callCounter === 4) {
                    $this->assertStringContainsString('gemini-3.1-flash-lite', $url);
                    return Http::response([
                        'candidates' => [[
                            'content' => ['parts' => [['text' => json_encode([
                                'status'               => 'success',
                                'solved_option_letter' => 'C',
                                'solution_explanation' => '9 * 9 = 81 cm².',
                                'confidence_score'     => 0.97,
                            ])]]],
                        ]],
                    ], 200);
                }

                // Llamada 5: Q1 R2 -> Éxito con 'A'
                if ($callCounter === 5) {
                    return Http::response([
                        'candidates' => [[
                            'content' => ['parts' => [['text' => json_encode([
                                'status'               => 'success',
                                'solved_option_letter' => 'A',
                                'solution_explanation' => 'Despejando x = (14-6)/2 = 4.',
                                'confidence_score'     => 0.99,
                            ])]]],
                        ]],
                    ], 200);
                }

                // Llamadas 6+: Q2 R2 falla con HTTP 500
                return Http::response(['error' => ['message' => 'Internal Server Error']], 500);
            }

            if ($phase === 2) {
                $run2Calls++;
                // Llamada 1 en Corrida 2: únicamente Q2 R2 (porque Q1 R1, Q2 R1 y Q1 R2 ya son success)
                if ($run2Calls === 1) {
                    return Http::response([
                        'candidates' => [[
                            'content' => ['parts' => [['text' => json_encode([
                                'status'               => 'success',
                                'solved_option_letter' => 'C',
                                'solution_explanation' => 'El área del cuadrado es lado al cuadrado: 81 cm².',
                                'confidence_score'     => 0.96,
                            ])]]],
                        ]],
                    ], 200);
                }

                // Llamadas siguientes (Q2 R3): falla con 503
                return Http::response(['error' => ['message' => 'Service Unavailable']], 503);
            }

            // Phase 3: únicamente Q2 R3
            $run3Calls++;
            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'status'               => 'success',
                        'solved_option_letter' => 'C',
                        'solution_explanation' => 'R3 independiente confirma 81 cm².',
                        'confidence_score'     => 0.99,
                    ])]]],
                ]],
            ], 200);
        });

        $job1 = new ProcessIqpeDocumentJob($docId, $jobId, skipExtraction: true);
        $job1->handle(
            app(GeminiQuestionExtractionService::class),
            app(GeminiQuestionSolverService::class),
            app(IqpeVerificationService::class),
            app(IqpeQualityAuditService::class)
        );

        $this->assertSame('failed', IqpeDocumentJob::find($jobId)->status);

        // Verificar que Q2 R1 registró el modelo fallback 'gemini-3.1-flash-lite'
        $q2R1 = IqpeQuestionResolution::where('job_id', $jobId)->where('question_id', $q2Id)->where('round_number', 1)->first();
        $this->assertSame('success', $q2R1->status);
        $this->assertSame('gemini-3.1-flash-lite', $q2R1->ai_model);

        // Verificar que Q2 R2 quedó como 'failed' mientras las otras 3 están en 'success'
        $q2R2 = IqpeQuestionResolution::where('job_id', $jobId)->where('question_id', $q2Id)->where('round_number', 2)->first();
        $this->assertSame('failed', $q2R2->status);

        // ---------------------------------------------------------------------
        // CORRIDA 2: Reanudar vía endpoint /retry-resolutions
        // - Q2 R2 ahora tiene éxito ('C'), pero simulamos fallo en Q2 R3 (HTTP 503)
        // ---------------------------------------------------------------------
        Queue::fake();
        $retryRes = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson("/api/v1/admin/documents/{$docId}/retry-resolutions");
        $retryRes->assertStatus(200)
            ->assertJsonPath('data.job_id', $jobId)
            ->assertJsonPath('data.skip_extraction', true);

        $phase = 2;
        $job2 = new ProcessIqpeDocumentJob($docId, $jobId, skipExtraction: true);
        $job2->handle(
            app(GeminiQuestionExtractionService::class),
            app(GeminiQuestionSolverService::class),
            app(IqpeVerificationService::class),
            app(IqpeQualityAuditService::class)
        );

        $this->assertSame('failed', IqpeDocumentJob::find($jobId)->status);
        $this->assertSame('success', IqpeQuestionResolution::where('job_id', $jobId)->where('question_id', $q2Id)->where('round_number', 2)->value('status'));
        $this->assertSame('failed', IqpeQuestionResolution::where('job_id', $jobId)->where('question_id', $q2Id)->where('round_number', 3)->value('status'));

        // ---------------------------------------------------------------------
        // CORRIDA 3: Reanudar nuevamente -> ejecuta ÚNICAMENTE Q2 R3 (1 llamada HTTP) y completa todo
        // ---------------------------------------------------------------------
        $phase = 3;
        IqpeDocumentJob::where('id', $jobId)->update(['status' => 'queued']);
        $job3 = new ProcessIqpeDocumentJob($docId, $jobId, skipExtraction: true);
        $job3->handle(
            app(GeminiQuestionExtractionService::class),
            app(GeminiQuestionSolverService::class),
            app(IqpeVerificationService::class),
            app(IqpeQualityAuditService::class)
        );

        $this->assertSame(1, $run3Calls, 'La recuperación de R3 debe realizar exactamente 1 petición HTTP (solo Q2 R3 pendiente).');
        $this->assertSame('completed', IqpeDocumentJob::find($jobId)->status);

        // Verificar idempotencia absoluta: 5 resoluciones totales (2 R1 + 2 R2 + 1 R3), 2 verificaciones, 2 auditorías
        $this->assertSame(5, IqpeQuestionResolution::where('job_id', $jobId)->count());
        $this->assertSame(2, IqpeVerification::where('job_id', $jobId)->count());
        $this->assertSame(2, IqpeQualityAudit::where('job_id', $jobId)->count());
    }

    /**
     * SECCIÓN 6 (ESCENARIOS 5 y 6): FALLO DE DESCARGA DE IMAGEN Y RECUPERACIÓN DE WORKER INTERRUMPIDO
     */
    public function test_image_download_failure_and_interrupted_worker_recovery(): void
    {
        // Mock de Storage donde el archivo de imagen falla al descargarse
        $storageMock = Mockery::mock(SupabaseStorageService::class);
        $storageMock->shouldReceive('exists')->andReturn(false);
        $this->app->instance(SupabaseStorageService::class, $storageMock);

        $docId = (string) Str::uuid();
        $staleJobId = (string) Str::uuid();

        SourceDocument::create([
            'id'             => $docId,
            'title'          => 'Documento Worker Interrumpido e Imagen Faltante',
            'storage_path'   => 'documents/2026/interrupted_worker.pdf',
            'exam_type'      => 'Saber 11',
            'status'         => 'extracted',
            'has_answer_key' => true,
        ]);

        // Trabajo previo que quedó colgado hace 25 minutos por interrupción del worker
        IqpeDocumentJob::create([
            'id'                       => $staleJobId,
            'source_document_id'       => $docId,
            'run_number'               => 1,
            'status'                   => 'solving_round_1',
            'total_questions_detected' => 1,
            'started_at'               => now()->subMinutes(25),
        ]);

        $qId = (string) Str::uuid();
        Question::create([
            'id'                  => $qId,
            'root_question_id'    => $qId,
            'version'             => 1,
            'is_current_version'  => true,
            'subject_id'          => 2,
            'source_document_id'  => $docId,
            'order_in_group'      => 1,
            'statement'           => 'Observa la siguiente figura geométrica y determina su perímetro total.',
            'question_image_path' => "extracted/{$docId}/missing_figure.png",
            'difficulty'          => 'medium',
            'key_status'          => 'verified',
            'status'              => 'draft',
        ]);
        foreach (['A' => '24 cm', 'B' => '30 cm', 'C' => '36 cm', 'D' => '42 cm'] as $l => $t) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $qId,
                'option_letter' => $l,
                'option_text'   => $t,
                'is_correct'    => $l === 'B',
            ]);
        }

        // Al invocar /retry sobre un trabajo estancado (>16 min), debe crear la corrida #2 con skip_extraction = true
        Queue::fake();
        $retryRes = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson("/api/v1/admin/documents/{$docId}/retry");
        $retryRes->assertStatus(200)
            ->assertJsonPath('data.run_number', 2)
            ->assertJsonPath('data.skip_extraction', true);

        $newJobId = $retryRes->json('data.job_id');

        // Ejecutar el nuevo Job: como la imagen no existe en Storage, R1 y R2 deben marcar 'uncertain' sin llamar a Gemini
        Http::fake();
        $job = new ProcessIqpeDocumentJob($docId, $newJobId, skipExtraction: true);
        $job->handle(
            app(GeminiQuestionExtractionService::class),
            app(GeminiQuestionSolverService::class),
            app(IqpeVerificationService::class),
            app(IqpeQualityAuditService::class)
        );

        Http::assertNothingSent();

        $r1 = IqpeQuestionResolution::where('job_id', $newJobId)->where('round_number', 1)->first();
        $r2 = IqpeQuestionResolution::where('job_id', $newJobId)->where('round_number', 2)->first();
        $this->assertSame('uncertain', $r1->status);
        $this->assertSame('uncertain', $r2->status);

        $audit = IqpeQualityAudit::where('job_id', $newJobId)->where('question_id', $qId)->first();
        $this->assertFalse($audit->passed_quality_check);
        $this->assertSame('critical', $audit->audit_warnings['highest_severity']);

        // Verificar que la aprobación administrativa está bloqueada mientras falte la imagen crítica
        $approveRes = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson("/api/v1/admin/questions/{$qId}/approve", [
                'confirmed_option_letter' => 'B',
            ]);
        $approveRes->assertStatus(422);
    }

    /**
     * SECCIÓN 7: EVALUACIÓN DE RENDIMIENTO, TOKENS Y DOCUMENTOS DE 50+ PREGUNTAS
     */
    public function test_performance_and_token_consumption_on_50_question_document(): void
    {
        $storageMock = Mockery::mock(SupabaseStorageService::class);
        $storageMock->shouldReceive('exists')->andReturn(true);
        $storageMock->shouldReceive('get')->andReturn($this->makeValidPngBytes());
        $this->app->instance(SupabaseStorageService::class, $storageMock);

        $docId = (string) Str::uuid();
        $jobId = (string) Str::uuid();

        SourceDocument::create([
            'id'             => $docId,
            'title'          => 'Cuadernillo Completo 50 Preguntas — Evaluación de Rendimiento Bloque 7',
            'storage_path'   => 'documents/2026/perf_50_questions.pdf',
            'exam_type'      => 'Saber 11',
            'status'         => 'extracted',
            'has_answer_key' => true,
        ]);

        // Sembrar 50 preguntas (45 con clave oficial [Escenario A] + 5 sin clave oficial [Escenario B])
        for ($i = 1; $i <= 50; $i++) {
            $qId = (string) Str::uuid();
            $hasKey = ($i <= 45);
            Question::create([
                'id'                 => $qId,
                'root_question_id'   => $qId,
                'version'            => 1,
                'is_current_version' => true,
                'subject_id'         => 2,
                'source_document_id' => $docId,
                'order_in_group'     => $i,
                'statement'          => "Pregunta de rendimiento matemático #{$i}: Si f(x) = {$i}x + 10, calcule el valor de f(2) para el reactivo #{$i}.",
                'difficulty'         => 'medium',
                'key_status'         => $hasKey ? 'verified' : 'unverified',
                'status'             => 'draft',
            ]);

            foreach (['A', 'B', 'C', 'D'] as $letter) {
                QuestionOption::create([
                    'id'            => (string) Str::uuid(),
                    'question_id'   => $qId,
                    'option_letter' => $letter,
                    'option_text'   => "Resultado {$letter} del reactivo #{$i} (" . (($i * 2) + ord($letter)) . ")",
                    'is_correct'    => $hasKey ? ($letter === 'B') : null,
                ]);
            }
        }

        IqpeDocumentJob::create([
            'id'                       => $jobId,
            'source_document_id'       => $docId,
            'run_number'               => 1,
            'status'                   => 'queued',
            'total_questions_detected' => 50,
        ]);

        $solverCalls = 0;
        Http::fake(function () use (&$solverCalls) {
            $solverCalls++;
            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'status'               => 'success',
                        'solved_option_letter' => 'B',
                        'solution_explanation' => 'Evaluación directa de la función lineal.',
                        'confidence_score'     => 0.95,
                    ])]]],
                ]],
                'usageMetadata' => [
                    'promptTokenCount'     => 380,
                    'candidatesTokenCount' => 95,
                    'totalTokenCount'      => 475,
                ],
            ], 200);
        });

        $t0 = microtime(true);
        $job = new ProcessIqpeDocumentJob($docId, $jobId, skipExtraction: true);
        $job->handle(
            app(GeminiQuestionExtractionService::class),
            app(GeminiQuestionSolverService::class),
            app(IqpeVerificationService::class),
            app(IqpeQualityAuditService::class)
        );
        $elapsedMs = round((microtime(true) - $t0) * 1000, 2);

        $iqpeJob = IqpeDocumentJob::find($jobId);
        $this->assertSame('completed', $iqpeJob->status);

        // 50 R1 + 50 R2 + 5 R3 = 105 llamadas exactas a Gemini
        $this->assertSame(105, $solverCalls);
        $this->assertSame(105, IqpeQuestionResolution::where('job_id', $jobId)->count());
        $this->assertSame(50, IqpeVerification::where('job_id', $jobId)->count());
        $this->assertSame(50, IqpeQualityAudit::where('job_id', $jobId)->count());

        $totalPromptTokens = (int) IqpeQuestionResolution::where('job_id', $jobId)->sum('prompt_tokens');
        $totalCompletionTokens = (int) IqpeQuestionResolution::where('job_id', $jobId)->sum('completion_tokens');
        $this->assertSame(105 * 380, $totalPromptTokens);
        $this->assertSame(105 * 95, $totalCompletionTokens);
        $this->assertLessThan(15000, $elapsedMs, 'El procesamiento en memoria de 50 preguntas (verificación + auditoría + detección de duplicados) debe ser rápido y eficiente.');
    }
}
