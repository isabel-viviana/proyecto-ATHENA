<?php

namespace Tests\Feature;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeQuestionResolution;
use App\Models\IqpeVerification;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Models\StudentAnswer;
use App\Services\GeminiQuestionExtractionService;
use App\Services\StudentTokenService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class IqpeBloque4VerificationFlowTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    private string $adminToken;
    private string $studentToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();

        $this->adminToken = StudentTokenService::generateToken([
            'id'    => 'admin-b4-1',
            'name'  => 'Admin Bloque 4',
            'email' => 'admin@athena.edu.co',
            'role'  => 'superadmin',
        ]);

        $this->studentToken = StudentTokenService::generateToken([
            'sub'   => 'student-b4-1',
            'id'    => 'student-b4-1',
            'name'  => 'Estudiante Bloque 4',
            'email' => 'student@athena.edu.co',
            'role'  => 'student',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function fakeGeminiJsonResponse(string $letter, string $explanation, float $confidence = 0.96): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => json_encode([
                        'status'                => 'success',
                        'solved_option_letter'  => $letter,
                        'solution_explanation'  => $explanation,
                        'visual_interpretation' => null,
                        'confidence_score'      => $confidence,
                    ]),
                ]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 240, 'candidatesTokenCount' => 55],
        ];
    }

    public function test_full_pipeline_runs_r3_only_when_no_official_key_and_never_leaks_keys_or_prior_rounds(): void
    {
        $doc = SourceDocument::create([
            'id'             => (string) Str::uuid(),
            'title'          => 'Cuadernillo Mixto Clave y Sin Clave',
            'storage_path'   => 'documents/2026/mixto.pdf',
            'year'           => 2026,
            'exam_type'      => 'Saber 11',
            'has_answer_key' => false,
            'status'         => 'uploaded',
        ]);

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        $q1Id = (string) Str::uuid();
        $q2Id = (string) Str::uuid();

        $extractionMock = Mockery::mock(GeminiQuestionExtractionService::class);
        $extractionMock->shouldReceive('extractQuestionsFromDocument')
            ->once()
            ->andReturnUsing(function (SourceDocument $targetDoc) use ($q1Id, $q2Id) {
                // Q1: CON clave oficial válida (B)
                Question::create([
                    'id'                 => $q1Id,
                    'subject_id'         => 2,
                    'source_document_id' => $targetDoc->id,
                    'order_in_group'     => 1,
                    'statement'          => 'PREGUNTA_Q1_CON_CLAVE: ¿Cuánto es 10 + 5?',
                    'explanation'        => 'EXPLICACION_SECRETA_Q1',
                    'key_status'         => 'verified',
                    'status'             => 'draft',
                ]);
                foreach (['A' => ['12', false], 'B' => ['15', true], 'C' => ['18', false], 'D' => ['20', false]] as $l => [$t, $c]) {
                    QuestionOption::create([
                        'id'            => (string) Str::uuid(),
                        'question_id'   => $q1Id,
                        'option_letter' => $l,
                        'option_text'   => $t,
                        'is_correct'    => $c,
                    ]);
                }

                // Q2: SIN clave oficial (unverified, is_correct = null)
                Question::create([
                    'id'                 => $q2Id,
                    'subject_id'         => 2,
                    'source_document_id' => $targetDoc->id,
                    'order_in_group'     => 2,
                    'statement'          => 'PREGUNTA_Q2_SIN_CLAVE: ¿Cuánto es 9 * 3?',
                    'explanation'        => null,
                    'key_status'         => 'unverified',
                    'status'             => 'draft',
                ]);
                foreach (['A' => '18', 'B' => '24', 'C' => '27', 'D' => '36'] as $l => $t) {
                    QuestionOption::create([
                        'id'            => (string) Str::uuid(),
                        'question_id'   => $q2Id,
                        'option_letter' => $l,
                        'option_text'   => $t,
                        'is_correct'    => null,
                    ]);
                }

                $targetDoc->update(['status' => 'extracted']);
                return ['questions_extracted' => 2, 'images_detected_count' => 0, 'stimuli_created' => 0];
            });
        $this->app->instance(GeminiQuestionExtractionService::class, $extractionMock);

        $callCounter = 0;
        Http::fake(function (HttpClientRequest $request) use (&$callCounter) {
            $callCounter++;
            $payloadStr = json_encode($request->data());
            if (str_contains($payloadStr, 'PREGUNTA_Q1_CON_CLAVE')) {
                return Http::response($this->fakeGeminiJsonResponse('B', "Q1_EXPL_{$callCounter}: 10+5=15."), 200);
            }

            return Http::response($this->fakeGeminiJsonResponse('C', "Q2_EXPL_{$callCounter}: 9*3=27."), 200);
        });

        $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/documents/{$doc->id}/extract");
        $resp->assertStatus(200)->assertJsonPath('data.status', 'queued');
        $jobId = $resp->json('data.job_id');

        Artisan::call('queue:work', [
            'connection'        => 'database',
            '--queue'           => 'default',
            '--once'            => true,
            '--stop-when-empty' => true,
        ]);

        // Exactamente 5 llamadas HTTP: Q1 (R1, R2) + Q2 (R1, R2, R3). R3 NO se ejecuta para Q1.
        $recorded = Http::recorded();
        $this->assertCount(5, $recorded);

        foreach ($recorded as [$req]) {
            $body = json_encode($req->data());
            $this->assertStringNotContainsString('is_correct', $body);
            $this->assertStringNotContainsString('EXPLICACION_SECRETA_Q1', $body);
            $this->assertStringNotContainsString('key_status', $body);
            $this->assertStringNotContainsString('iqpe_verifications', $body);
        }

        // Verificar que la llamada #5 fue Q2 R3 y no contiene explicaciones ni referencias a R1/R2
        $r3Body = json_encode($recorded[4][0]->data());
        $this->assertStringContainsString('PREGUNTA_Q2_SIN_CLAVE', $r3Body);
        $this->assertStringNotContainsString('Q2_EXPL_2', $r3Body);
        $this->assertStringNotContainsString('Q2_EXPL_4', $r3Body);
        $this->assertStringNotContainsString('R1', $r3Body);
        $this->assertStringNotContainsString('R2', $r3Body);

        // Verificar resoluciones guardadas
        $this->assertSame(2, IqpeQuestionResolution::where('job_id', $jobId)->where('question_id', $q1Id)->count());
        $this->assertNull(IqpeQuestionResolution::where('job_id', $jobId)->where('question_id', $q1Id)->where('round_number', 3)->first());
        $this->assertSame(3, IqpeQuestionResolution::where('job_id', $jobId)->where('question_id', $q2Id)->count());

        // Verificar iqpe_verifications
        $v1 = IqpeVerification::where('job_id', $jobId)->where('question_id', $q1Id)->first();
        $v2 = IqpeVerification::where('job_id', $jobId)->where('question_id', $q2Id)->first();

        $this->assertSame('verified_candidate', $v1->verdict);
        $this->assertSame('verified_candidate', $v1->recommended_status);
        $this->assertFalse($v1->requires_admin_review);
        $this->assertNull($v1->resolution_3_id);

        $this->assertSame('verified_candidate_no_official_key', $v2->verdict);
        $this->assertSame('verified_candidate', $v2->recommended_status);
        $this->assertTrue($v2->requires_admin_review);
        $this->assertNotNull($v2->resolution_3_id);

        // Trabajo completado y preguntas permanecen en draft (Límite Bloque 4: sin publicación automática)
        $job = IqpeDocumentJob::find($jobId);
        $this->assertSame('completed', $job->status);
        $this->assertSame(2, $job->total_questions_verified);
        $this->assertSame(0, $job->total_questions_discrepancy);
        $this->assertSame('draft', Question::find($q1Id)->status);
        $this->assertSame('draft', Question::find($q2Id)->status);

        // Endpoint iqpe-status refleja conteos de R1, R2, R3 y resumen de verificación
        $statusResp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->getJson("/api/v1/admin/documents/{$doc->id}/iqpe-status");

        $statusResp->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.r1_completed_count', 2)
            ->assertJsonPath('data.r2_completed_count', 2)
            ->assertJsonPath('data.r3_completed_count', 1)
            ->assertJsonPath('data.verification_summary.verified_candidate_count', 1)
            ->assertJsonPath('data.verification_summary.verified_candidate_no_official_count', 1)
            ->assertJsonPath('data.verification_summary.requires_admin_review_count', 0);
    }

    public function test_r3_failure_recovers_via_retry_resolutions_without_repeating_extraction_r1_or_r2(): void
    {
        $doc = SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => 'Cuadernillo Recuperación R3',
            'storage_path' => 'documents/2026/r3_retry.pdf',
            'year'         => 2026,
            'exam_type'    => 'Saber 11',
            'status'       => 'extracted',
        ]);

        $q = Question::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 2,
            'source_document_id' => $doc->id,
            'order_in_group'     => 1,
            'statement'          => 'Pregunta sin clave para recuperación de R3: ¿Cuánto es 8 * 4?',
            'key_status'         => 'unverified',
            'status'             => 'draft',
        ]);
        foreach (['A' => '24', 'B' => '32', 'C' => '36', 'D' => '40'] as $l => $t) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $q->id,
                'option_letter' => $l,
                'option_text'   => $t,
                'is_correct'    => null,
            ]);
        }

        $job = IqpeDocumentJob::create([
            'id'                       => (string) Str::uuid(),
            'source_document_id'       => $doc->id,
            'run_number'               => 1,
            'status'                   => 'queued',
            'total_questions_detected' => 1,
            'config'                   => ['skip_extraction' => true],
        ]);

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        $extractionMock = Mockery::mock(GeminiQuestionExtractionService::class);
        $extractionMock->shouldNotReceive('extractQuestionsFromDocument');
        $this->app->instance(GeminiQuestionExtractionService::class, $extractionMock);

        $r3ShouldFail = true;
        $run1Requests = 0;
        $run2Requests = 0;
        $inRun2 = false;

        // Run 1: R1 OK, R2 OK, R3 falla (HTTP 400 no reintentable para fallo determinístico inmediato)
        Http::fake(function (HttpClientRequest $request) use (&$r3ShouldFail, &$run1Requests, &$run2Requests, &$inRun2) {
            if ($inRun2) {
                $run2Requests++;
                return Http::response($this->fakeGeminiJsonResponse('B', 'R3_RECUPERADA_8x4'), 200);
            }

            $run1Requests++;
            if ($run1Requests === 1) {
                return Http::response($this->fakeGeminiJsonResponse('B', 'R1_INTACTA_8x4'), 200);
            }
            if ($run1Requests === 2) {
                return Http::response($this->fakeGeminiJsonResponse('B', 'R2_INTACTA_8x4'), 200);
            }

            if ($r3ShouldFail) {
                return Http::response(['error' => ['code' => 400, 'message' => 'Simulated R3 error']], 400);
            }

            return Http::response($this->fakeGeminiJsonResponse('B', 'R3_RECUPERADA_8x4'), 200);
        });

        // Despachar y ejecutar Run 1 en cola
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/documents/{$doc->id}/retry-resolutions")
            ->assertStatus(200);

        Artisan::call('queue:work', [
            'connection'        => 'database',
            '--queue'           => 'default',
            '--once'            => true,
            '--stop-when-empty' => true,
        ]);

        $jobAfterRun1 = IqpeDocumentJob::find($job->id);
        $this->assertSame('failed', $jobAfterRun1->status, 'No debe completarse mientras R3 esté fallida.');
        $this->assertSame(0, IqpeVerification::where('job_id', $job->id)->count());

        $r1Orig = IqpeQuestionResolution::where('job_id', $job->id)->where('round_number', 1)->first();
        $r2Orig = IqpeQuestionResolution::where('job_id', $job->id)->where('round_number', 2)->first();
        $r3Orig = IqpeQuestionResolution::where('job_id', $job->id)->where('round_number', 3)->first();

        $this->assertSame('success', $r1Orig->status);
        $this->assertSame('success', $r2Orig->status);
        $this->assertSame('failed', $r3Orig->status);

        // Limpiar jobs liberados antes de simular reanudación manual en Run 2
        DB::connection('question_bank')->table('jobs')->truncate();

        // Run 2: Simular éxito únicamente en la nueva llamada R3
        $r3ShouldFail = false;
        $inRun2 = true;

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/documents/{$doc->id}/retry-resolutions")
            ->assertStatus(200);

        Artisan::call('queue:work', [
            'connection'        => 'database',
            '--queue'           => 'default',
            '--once'            => true,
            '--stop-when-empty' => true,
        ]);

        // Solo debió ejecutarse 1 llamada HTTP (R3 fallida); ni R1 ni R2 se repitieron
        $this->assertSame(1, $run2Requests);
        Http::assertSentCount(4);

        $r1After = IqpeQuestionResolution::find($r1Orig->id);
        $r2After = IqpeQuestionResolution::find($r2Orig->id);
        $r3After = IqpeQuestionResolution::find($r3Orig->id);

        $this->assertSame('R1_INTACTA_8x4', $r1After->solution_explanation);
        $this->assertSame('R2_INTACTA_8x4', $r2After->solution_explanation);
        $this->assertSame('success', $r3After->status);
        $this->assertSame('B', $r3After->solved_option_letter);
        $this->assertSame(3, IqpeQuestionResolution::where('job_id', $job->id)->count());

        $v = IqpeVerification::where('job_id', $job->id)->where('question_id', $q->id)->first();
        $this->assertNotNull($v);
        $this->assertSame('verified_candidate_no_official_key', $v->verdict);
        $this->assertSame('completed', IqpeDocumentJob::find($job->id)->status);
    }

    public function test_student_endpoints_and_published_questions_history_remain_intact_after_bloque4(): void
    {
        // Documento publicado existente con historial de respuestas
        $pubDoc = SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => 'Cuadernillo Lectura Crítica Existente',
            'storage_path' => 'documents/2026/lc_pub.pdf',
            'year'         => 2026,
            'exam_type'    => 'Saber 11',
            'status'       => 'extracted',
        ]);

        $pubQ = Question::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 1,
            'source_document_id' => $pubDoc->id,
            'order_in_group'     => 1,
            'statement'          => 'Pregunta publicada existente de Lectura Crítica.',
            'explanation'        => 'La respuesta correcta es B.',
            'key_status'         => 'verified',
            'status'             => 'published',
        ]);

        $optIds = [];
        foreach (['A' => ['Alt A', false], 'B' => ['Alt B', true], 'C' => ['Alt C', false], 'D' => ['Alt D', false]] as $l => [$t, $c]) {
            $opt = QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $pubQ->id,
                'option_letter' => $l,
                'option_text'   => $t,
                'is_correct'    => $c,
            ]);
            $optIds[$l] = $opt->id;
        }

        $histAnswer = StudentAnswer::create([
            'id'                     => (string) Str::uuid(),
            'student_id'             => 'student-hist-1',
            'question_id'            => $pubQ->id,
            'selected_option_id'     => $optIds['B'],
            'selected_option_letter' => 'B',
            'is_correct'             => true,
            'practice_mode'          => 'practice',
            'time_spent_seconds'     => 22,
        ]);

        // Nuevo documento en borrador procesado con Bloque 4
        $draftDoc = SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => 'Cuadernillo Nuevo Borrador',
            'storage_path' => 'documents/2026/draft_b4.pdf',
            'year'         => 2026,
            'exam_type'    => 'Saber 11',
            'status'       => 'extracted',
        ]);

        $draftQ = Question::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 2,
            'source_document_id' => $draftDoc->id,
            'order_in_group'     => 1,
            'statement'          => 'Pregunta en borrador sin clave oficial: ¿Cuánto es 5 + 5?',
            'key_status'         => 'unverified',
            'status'             => 'draft',
        ]);
        foreach (['A' => '8', 'B' => '10', 'C' => '12', 'D' => '15'] as $l => $t) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $draftQ->id,
                'option_letter' => $l,
                'option_text'   => $t,
                'is_correct'    => null,
            ]);
        }

        $job = IqpeDocumentJob::create([
            'id'                       => (string) Str::uuid(),
            'source_document_id'       => $draftDoc->id,
            'run_number'               => 1,
            'status'                   => 'queued',
            'total_questions_detected' => 1,
            'config'                   => ['skip_extraction' => true],
        ]);

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        Http::fake([
            '*' => Http::response($this->fakeGeminiJsonResponse('B', '5 + 5 = 10'), 200),
        ]);

        (new \App\Jobs\ProcessIqpeDocumentJob($draftDoc->id, $job->id, skipExtraction: true))->handle(
            $this->app->make(GeminiQuestionExtractionService::class),
            $this->app->make(\App\Services\GeminiQuestionSolverService::class),
            $this->app->make(\App\Services\IqpeVerificationService::class)
        );

        // Integridad intacta de preguntas publicadas e historial
        $this->assertSame('published', Question::find($pubQ->id)->status);
        $this->assertSame(1, Question::where('status', 'published')->count());
        $this->assertSame('draft', Question::find($draftQ->id)->status);
        $this->assertNotNull(StudentAnswer::find($histAnswer->id));

        // Endpoints de estudiantes funcionando sin exponer is_correct ni preguntas draft
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->getJson('/api/v1/student/subjects')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $listResp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->getJson('/api/v1/student/questions')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $listResp->json('data'));
        $this->assertSame($pubQ->id, $listResp->json('data.0.id'));
        $this->assertStringNotContainsString('is_correct', $listResp->getContent());

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->postJson("/api/v1/student/questions/{$pubQ->id}/answer", [
                'selected_option_letter' => 'B',
                'time_spent_seconds'     => 10,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_correct', true);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->getJson('/api/v1/student/progress')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
