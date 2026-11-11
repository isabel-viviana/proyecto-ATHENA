<?php

namespace Tests\Unit;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeQuestionResolution;
use App\Models\IqpeVerification;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Services\GeminiQuestionSolverService;
use App\Services\IqpeVerificationService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class IqpeVerificationServiceTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    private IqpeVerificationService $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();
        $this->verifier = $this->app->make(IqpeVerificationService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeDocument(): SourceDocument
    {
        return SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => 'Cuadernillo Bloque 4 Unit',
            'storage_path' => 'documents/2026/b4.pdf',
            'year'         => 2026,
            'exam_type'    => 'Saber 11',
            'status'       => 'extracted',
        ]);
    }

    private function makeJob(SourceDocument $doc): IqpeDocumentJob
    {
        return IqpeDocumentJob::create([
            'id'                 => (string) Str::uuid(),
            'source_document_id' => $doc->id,
            'run_number'         => 1,
            'status'             => 'verifying',
        ]);
    }

    /**
     * @param array<string, ?bool> $isCorrectMap
     */
    private function makeQuestion(SourceDocument $doc, string $keyStatus, array $isCorrectMap, int $order = 1): Question
    {
        $q = Question::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 2,
            'source_document_id' => $doc->id,
            'order_in_group'     => $order,
            'statement'          => "Pregunta #{$order} de verificación Bloque 4: calcula 6 + 4.",
            'explanation'        => 'EXPLICACION_OFICIAL_PRIVADA',
            'key_status'         => $keyStatus,
            'status'             => 'draft',
        ]);

        foreach (['A' => '8', 'B' => '10', 'C' => '12', 'D' => '14'] as $letter => $text) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $q->id,
                'option_letter' => $letter,
                'option_text'   => $text,
                'is_correct'    => $isCorrectMap[$letter] ?? null,
            ]);
        }

        return $q->fresh(['options']);
    }

    private function makeResolution(
        IqpeDocumentJob $job,
        Question $q,
        int $round,
        string $status,
        ?string $letter,
        float $confidence = 0.95
    ): IqpeQuestionResolution {
        return IqpeQuestionResolution::create([
            'id'                   => (string) Str::uuid(),
            'job_id'               => $job->id,
            'question_id'          => $q->id,
            'round_number'         => $round,
            'ai_model'             => 'gemini-3-flash-preview',
            'prompt_tokens'        => 200,
            'completion_tokens'    => 60,
            'latency_ms'           => 800,
            'solved_option_letter' => $letter,
            'confidence_score'     => $status === 'success' ? $confidence : null,
            'solution_explanation' => "Explicación ronda {$round}",
            'status'               => $status,
        ]);
    }

    public function test_scenario_a_r1_r2_and_official_key_match_yields_verified_candidate(): void
    {
        $doc = $this->makeDocument();
        $job = $this->makeJob($doc);
        $q = $this->makeQuestion($doc, 'verified', ['A' => false, 'B' => true, 'C' => false, 'D' => false]);

        $r1 = $this->makeResolution($job, $q, 1, 'success', 'B', 0.98);
        $r2 = $this->makeResolution($job, $q, 2, 'success', 'B', 0.96);

        $stats = $this->verifier->verifyJobQuestions($job, collect([$q]));

        $this->assertSame(1, $stats['verified_candidate']);
        $this->assertSame(0, $stats['requires_admin_review']);

        $v = IqpeVerification::where('job_id', $job->id)->where('question_id', $q->id)->first();
        $this->assertNotNull($v);
        $this->assertTrue($v->has_official_key);
        $this->assertSame('B', $v->official_key_letter);
        $this->assertSame($r1->id, $v->resolution_1_id);
        $this->assertSame($r2->id, $v->resolution_2_id);
        $this->assertNull($v->resolution_3_id);
        $this->assertNull($v->resolution_3_letter);
        $this->assertSame('verified_candidate', $v->verdict);
        $this->assertSame('verified_candidate', $v->recommended_status);
        $this->assertFalse($v->requires_admin_review);
        $this->assertEqualsWithDelta(0.97, $v->confidence_index, 0.001);
    }

    public function test_scenario_a_r1_r2_agree_but_contradict_official_key_sends_to_admin_review_without_changing_key(): void
    {
        $doc = $this->makeDocument();
        $job = $this->makeJob($doc);
        $q = $this->makeQuestion($doc, 'verified', ['A' => false, 'B' => false, 'C' => true, 'D' => false]);

        $this->makeResolution($job, $q, 1, 'success', 'B');
        $this->makeResolution($job, $q, 2, 'success', 'B');

        $this->verifier->verifyJobQuestions($job, collect([$q]));

        $v = IqpeVerification::where('job_id', $job->id)->where('question_id', $q->id)->first();
        $this->assertSame('official_discrepancy', $v->verdict);
        $this->assertSame('requires_admin_review', $v->recommended_status);
        $this->assertTrue($v->requires_admin_review);
        $this->assertSame('C', $v->official_key_letter);
        $this->assertStringContainsString('Discrepancia con clave oficial', $v->discrepancy_details);

        // La clave oficial en BD NO debe ser alterada
        $freshQ = Question::with('options')->find($q->id);
        $this->assertSame('verified', $freshQ->key_status);
        $this->assertTrue($freshQ->options->firstWhere('option_letter', 'C')->is_correct);
        $this->assertFalse($freshQ->options->firstWhere('option_letter', 'B')->is_correct);
    }

    public function test_scenario_a_r1_and_r2_disagree_with_official_key_sends_to_admin_review(): void
    {
        $doc = $this->makeDocument();
        $job = $this->makeJob($doc);
        $q = $this->makeQuestion($doc, 'verified', ['A' => false, 'B' => true, 'C' => false, 'D' => false]);

        $this->makeResolution($job, $q, 1, 'success', 'B');
        $this->makeResolution($job, $q, 2, 'success', 'A');

        $this->verifier->verifyJobQuestions($job, collect([$q]));

        $v = IqpeVerification::where('job_id', $job->id)->where('question_id', $q->id)->first();
        $this->assertSame('ai_disagreement', $v->verdict);
        $this->assertSame('requires_admin_review', $v->recommended_status);
        $this->assertTrue($v->requires_admin_review);
        $this->assertNull($v->resolution_3_id);
    }

    public function test_scenario_a_uncertain_resolution_or_unreliable_official_key_sends_to_admin_review(): void
    {
        $doc = $this->makeDocument();
        $job = $this->makeJob($doc);

        // Q1: clave oficial válida pero R2 incierta
        $qUncertain = $this->makeQuestion($doc, 'verified', ['A' => false, 'B' => true, 'C' => false, 'D' => false], 1);
        $this->makeResolution($job, $qUncertain, 1, 'success', 'B');
        $this->makeResolution($job, $qUncertain, 2, 'uncertain', null);

        // Q2: clave oficial marcada 'verified' pero 0 opciones con is_correct=true (incompleta)
        $qIncompleteKey = $this->makeQuestion($doc, 'verified', ['A' => false, 'B' => false, 'C' => false, 'D' => false], 2);
        $this->makeResolution($job, $qIncompleteKey, 1, 'success', 'B');
        $this->makeResolution($job, $qIncompleteKey, 2, 'success', 'B');

        // Q3: clave oficial marcada 'disputed' (no confiable)
        $qDisputedKey = $this->makeQuestion($doc, 'disputed', ['A' => false, 'B' => true, 'C' => false, 'D' => false], 3);
        $this->makeResolution($job, $qDisputedKey, 1, 'success', 'B');
        $this->makeResolution($job, $qDisputedKey, 2, 'success', 'B');

        $classification = $this->verifier->classifyQuestionsForVerification(collect([$qUncertain, $qIncompleteKey, $qDisputedKey]));
        $this->assertSame([], $classification['scenario_b_ids'], 'Ninguna clave verificada/disputada/incompleta debe enviarse a R3.');

        $this->verifier->verifyJobQuestions($job, collect([$qUncertain, $qIncompleteKey, $qDisputedKey]));

        $v1 = IqpeVerification::where('job_id', $job->id)->where('question_id', $qUncertain->id)->first();
        $v2 = IqpeVerification::where('job_id', $job->id)->where('question_id', $qIncompleteKey->id)->first();
        $v3 = IqpeVerification::where('job_id', $job->id)->where('question_id', $qDisputedKey->id)->first();

        $this->assertSame('inconclusive', $v1->verdict);
        $this->assertSame('requires_admin_review', $v1->recommended_status);
        $this->assertTrue($v1->requires_admin_review);

        $this->assertSame('unreliable_official_key', $v2->verdict);
        $this->assertSame('requires_admin_review', $v2->recommended_status);

        $this->assertSame('unreliable_official_key', $v3->verdict);
        $this->assertSame('requires_admin_review', $v3->recommended_status);
    }

    public function test_scenario_b_no_official_key_outcomes_and_idempotent_persistence(): void
    {
        $doc = $this->makeDocument();
        $job = $this->makeJob($doc);

        // Q1: R1=R2=R3 = B (todas concluyentes sin clave oficial)
        $qAgree = $this->makeQuestion($doc, 'unverified', ['A' => null, 'B' => null, 'C' => null, 'D' => null], 1);
        $this->makeResolution($job, $qAgree, 1, 'success', 'B', 0.99);
        $this->makeResolution($job, $qAgree, 2, 'success', 'B', 0.98);
        $r3Agree = $this->makeResolution($job, $qAgree, 3, 'success', 'B', 0.97);

        // Q2: R1=B, R2=B, R3=C (difieren sin clave oficial)
        $qDiffer = $this->makeQuestion($doc, 'unverified', ['A' => null, 'B' => null, 'C' => null, 'D' => null], 2);
        $this->makeResolution($job, $qDiffer, 1, 'success', 'B');
        $this->makeResolution($job, $qDiffer, 2, 'success', 'B');
        $this->makeResolution($job, $qDiffer, 3, 'success', 'C');

        // Q3: R1=B, R2=B, R3=uncertain
        $qR3Uncertain = $this->makeQuestion($doc, 'unverified', ['A' => null, 'B' => null, 'C' => null, 'D' => null], 3);
        $this->makeResolution($job, $qR3Uncertain, 1, 'success', 'B');
        $this->makeResolution($job, $qR3Uncertain, 2, 'success', 'B');
        $this->makeResolution($job, $qR3Uncertain, 3, 'uncertain', null);

        $all = collect([$qAgree, $qDiffer, $qR3Uncertain]);
        $classification = $this->verifier->classifyQuestionsForVerification($all);
        $this->assertCount(3, $classification['scenario_b_ids']);

        $stats = $this->verifier->verifyJobQuestions($job, $all);
        // Re-ejecutar verificación para validar idempotencia (Prueba 9)
        $this->verifier->verifyJobQuestions($job, $all);

        $this->assertSame(1, $stats['verified_candidate_no_official_key']);
        $this->assertSame(2, $stats['requires_admin_review']);
        $this->assertSame(3, IqpeVerification::where('job_id', $job->id)->count());

        $vAgree = IqpeVerification::where('job_id', $job->id)->where('question_id', $qAgree->id)->first();
        $this->assertFalse($vAgree->has_official_key);
        $this->assertNull($vAgree->official_key_letter);
        $this->assertSame($r3Agree->id, $vAgree->resolution_3_id);
        $this->assertSame('B', $vAgree->resolution_3_letter);
        $this->assertSame('verified_candidate_no_official_key', $vAgree->verdict);
        $this->assertSame('verified_candidate', $vAgree->recommended_status);
        $this->assertTrue($vAgree->requires_admin_review, 'Sin clave oficial debe indicar explícitamente revisión/aprobación administrativa.');
        $this->assertStringContainsString('no representa certeza absoluta', strtolower($vAgree->discrepancy_details));

        $vDiffer = IqpeVerification::where('job_id', $job->id)->where('question_id', $qDiffer->id)->first();
        $this->assertSame('ai_disagreement', $vDiffer->verdict);
        $this->assertSame('requires_admin_review', $vDiffer->recommended_status);

        $vUnc = IqpeVerification::where('job_id', $job->id)->where('question_id', $qR3Uncertain->id)->first();
        $this->assertSame('inconclusive', $vUnc->verdict);
        $this->assertSame('requires_admin_review', $vUnc->recommended_status);
    }

    public function test_r3_executes_in_strictly_isolated_context_without_r1_r2_or_official_key(): void
    {
        $doc = $this->makeDocument();
        $job = $this->makeJob($doc);
        $q = $this->makeQuestion($doc, 'unverified', ['A' => null, 'B' => null, 'C' => null, 'D' => null]);

        // Simular que R1 y R2 ya existen en BD
        $this->makeResolution($job, $q, 1, 'success', 'B');
        $this->makeResolution($job, $q, 2, 'success', 'B');

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'status'                => 'success',
                            'solved_option_letter'  => 'B',
                            'solution_explanation'  => 'R3_AUTONOMA: 6 + 4 = 10.',
                            'visual_interpretation' => null,
                            'confidence_score'      => 0.99,
                        ]),
                    ]]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 195, 'candidatesTokenCount' => 42],
            ], 200),
        ]);

        /** @var GeminiQuestionSolverService $solver */
        $solver = $this->app->make(GeminiQuestionSolverService::class);
        $r3 = $solver->solveSingleQuestionRound($job->id, $q, 3);

        $this->assertSame(3, $r3->round_number);
        $this->assertSame('success', $r3->status);
        $this->assertSame('B', $r3->solved_option_letter);

        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);
        /** @var HttpClientRequest $req */
        $req = $recorded[0][0];
        $payloadText = json_encode($req->data());

        $this->assertStringNotContainsString('EXPLICACION_OFICIAL_PRIVADA', $payloadText);
        $this->assertStringNotContainsString('is_correct', $payloadText);
        $this->assertStringNotContainsString('Explicación ronda 1', $payloadText);
        $this->assertStringNotContainsString('Explicación ronda 2', $payloadText);
        $this->assertStringNotContainsString('R1', $payloadText);
        $this->assertStringNotContainsString('R2', $payloadText);
        $this->assertStringNotContainsString('iqpe_verifications', $payloadText);
        $this->assertNotSame($solver->getSystemPromptForRound(1), $solver->getSystemPromptForRound(3));
        $this->assertNotSame($solver->getSystemPromptForRound(2), $solver->getSystemPromptForRound(3));
    }
}

