<?php

namespace Tests\Unit;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeQualityAudit;
use App\Models\IqpeQuestionResolution;
use App\Models\IqpeVerification;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Models\Stimulus;
use App\Services\IqpeQualityAuditService;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class IqpeQualityAndDuplicateAuditTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    private string $validPngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeDoc(string $title = 'Documento Auditoría B5'): SourceDocument
    {
        return SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => $title,
            'storage_path' => 'documents/2026/b5.pdf',
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
            'status'             => 'auditing',
        ]);
    }

    private function makeQuestion(
        SourceDocument $doc,
        string $statement,
        ?array $options = null,
        array $overrides = []
    ): Question {
        $q = Question::create(array_merge([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 2,
            'source_document_id' => $doc->id,
            'order_in_group'     => 1,
            'statement'          => $statement,
            'key_status'         => 'verified',
            'status'             => 'draft',
        ], $overrides));

        $options ??= [
            ['letter' => 'A', 'text' => '10 unidades métricas', 'is_correct' => false],
            ['letter' => 'B', 'text' => '20 unidades métricas', 'is_correct' => true],
            ['letter' => 'C', 'text' => '30 unidades métricas', 'is_correct' => false],
            ['letter' => 'D', 'text' => '40 unidades métricas', 'is_correct' => false],
        ];

        foreach ($options as $opt) {
            QuestionOption::create([
                'id'                => (string) Str::uuid(),
                'question_id'       => $q->id,
                'option_letter'     => $opt['letter'],
                'option_text'       => $opt['text'],
                'option_image_path' => $opt['image_path'] ?? null,
                'is_correct'        => $opt['is_correct'] ?? null,
            ]);
        }

        return $q->fresh(['options', 'stimulus']);
    }

    private function attachCleanResolutionsAndVerification(IqpeDocumentJob $job, Question $q, string $letter = 'B'): void
    {
        $r1 = IqpeQuestionResolution::create([
            'id'                   => (string) Str::uuid(),
            'job_id'               => $job->id,
            'question_id'          => $q->id,
            'round_number'         => 1,
            'ai_model'             => 'gemini-3-flash-preview',
            'solved_option_letter' => $letter,
            'confidence_score'     => 0.98,
            'solution_explanation' => "Análisis detallado R1 que fundamenta la opción {$letter}.",
            'status'               => 'success',
        ]);
        $r2 = IqpeQuestionResolution::create([
            'id'                   => (string) Str::uuid(),
            'job_id'               => $job->id,
            'question_id'          => $q->id,
            'round_number'         => 2,
            'ai_model'             => 'gemini-3-flash-preview',
            'solved_option_letter' => $letter,
            'confidence_score'     => 0.97,
            'solution_explanation' => "Verificación por descarte R2 confirmando {$letter}.",
            'status'               => 'success',
        ]);
        IqpeVerification::create([
            'id'                    => (string) Str::uuid(),
            'job_id'                => $job->id,
            'question_id'           => $q->id,
            'has_official_key'      => true,
            'official_key_letter'   => $letter,
            'resolution_1_id'       => $r1->id,
            'resolution_1_letter'   => $letter,
            'resolution_2_id'       => $r2->id,
            'resolution_2_letter'   => $letter,
            'verdict'               => 'verified_candidate',
            'recommended_status'    => 'verified_candidate',
            'requires_admin_review' => false,
            'confidence_index'      => 0.975,
        ]);
    }

    public function test_audits_missing_statement_incomplete_options_missing_visual_and_incomplete_table(): void
    {
        $doc = $this->makeDoc();
        $job = $this->makeJob($doc);

        // 1. Pregunta sin enunciado
        $qEmptyStmt = $this->makeQuestion($doc, '', null, ['order_in_group' => 1]);

        // 2. Pregunta con opciones incompletas (solo 2 opciones y texto repetido)
        $qBadOpts = $this->makeQuestion(
            $doc,
            'En una fábrica se producen 100 piezas cada hora. ¿Cuántas piezas se producen en 4 horas?',
            [
                ['letter' => 'A', 'text' => '400 piezas', 'is_correct' => true],
                ['letter' => 'B', 'text' => '400 piezas', 'is_correct' => false],
            ],
            ['order_in_group' => 2]
        );

        // 3. Pregunta con recurso visual faltante en Storage
        $qMissingImg = $this->makeQuestion(
            $doc,
            'Observa la siguiente gráfica de velocidad contra tiempo. ¿En qué intervalo el móvil acelera?',
            null,
            ['order_in_group' => 3, 'question_image_path' => 'extracted/missing/grafica.png']
        );

        // 4. Pregunta con tabla incompleta (media_type=table sin contenido ni transcripción)
        $emptyTableStim = Stimulus::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 2,
            'source_document_id' => $doc->id,
            'title'              => 'Tabla de datos incompleta',
            'content_text'       => '',
            'media_type'         => 'table',
        ]);
        $qBadTable = $this->makeQuestion(
            $doc,
            'De acuerdo con la siguiente tabla de frecuencias, ¿cuál es la mediana del conjunto?',
            null,
            ['order_in_group' => 4, 'stimulus_id' => $emptyTableStim->id]
        );

        $storage = Mockery::mock(SupabaseStorageService::class);
        $storage->shouldReceive('exists')->with('icfes-media', 'extracted/missing/grafica.png')->andReturn(false);
        $this->app->instance(SupabaseStorageService::class, $storage);

        /** @var IqpeQualityAuditService $auditor */
        $auditor = $this->app->make(IqpeQualityAuditService::class);
        $stats = $auditor->auditJobQuestions($job, collect([$qEmptyStmt, $qBadOpts, $qMissingImg, $qBadTable]));

        $this->assertSame(4, $stats['total_audited']);
        $this->assertSame(0, $stats['ready_for_approval_count']);
        $this->assertSame(4, $stats['blocked_critical_count']);

        $a1 = IqpeQualityAudit::where('job_id', $job->id)->where('question_id', $qEmptyStmt->id)->first();
        $this->assertFalse($a1->passed_quality_check);
        $this->assertEquals(0.0, $a1->statement_integrity_score);
        $this->assertContains('empty_statement', array_column($a1->audit_warnings['findings'], 'type'));

        $a2 = IqpeQualityAudit::where('job_id', $job->id)->where('question_id', $qBadOpts->id)->first();
        $this->assertFalse($a2->passed_quality_check);
        $this->assertLessThan(0.5, $a2->options_integrity_score);
        $types2 = array_column($a2->audit_warnings['findings'], 'type');
        $this->assertContains('incomplete_options', $types2);
        $this->assertContains('duplicate_option_text', $types2);

        $a3 = IqpeQualityAudit::where('job_id', $job->id)->where('question_id', $qMissingImg->id)->first();
        $this->assertFalse($a3->passed_quality_check);
        $this->assertSame('unverified_media', $a3->media_integrity_status);
        $this->assertContains('missing_visual_resource', array_column($a3->audit_warnings['findings'], 'type'));

        $a4 = IqpeQualityAudit::where('job_id', $job->id)->where('question_id', $qBadTable->id)->first();
        $this->assertFalse($a4->passed_quality_check);
        $this->assertSame('unverified_media', $a4->media_integrity_status);
        $this->assertContains('incomplete_table_or_diagram', array_column($a4->audit_warnings['findings'], 'type'));
    }

    public function test_detects_exact_and_probable_duplicates_without_false_positives_on_topic_or_shared_stimulus(): void
    {
        $doc1 = $this->makeDoc('Cuadernillo Origen');
        $doc2 = $this->makeDoc('Cuadernillo Nuevo');
        $job = $this->makeJob($doc2);

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        // Pregunta base 1 (publicada)
        $pubExact = $this->makeQuestion(
            $doc1,
            'Un ciclista recorre una pista circular de 400 metros y completa 5 vueltas en 2 minutos. ¿Cuál es la distancia total recorrida?',
            [
                ['letter' => 'A', 'text' => '800 metros', 'is_correct' => false],
                ['letter' => 'B', 'text' => '2000 metros', 'is_correct' => true],
                ['letter' => 'C', 'text' => '1600 metros', 'is_correct' => false],
                ['letter' => 'D', 'text' => '400 metros', 'is_correct' => false],
            ],
            ['status' => 'published', 'order_in_group' => 1]
        );

        // Pregunta base 2 (en banco)
        $bankProb = $this->makeQuestion(
            $doc1,
            'Un automóvil viaja a una rapidez constante de 60 km/h durante 3 horas por una autopista. ¿Qué distancia recorre en total?',
            [
                ['letter' => 'A', 'text' => '120 km', 'is_correct' => false],
                ['letter' => 'B', 'text' => '180 km', 'is_correct' => true],
                ['letter' => 'C', 'text' => '200 km', 'is_correct' => false],
                ['letter' => 'D', 'text' => '240 km', 'is_correct' => false],
            ],
            ['status' => 'review', 'order_in_group' => 2]
        );

        // Estímulo compartido para dos preguntas legítimamente distintas
        $sharedStim = Stimulus::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 1,
            'source_document_id' => $doc2->id,
            'title'              => 'El anillo de Giges',
            'content_text'       => 'En La República, Platón narra la historia del pastor Giges, quien encontró un anillo que lo volvía invisible y cuestiona si alguien sería justo sin temor al castigo.',
            'media_type'         => 'none',
        ]);

        // 5 & 9: Duplicado exacto contra pregunta publicada (con prefijo numerado distinto)
        $qExactCopy = $this->makeQuestion(
            $doc2,
            '14. Un ciclista recorre una pista circular de 400 metros y completa 5 vueltas en 2 minutos. ¿Cuál es la distancia total recorrida?',
            [
                ['letter' => 'A', 'text' => '2000 metros', 'is_correct' => true],
                ['letter' => 'B', 'text' => '800 metros', 'is_correct' => false],
                ['letter' => 'C', 'text' => '400 metros', 'is_correct' => false],
                ['letter' => 'D', 'text' => '1600 metros', 'is_correct' => false],
            ],
            ['order_in_group' => 1]
        );
        $this->attachCleanResolutionsAndVerification($job, $qExactCopy, 'A');

        // 6: Duplicado probable (parafraseado con mismas opciones)
        $qProbableCopy = $this->makeQuestion(
            $doc2,
            'Un carro avanza con rapidez constante de 60 km/h durante 3 horas en carretera. ¿Cuál es la distancia total que recorre?',
            [
                ['letter' => 'A', 'text' => '120 km', 'is_correct' => false],
                ['letter' => 'B', 'text' => '180 km', 'is_correct' => true],
                ['letter' => 'C', 'text' => '200 km', 'is_correct' => false],
                ['letter' => 'D', 'text' => '240 km', 'is_correct' => false],
            ],
            ['order_in_group' => 2]
        );
        $this->attachCleanResolutionsAndVerification($job, $qProbableCopy, 'B');

        // 7: Mismo tema (Matemáticas / distancia-porcentaje), pero pregunta diferente
        $qSameTopicDifferent = $this->makeQuestion(
            $doc2,
            'En una tienda deportiva una bicicleta cuesta $900.000 y tiene descuento del 20%. ¿Cuánto dinero se ahorra el comprador?',
            [
                ['letter' => 'A', 'text' => '$150.000', 'is_correct' => false],
                ['letter' => 'B', 'text' => '$180.000', 'is_correct' => true],
                ['letter' => 'C', 'text' => '$200.000', 'is_correct' => false],
                ['letter' => 'D', 'text' => '$720.000', 'is_correct' => false],
            ],
            ['order_in_group' => 3]
        );
        $this->attachCleanResolutionsAndVerification($job, $qSameTopicDifferent, 'B');

        // 8: Dos preguntas distintas que comparten el mismo estímulo
        $qStim1 = $this->makeQuestion(
            $doc2,
            'Según el relato de Platón, ¿qué cuestionamiento filosófico ilustra el hallazgo del anillo de invisibilidad?',
            [
                ['letter' => 'A', 'text' => 'La existencia de mundos paralelos', 'is_correct' => false],
                ['letter' => 'B', 'text' => 'Si las personas actúan con justicia por convicción o por miedo al castigo', 'is_correct' => true],
                ['letter' => 'C', 'text' => 'La superioridad técnica de la metalurgia antigua', 'is_correct' => false],
                ['letter' => 'D', 'text' => 'El origen mitológico de los pastores griegos', 'is_correct' => false],
            ],
            ['subject_id' => 1, 'stimulus_id' => $sharedStim->id, 'order_in_group' => 4]
        );
        $this->attachCleanResolutionsAndVerification($job, $qStim1, 'B');

        $qStim2 = $this->makeQuestion(
            $doc2,
            'En la estructura argumentativa de la lectura, el personaje del pastor cumple la función de:',
            [
                ['letter' => 'A', 'text' => 'Refutar la existencia de las leyes estatales', 'is_correct' => false],
                ['letter' => 'B', 'text' => 'Servir como experimento mental para analizar la moral humana', 'is_correct' => true],
                ['letter' => 'C', 'text' => 'Describir las costumbres económicas de Atenas', 'is_correct' => false],
                ['letter' => 'D', 'text' => 'Narrar una biografía histórica verificada', 'is_correct' => false],
            ],
            ['subject_id' => 1, 'stimulus_id' => $sharedStim->id, 'order_in_group' => 5]
        );
        $this->attachCleanResolutionsAndVerification($job, $qStim2, 'B');

        /** @var IqpeQualityAuditService $auditor */
        $auditor = $this->app->make(IqpeQualityAuditService::class);
        $targets = collect([$qExactCopy, $qProbableCopy, $qSameTopicDifferent, $qStim1, $qStim2]);
        $stats = $auditor->auditJobQuestions($job, $targets);

        // Re-ejecutar auditoría para verificar idempotencia sin duplicar hallazgos (Prueba 10)
        $auditor->auditJobQuestions($job, $targets);
        $this->assertSame(5, IqpeQualityAudit::where('job_id', $job->id)->count());

        // Duplicado exacto contra pregunta publicada
        $aExact = IqpeQualityAudit::where('job_id', $job->id)->where('question_id', $qExactCopy->id)->first();
        $this->assertTrue($aExact->duplicate_risk_flag);
        $this->assertSame($pubExact->id, $aExact->duplicate_candidate_id);
        $this->assertSame('exact_duplicate', $aExact->audit_warnings['duplicate_assessment']['classification']);
        $this->assertTrue($aExact->audit_warnings['duplicate_assessment']['involves_published_question']);
        $this->assertFalse($aExact->passed_quality_check);
        // Al tener duplicado contra publicada, iqpe_verifications debe degradarse a requires_admin_review
        $vExact = IqpeVerification::where('job_id', $job->id)->where('question_id', $qExactCopy->id)->first();
        $this->assertSame('requires_admin_review', $vExact->recommended_status);
        $this->assertTrue($vExact->requires_admin_review);

        // La pregunta publicada NO debe haber sido eliminada ni alterada (Prueba 9 & 11)
        $pubFresh = Question::find($pubExact->id);
        $this->assertNotNull($pubFresh);
        $this->assertSame('published', $pubFresh->status);

        // Duplicado probable
        $aProb = IqpeQualityAudit::where('job_id', $job->id)->where('question_id', $qProbableCopy->id)->first();
        $this->assertTrue($aProb->duplicate_risk_flag);
        $this->assertSame($bankProb->id, $aProb->duplicate_candidate_id);
        $this->assertSame('probable_duplicate', $aProb->audit_warnings['duplicate_assessment']['classification']);
        $this->assertFalse($aProb->passed_quality_check);

        // Mismo tema pero pregunta distinta -> independiente y aprobable
        $aTopic = IqpeQualityAudit::where('job_id', $job->id)->where('question_id', $qSameTopicDifferent->id)->first();
        $this->assertFalse($aTopic->duplicate_risk_flag);
        $this->assertNull($aTopic->duplicate_candidate_id);
        $this->assertSame('independent', $aTopic->audit_warnings['duplicate_assessment']['classification']);
        $this->assertTrue($aTopic->passed_quality_check);

        // Estímulo compartido -> relacionadas, NO duplicadas, ambas pasan calidad
        $aStim1 = IqpeQualityAudit::where('job_id', $job->id)->where('question_id', $qStim1->id)->first();
        $aStim2 = IqpeQualityAudit::where('job_id', $job->id)->where('question_id', $qStim2->id)->first();
        $this->assertFalse($aStim1->duplicate_risk_flag);
        $this->assertFalse($aStim2->duplicate_risk_flag);
        $this->assertSame('related_question', $aStim1->audit_warnings['duplicate_assessment']['classification']);
        $this->assertSame('related_question', $aStim2->audit_warnings['duplicate_assessment']['classification']);
        $this->assertTrue($aStim1->passed_quality_check);
        $this->assertTrue($aStim2->passed_quality_check);

        $this->assertSame(3, $stats['ready_for_approval_count']);
        $this->assertSame(2, $stats['duplicate_risk_count']);
    }
}
