<?php

namespace Tests\Feature;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeQualityAudit;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class IqpeBloque5AuditFlowTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    private string $adminToken;
    private string $studentToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();

        $this->adminToken = StudentTokenService::generateToken([
            'id'    => 'admin-b5-1',
            'name'  => 'Admin Bloque 5',
            'email' => 'admin@athena.edu.co',
            'role'  => 'superadmin',
        ]);

        $this->studentToken = StudentTokenService::generateToken([
            'sub'   => 'student-b5-1',
            'id'    => 'student-b5-1',
            'name'  => 'Estudiante Bloque 5',
            'email' => 'student@athena.edu.co',
            'role'  => 'student',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function fakeGeminiResponse(string $letter, string $explanation): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => json_encode([
                        'status'                => 'success',
                        'solved_option_letter'  => $letter,
                        'solution_explanation'  => $explanation,
                        'visual_interpretation' => null,
                        'confidence_score'      => 0.96,
                    ]),
                ]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 210, 'candidatesTokenCount' => 45],
        ];
    }

    public function test_bloque5_pipeline_preserves_49_published_questions_and_student_history_and_isolates_per_question_failures(): void
    {
        // Sembrar las 49 preguntas publicadas de referencia + 10 respuestas de historial estudiantil
        $pubDoc = SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => 'cuadernillo saber 11 lectura critica',
            'storage_path' => 'documents/2026/lc_49.pdf',
            'year'         => 2026,
            'exam_type'    => 'Saber 11',
            'status'       => 'extracted',
        ]);

        $firstPublishedQ = null;
        $answerIds = [];

        for ($i = 1; $i <= 49; $i++) {
            $pq = Question::create([
                'id'                 => (string) Str::uuid(),
                'subject_id'         => 1,
                'source_document_id' => $pubDoc->id,
                'order_in_group'     => $i,
                'statement'          => "Pregunta publicada #{$i} de lectura crítica sobre análisis argumentativo e inferencia textual.",
                'explanation'        => "Explicación oficial publicada #{$i}.",
                'key_status'         => 'verified',
                'status'             => 'published',
            ]);
            if ($i === 1) {
                $firstPublishedQ = $pq;
            }

            $optB = null;
            foreach (['A', 'B', 'C', 'D'] as $letter) {
                $opt = QuestionOption::create([
                    'id'            => (string) Str::uuid(),
                    'question_id'   => $pq->id,
                    'option_letter' => $letter,
                    'option_text'   => "Alternativa {$letter} específica de la pregunta {$i}",
                    'is_correct'    => $letter === 'B',
                ]);
                if ($letter === 'B') {
                    $optB = $opt;
                }
            }

            if ($i <= 10) {
                $ans = StudentAnswer::create([
                    'id'                     => (string) Str::uuid(),
                    'student_id'             => 'student-hist-49',
                    'question_id'            => $pq->id,
                    'selected_option_id'     => $optB->id,
                    'selected_option_letter' => 'B',
                    'is_correct'             => true,
                    'practice_mode'          => 'practice',
                    'time_spent_seconds'     => 25,
                ]);
                $answerIds[] = $ans->id;
            }
        }

        // Nuevo documento en borrador con 3 preguntas:
        // Q1: Duplicado exacto de $firstPublishedQ (publicada)
        // Q2: Pregunta con opciones incompletas (defecto crítico)
        // Q3: Pregunta sana independiente
        $newDoc = SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => 'Cuadernillo Nuevo Bloque 5',
            'storage_path' => 'documents/2026/new_b5.pdf',
            'year'         => 2026,
            'exam_type'    => 'Saber 11',
            'status'       => 'uploaded',
        ]);

        $qDupId = (string) Str::uuid();
        $qBadOptsId = (string) Str::uuid();
        $qHealthyId = (string) Str::uuid();

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        $extractionMock = Mockery::mock(GeminiQuestionExtractionService::class);
        $extractionMock->shouldReceive('extractQuestionsFromDocument')
            ->once()
            ->andReturnUsing(function (SourceDocument $targetDoc) use ($qDupId, $qBadOptsId, $qHealthyId) {
                // Q1: Duplicado exacto de pregunta publicada #1
                Question::create([
                    'id'                 => $qDupId,
                    'subject_id'         => 1,
                    'source_document_id' => $targetDoc->id,
                    'order_in_group'     => 1,
                    'statement'          => 'Pregunta publicada #1 de lectura crítica sobre análisis argumentativo e inferencia textual.',
                    'key_status'         => 'verified',
                    'status'             => 'draft',
                ]);
                foreach (['A', 'B', 'C', 'D'] as $letter) {
                    QuestionOption::create([
                        'id'            => (string) Str::uuid(),
                        'question_id'   => $qDupId,
                        'option_letter' => $letter,
                        'option_text'   => "Alternativa {$letter} específica de la pregunta 1",
                        'is_correct'    => $letter === 'B',
                    ]);
                }

                // Q2: Opciones incompletas (solo 2 opciones -> crítica)
                Question::create([
                    'id'                 => $qBadOptsId,
                    'subject_id'         => 2,
                    'source_document_id' => $targetDoc->id,
                    'order_in_group'     => 2,
                    'statement'          => 'Un tanque se llena en 4 horas con una llave. ¿En cuánto tiempo se llena con dos llaves iguales?',
                    'key_status'         => 'verified',
                    'status'             => 'draft',
                ]);
                foreach (['A' => ['1 hora', false], 'B' => ['2 horas', true]] as $letter => [$txt, $corr]) {
                    QuestionOption::create([
                        'id'            => (string) Str::uuid(),
                        'question_id'   => $qBadOptsId,
                        'option_letter' => $letter,
                        'option_text'   => $txt,
                        'is_correct'    => $corr,
                    ]);
                }

                // Q3: Pregunta completamente sana e independiente
                Question::create([
                    'id'                 => $qHealthyId,
                    'subject_id'         => 2,
                    'source_document_id' => $targetDoc->id,
                    'order_in_group'     => 3,
                    'statement'          => 'Un estudiante tiene 3 cuadernos azules y 5 cuadernos rojos en su mochila. ¿Cuál es la probabilidad de sacar un cuaderno azul al azar?',
                    'key_status'         => 'verified',
                    'status'             => 'draft',
                ]);
                foreach (['A' => ['3/5', false], 'B' => ['3/8', true], 'C' => ['5/8', false], 'D' => ['1/8', false]] as $letter => [$txt, $corr]) {
                    QuestionOption::create([
                        'id'            => (string) Str::uuid(),
                        'question_id'   => $qHealthyId,
                        'option_letter' => $letter,
                        'option_text'   => $txt,
                        'is_correct'    => $corr,
                    ]);
                }

                $targetDoc->update(['status' => 'extracted']);
                return ['questions_extracted' => 3, 'images_detected_count' => 0, 'stimuli_created' => 0];
            });
        $this->app->instance(GeminiQuestionExtractionService::class, $extractionMock);

        Http::fake(function (HttpClientRequest $request) {
            return Http::response(
                $this->fakeGeminiResponse('B', 'Razonamiento lógico detallado que verifica la opción B.'),
                200
            );
        });

        // 1. Encolar y ejecutar trabajo completo IQPE
        $extractResp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/documents/{$newDoc->id}/extract")
            ->assertStatus(200);
        $jobId = $extractResp->json('data.job_id');

        Artisan::call('queue:work', [
            'connection'        => 'database',
            '--queue'           => 'default',
            '--once'            => true,
            '--stop-when-empty' => true,
        ]);

        // 2. El documento completa sin ser bloqueado por Q1/Q2, y distingue estados por pregunta
        $job = IqpeDocumentJob::find($jobId);
        $this->assertSame('completed', $job->status);
        $this->assertSame(1, $job->total_questions_verified, 'Solo Q3 sana y verificada debe quedar como lista verificada.');
        $this->assertSame(2, $job->total_questions_discrepancy, 'Q1 duplicado y Q2 opciones incompletas requieren revisión.');

        $auditDup = IqpeQualityAudit::where('job_id', $jobId)->where('question_id', $qDupId)->first();
        $auditBad = IqpeQualityAudit::where('job_id', $jobId)->where('question_id', $qBadOptsId)->first();
        $auditHealthy = IqpeQualityAudit::where('job_id', $jobId)->where('question_id', $qHealthyId)->first();

        $this->assertTrue($auditDup->duplicate_risk_flag);
        $this->assertSame($firstPublishedQ->id, $auditDup->duplicate_candidate_id);
        $this->assertFalse($auditDup->passed_quality_check);

        $this->assertFalse($auditBad->passed_quality_check);
        $this->assertSame('blocked_critical', $auditBad->audit_warnings['overall_classification']);

        $this->assertTrue($auditHealthy->passed_quality_check);
        $this->assertFalse($auditHealthy->duplicate_risk_flag);
        $this->assertSame('ready_for_approval', $auditHealthy->audit_warnings['overall_classification']);

        // Verificación de Q1 degradada a revisión administrativa por duplicado contra publicada
        $vDup = IqpeVerification::where('job_id', $jobId)->where('question_id', $qDupId)->first();
        $this->assertSame('requires_admin_review', $vDup->recommended_status);
        $this->assertTrue($vDup->requires_admin_review);

        // 3. Re-ejecutar auditoría vía POST /audit sin llamar a Gemini y sin duplicar registros
        $sentBeforeAuditRerun = count(Http::recorded());
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/documents/{$newDoc->id}/audit")
            ->assertStatus(200)
            ->assertJsonPath('data.duplicate_risk_count', 1)
            ->assertJsonPath('data.audit_status', 'requires_review');

        $this->assertCount($sentBeforeAuditRerun, Http::recorded(), 'Re-ejecutar auditoría no debe consumir llamadas a Gemini.');
        $this->assertSame(3, IqpeQualityAudit::where('job_id', $jobId)->count());

        // 4. Endpoint iqpe-status reporta quality_audit_summary
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->getJson("/api/v1/admin/documents/{$newDoc->id}/iqpe-status")
            ->assertStatus(200)
            ->assertJsonPath('data.quality_audit_summary.total_audited', 3)
            ->assertJsonPath('data.quality_audit_summary.passed_quality_count', 1)
            ->assertJsonPath('data.quality_audit_summary.requires_review_count', 2)
            ->assertJsonPath('data.quality_audit_summary.duplicate_risk_count', 1);

        // 5. REGLA ABSOLUTA: Ninguna pregunta eliminada; 49 publicadas y 10 respuestas intactas
        $this->assertSame(49, Question::where('status', 'published')->count());
        $this->assertSame(52, Question::count());
        $this->assertSame(10, StudentAnswer::whereIn('id', $answerIds)->count());
        $this->assertSame('published', Question::find($firstPublishedQ->id)->status);
        $this->assertSame('draft', Question::find($qHealthyId)->status);
    }
}
