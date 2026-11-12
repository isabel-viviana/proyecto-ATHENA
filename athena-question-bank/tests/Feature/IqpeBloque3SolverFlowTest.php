<?php

namespace Tests\Feature;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\IqpeQuestionResolution;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Services\GeminiQuestionExtractionService;
use App\Services\StudentTokenService;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class IqpeBloque3SolverFlowTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    private string $adminToken;
    private string $studentToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();

        $this->adminToken = StudentTokenService::generateToken([
            'id'    => 'admin-test-1',
            'name'  => 'Admin Test',
            'email' => 'admin@athena.edu.co',
            'role'  => 'superadmin',
        ]);

        $this->studentToken = StudentTokenService::generateToken([
            'sub'   => 'student-test-1',
            'id'    => 'student-test-1',
            'name'  => 'Estudiante Test',
            'email' => 'student@athena.edu.co',
            'role'  => 'student',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_async_queue_dispatches_extract_and_independent_r1_r2_without_blocking_http(): void
    {
        $doc = SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => 'Cuadernillo Matemáticas Prueba Cola',
            'storage_path' => 'documents/2026/sample.pdf',
            'year'         => 2026,
            'exam_type'    => 'Saber 11',
            'status'       => 'uploaded',
        ]);

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        $extractionMock = Mockery::mock(GeminiQuestionExtractionService::class);
        $extractionMock->shouldReceive('extractQuestionsFromDocument')
            ->once()
            ->andReturnUsing(function (SourceDocument $targetDoc) {
                $q = Question::create([
                    'id'                 => (string) Str::uuid(),
                    'subject_id'         => 2,
                    'source_document_id' => $targetDoc->id,
                    'order_in_group'     => 1,
                    'statement'          => 'En una encuesta a 40 estudiantes, el 25% prefiere matemáticas. ¿Cuántos estudiantes son?',
                    'status'             => 'draft',
                    'key_status'         => 'unverified',
                ]);
                foreach (['A' => ['5', false], 'B' => ['10', true], 'C' => ['15', false], 'D' => ['20', false]] as $letter => [$text, $correct]) {
                    QuestionOption::create([
                        'id'            => (string) Str::uuid(),
                        'question_id'   => $q->id,
                        'option_letter' => $letter,
                        'option_text'   => $text,
                        'is_correct'    => $correct,
                    ]);
                }
                $targetDoc->update(['status' => 'extracted']);

                return [
                    'questions_extracted'   => 1,
                    'images_detected_count' => 0,
                    'stimuli_created'       => 0,
                ];
            });
        $this->app->instance(GeminiQuestionExtractionService::class, $extractionMock);

        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => json_encode([
                                'status'                => 'success',
                                'solved_option_letter'  => 'B',
                                'solution_explanation'  => 'R1: El 25% de 40 equivale a 10 estudiantes.',
                                'visual_interpretation' => null,
                                'confidence_score'      => 0.99,
                            ]),
                        ]]],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => ['promptTokenCount' => 310, 'candidatesTokenCount' => 70],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => json_encode([
                                'status'                => 'success',
                                'solved_option_letter'  => 'B',
                                'solution_explanation'  => 'R2: Multiplicando 40 por 0.25 se obtiene 10.',
                                'visual_interpretation' => null,
                                'confidence_score'      => 0.97,
                            ]),
                        ]]],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => ['promptTokenCount' => 325, 'candidatesTokenCount' => 75],
                ], 200),
        ]);

        // 1. Petición HTTP no bloqueante: solo encola el job
        $extractResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/v1/admin/documents/{$doc->id}/extract");

        $extractResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'queued');

        $jobId = $extractResponse->json('data.job_id');
        $this->assertSame(1, DB::connection('question_bank')->table('jobs')->count());
        $this->assertSame(0, IqpeQuestionResolution::count());

        // 2. Worker asíncrono procesa extracción + R1 + R2
        $exitCode = Artisan::call('queue:work', [
            'connection'        => 'database',
            '--queue'           => 'default',
            '--once'            => true,
            '--stop-when-empty' => true,
        ]);
        $this->assertSame(0, $exitCode);

        $iqpeJob = IqpeDocumentJob::find($jobId);
        $this->assertSame('completed', $iqpeJob->status);
        $this->assertSame(1, $iqpeJob->total_questions_detected);

        $question = Question::where('source_document_id', $doc->id)->first();
        $this->assertNotNull($question);
        $this->assertSame('draft', $question->status); // Límite Bloque 3: no publica automáticamente

        $r1 = IqpeQuestionResolution::where('job_id', $jobId)->where('round_number', 1)->first();
        $r2 = IqpeQuestionResolution::where('job_id', $jobId)->where('round_number', 2)->first();
        $this->assertNotNull($r1);
        $this->assertNotNull($r2);
        $this->assertSame('B', $r1->solved_option_letter);
        $this->assertSame('B', $r2->solved_option_letter);
        $this->assertSame(0, IqpeQuestionResolution::where('job_id', $jobId)->where('round_number', 3)->count());

        // 3. Endpoint iqpe-status refleja R1 y R2 completados
        $statusResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/v1/admin/documents/{$doc->id}/iqpe-status");

        $statusResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.r1_completed_count', 1)
            ->assertJsonPath('data.r2_completed_count', 1);
    }

    public function test_retry_resolutions_recovers_only_failed_rounds_without_reextracting_or_duplicating(): void
    {
        $doc = SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => 'Cuadernillo Recuperación',
            'storage_path' => 'documents/2026/retry.pdf',
            'year'         => 2026,
            'exam_type'    => 'Saber 11',
            'status'       => 'extracted',
        ]);

        $q = Question::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 2,
            'source_document_id' => $doc->id,
            'order_in_group'     => 1,
            'statement'          => '¿Cuánto es 3 * 7?',
            'status'             => 'draft',
        ]);
        foreach (['A' => '14', 'B' => '21', 'C' => '24', 'D' => '28'] as $l => $t) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $q->id,
                'option_letter' => $l,
                'option_text'   => $t,
                'is_correct'    => $l === 'B',
            ]);
        }

        $job = IqpeDocumentJob::create([
            'id'                       => (string) Str::uuid(),
            'source_document_id'       => $doc->id,
            'run_number'               => 1,
            'status'                   => 'failed',
            'total_questions_detected' => 1,
            'error_log'                => 'Fallo parcial en R2',
        ]);

        // R1 ya completada con éxito; R2 en estado failed
        $r1Original = IqpeQuestionResolution::create([
            'id'                   => (string) Str::uuid(),
            'job_id'               => $job->id,
            'question_id'          => $q->id,
            'round_number'         => 1,
            'ai_model'             => 'gemini-3-flash-preview',
            'prompt_tokens'        => 180,
            'completion_tokens'    => 45,
            'latency_ms'           => 950,
            'solved_option_letter' => 'B',
            'confidence_score'     => 0.99,
            'solution_explanation' => 'R1_ORIGINAL_INTACTA',
            'status'               => 'success',
        ]);

        $r2Failed = IqpeQuestionResolution::create([
            'id'                   => (string) Str::uuid(),
            'job_id'               => $job->id,
            'question_id'          => $q->id,
            'round_number'         => 2,
            'ai_model'             => 'gemini-3-flash-preview',
            'solved_option_letter' => null,
            'solution_explanation' => 'Error simulado HTTP 503',
            'status'               => 'failed',
        ]);

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        $extractionMock = Mockery::mock(GeminiQuestionExtractionService::class);
        $extractionMock->shouldNotReceive('extractQuestionsFromDocument');
        $this->app->instance(GeminiQuestionExtractionService::class, $extractionMock);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'status'                => 'success',
                            'solved_option_letter'  => 'B',
                            'solution_explanation'  => 'R2_RECUPERADA: 3 por 7 es 21.',
                            'visual_interpretation' => null,
                            'confidence_score'      => 0.98,
                        ]),
                    ]]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 190, 'candidatesTokenCount' => 50],
            ], 200),
        ]);

        $retryResp = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/v1/admin/documents/{$doc->id}/retry-resolutions");

        $retryResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skip_extraction', true);

        Artisan::call('queue:work', [
            'connection'        => 'database',
            '--queue'           => 'default',
            '--once'            => true,
            '--stop-when-empty' => true,
        ]);

        // Solo 1 llamada HTTP realizada (para R2 fallida); R1 no se re-ejecutó
        Http::assertSentCount(1);

        $this->assertSame(1, IqpeQuestionResolution::where('job_id', $job->id)->where('round_number', 1)->count());
        $this->assertSame(1, IqpeQuestionResolution::where('job_id', $job->id)->where('round_number', 2)->count());

        $r1After = IqpeQuestionResolution::find($r1Original->id);
        $r2After = IqpeQuestionResolution::find($r2Failed->id);

        $this->assertSame('R1_ORIGINAL_INTACTA', $r1After->solution_explanation);
        $this->assertSame('success', $r2After->status);
        $this->assertSame('B', $r2After->solved_option_letter);
        $this->assertSame('completed', IqpeDocumentJob::find($job->id)->status);
        $this->assertTrue(
            IqpeExecutionLog::where('job_id', $job->id)->where('message', 'like', '%Extracción omitida%')->exists()
        );
    }

    public function test_existing_athena_admin_and_student_endpoints_continue_working(): void
    {
        $doc = SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => 'Cuadernillo Publicado Existente',
            'storage_path' => 'documents/2026/published.pdf',
            'year'         => 2026,
            'exam_type'    => 'Saber 11',
            'status'       => 'extracted',
        ]);

        $publishedQ = Question::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 1,
            'source_document_id' => $doc->id,
            'order_in_group'     => 1,
            'statement'          => 'Pregunta publicada de lectura crítica para práctica estudiantil.',
            'explanation'        => 'La respuesta correcta es B.',
            'key_status'         => 'verified',
            'status'             => 'published',
        ]);

        foreach (['A' => ['Opción A', false], 'B' => ['Opción B', true], 'C' => ['Opción C', false], 'D' => ['Opción D', false]] as $l => [$t, $c]) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $publishedQ->id,
                'option_letter' => $l,
                'option_text'   => $t,
                'is_correct'    => $c,
            ]);
        }

        // 1. Health endpoint
        $this->getJson('/api/v1/health')->assertStatus(200);

        // 2. Admin questions endpoint
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->getJson('/api/v1/admin/questions')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.published_count', 1);

        // 3. Student questions endpoint (sin exponer is_correct)
        $studentList = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->getJson('/api/v1/student/questions')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertStringNotContainsString('is_correct', $studentList->getContent());

        // 4. Student answer submission
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->postJson("/api/v1/student/questions/{$publishedQ->id}/answer", [
                'selected_option_letter' => 'B',
                'time_spent_seconds'     => 15,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_correct', true);
    }
}

