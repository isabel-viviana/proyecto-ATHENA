<?php

namespace Tests\Unit;

use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\IqpeQuestionResolution;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Models\Stimulus;
use App\Services\GeminiQuestionSolverService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class GeminiQuestionSolverServiceTest extends TestCase
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

    private function makeDocument(string $status = 'extracted'): SourceDocument
    {
        return SourceDocument::create([
            'id'           => (string) Str::uuid(),
            'title'        => 'Cuadernillo Prueba Bloque 3',
            'storage_path' => 'documents/2026/test.pdf',
            'year'         => 2026,
            'exam_type'    => 'Saber 11',
            'status'       => $status,
        ]);
    }

    private function makeQuestionWithOptions(SourceDocument $doc, array $overrides = [], ?array $optionsOverride = null): Question
    {
        $q = Question::create(array_merge([
            'id'                  => (string) Str::uuid(),
            'subject_id'          => 2,
            'source_document_id'  => $doc->id,
            'order_in_group'      => 1,
            'statement'           => 'Un estudiante recorre 12 km en 2 horas a velocidad constante. ¿Cuál es su rapidez?',
            'difficulty'          => 'medium',
            'explanation'         => 'SECRETO_OFICIAL_EXPLICACION_NO_FILTRAR',
            'key_status'          => 'verified',
            'extraction_warnings' => 'Discrepancia en clave: marcada A, oficial en PDF es B.',
            'status'              => 'draft',
        ], $overrides));

        $defaultOptions = $optionsOverride ?? [
            ['letter' => 'A', 'text' => '4 km/h', 'is_correct' => false],
            ['letter' => 'B', 'text' => '6 km/h', 'is_correct' => true],
            ['letter' => 'C', 'text' => '12 km/h', 'is_correct' => false],
            ['letter' => 'D', 'text' => '24 km/h', 'is_correct' => false],
        ];

        foreach ($defaultOptions as $opt) {
            QuestionOption::create([
                'id'                => (string) Str::uuid(),
                'question_id'       => $q->id,
                'option_letter'     => $opt['letter'],
                'option_text'       => $opt['text'],
                'option_image_path' => $opt['image_path'] ?? null,
                'is_correct'        => $opt['is_correct'] ?? null,
            ]);
        }

        return $q;
    }

    public function test_r1_and_r2_execute_independently_without_official_key_or_cross_contamination(): void
    {
        $doc = $this->makeDocument();
        $q = $this->makeQuestionWithOptions($doc);
        $job = IqpeDocumentJob::create([
            'id'                 => (string) Str::uuid(),
            'source_document_id' => $doc->id,
            'run_number'         => 1,
            'status'             => 'solving_round_1',
        ]);

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [
                            ['text' => 'RAZONAMIENTO_PRIVADO_NO_GUARDAR', 'thought' => true],
                            ['text' => json_encode([
                                'status'                => 'success',
                                'solved_option_letter'  => 'B',
                                'solution_explanation'  => 'R1_EXPLICACION_UNICA: 12 / 2 = 6 km/h.',
                                'visual_interpretation' => null,
                                'confidence_score'      => 0.98,
                            ])],
                        ]],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => [
                        'promptTokenCount'     => 210,
                        'candidatesTokenCount' => 55,
                    ],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => json_encode([
                                'status'                => 'success',
                                'solved_option_letter'  => 'B',
                                'solution_explanation'  => 'R2_EXPLICACION_AUTONOMA: Se divide distancia entre tiempo.',
                                'visual_interpretation' => null,
                                'confidence_score'      => 0.96,
                            ]),
                        ]]],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => [
                        'promptTokenCount'     => 230,
                        'candidatesTokenCount' => 62,
                    ],
                ], 200),
        ]);

        $solver = $this->app->make(GeminiQuestionSolverService::class);

        // Activar query log para confirmar que NUNCA se consulta question_options.is_correct
        DB::connection('question_bank')->flushQueryLog();
        DB::connection('question_bank')->enableQueryLog();

        $freshQ = Question::select([
            'id', 'subject_id', 'stimulus_id', 'source_document_id',
            'order_in_group', 'statement', 'question_image_path',
            'source_page', 'visual_transcription', 'visual_description',
        ])->find($q->id);

        $r1Stats = $solver->solveBatchForJob($job, collect([$freshQ]), 1, 1);
        $r2Stats = $solver->solveBatchForJob($job, collect([$freshQ]), 2, 1);

        $queries = DB::connection('question_bank')->getQueryLog();
        DB::connection('question_bank')->disableQueryLog();

        $optionQueries = array_filter($queries, fn ($entry) => str_contains($entry['query'], 'question_options'));
        $this->assertNotEmpty($optionQueries);
        foreach ($optionQueries as $oq) {
            $this->assertStringNotContainsString('is_correct', $oq['query']);
            $this->assertStringNotContainsString('select *', strtolower($oq['query']));
        }

        $this->assertSame(1, $r1Stats['success']);
        $this->assertSame(1, $r2Stats['success']);

        $r1 = IqpeQuestionResolution::where('job_id', $job->id)->where('question_id', $q->id)->where('round_number', 1)->first();
        $r2 = IqpeQuestionResolution::where('job_id', $job->id)->where('question_id', $q->id)->where('round_number', 2)->first();

        $this->assertNotNull($r1);
        $this->assertNotNull($r2);
        $this->assertNotSame($r1->id, $r2->id);
        $this->assertSame('B', $r1->solved_option_letter);
        $this->assertSame('B', $r2->solved_option_letter);
        $this->assertSame('gemini-3-flash-preview', $r1->ai_model);
        $this->assertSame(210, $r1->prompt_tokens);
        $this->assertSame(55, $r1->completion_tokens);
        $this->assertGreaterThan(0, $r1->latency_ms);
        $this->assertStringNotContainsString('RAZONAMIENTO_PRIVADO_NO_GUARDAR', $r1->solution_explanation);
        $this->assertStringNotContainsString('RAZONAMIENTO_PRIVADO_NO_GUARDAR', json_encode($r1->raw_metadata));

        // Verificar aislamiento estricto entre peticiones HTTP R1 y R2
        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);

        /** @var HttpClientRequest $req1 */
        $req1 = $recorded[0][0];
        /** @var HttpClientRequest $req2 */
        $req2 = $recorded[1][0];

        $body1 = json_encode($req1->data());
        $body2 = json_encode($req2->data());

        // Ninguna petición recibe clave oficial, explicación oficial ni warnings con discrepancia
        foreach ([$body1, $body2] as $payloadStr) {
            $this->assertStringNotContainsString('is_correct', $payloadStr);
            $this->assertStringNotContainsString('SECRETO_OFICIAL_EXPLICACION_NO_FILTRAR', $payloadStr);
            $this->assertStringNotContainsString('Discrepancia en clave', $payloadStr);
            $this->assertStringNotContainsString('key_status', $payloadStr);
        }

        // R2 no recibe la respuesta ni explicación de R1, ni referencias a validar/confirmar R1
        $this->assertStringNotContainsString('R1_EXPLICACION_UNICA', $body2);
        $this->assertStringNotContainsString('R1', $body2);
        $this->assertStringNotContainsString('confirma', strtolower($body2));
        $this->assertNotSame(
            $solver->getSystemPromptForRound(1),
            $solver->getSystemPromptForRound(2)
        );
    }

    public function test_visual_questions_send_real_image_bytes_as_inline_data(): void
    {
        $doc = $this->makeDocument();
        $stimulus = Stimulus::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 1,
            'source_document_id' => $doc->id,
            'title'              => 'Historieta',
            'content_text'       => 'Observa la imagen del puesto de limonada.',
            'media_type'         => 'image',
            'media_path'         => 'extracted/doc/comic.png',
        ]);

        $q = $this->makeQuestionWithOptions($doc, [
            'subject_id'          => 1,
            'stimulus_id'         => $stimulus->id,
            'statement'           => '¿Qué precio aparece en el letrero de la historieta?',
            'question_image_path' => 'extracted/doc/q_extra.png',
        ]);

        $job = IqpeDocumentJob::create([
            'id'                 => (string) Str::uuid(),
            'source_document_id' => $doc->id,
            'run_number'         => 1,
            'status'             => 'solving_round_1',
        ]);

        $pngBytes = base64_decode($this->validPngBase64);
        $storage = Mockery::mock(SupabaseStorageService::class);
        $storage->shouldReceive('exists')->with('icfes-media', 'extracted/doc/comic.png')->andReturn(true);
        $storage->shouldReceive('get')->with('icfes-media', 'extracted/doc/comic.png')->andReturn($pngBytes);
        $storage->shouldReceive('exists')->with('icfes-media', 'extracted/doc/q_extra.png')->andReturn(true);
        $storage->shouldReceive('get')->with('icfes-media', 'extracted/doc/q_extra.png')->andReturn($pngBytes);
        $this->app->instance(SupabaseStorageService::class, $storage);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'status'                => 'success',
                            'solved_option_letter'  => 'B',
                            'solution_explanation'  => 'En la imagen se observa el cartel.',
                            'visual_interpretation' => 'Cartel visible con precio.',
                            'confidence_score'      => 0.92,
                        ]),
                    ]]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 450, 'candidatesTokenCount' => 80],
            ], 200),
        ]);

        $solver = $this->app->make(GeminiQuestionSolverService::class);
        $res = $solver->solveSingleQuestionRound($job->id, $q, 1);

        $this->assertSame('success', $res->status);
        $this->assertSame('Cartel visible con precio.', $res->visual_interpretation);

        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);
        $parts = $recorded[0][0]->data()['contents'][0]['parts'];

        $inlineParts = array_values(array_filter($parts, fn ($p) => isset($p['inlineData'])));
        $this->assertCount(2, $inlineParts);
        $this->assertSame('image/png', $inlineParts[0]['inlineData']['mimeType']);
        $this->assertSame($this->validPngBase64, $inlineParts[0]['inlineData']['data']);
    }

    public function test_incomplete_or_unreadable_questions_are_marked_uncertain_for_review_without_calling_gemini(): void
    {
        $doc = $this->makeDocument();

        // Caso 1: Imagen faltante en Storage
        $qMissingImg = $this->makeQuestionWithOptions($doc, [
            'order_in_group'      => 1,
            'statement'           => 'Observa la siguiente gráfica y determina la tendencia.',
            'question_image_path' => 'extracted/doc/missing.png',
        ]);

        // Caso 2: Bytes corruptos (no es imagen válida)
        $qCorruptImg = $this->makeQuestionWithOptions($doc, [
            'order_in_group'      => 2,
            'statement'           => 'Analiza la figura geométrica adjunta.',
            'question_image_path' => 'extracted/doc/corrupt.png',
        ]);

        // Caso 3: Estímulo declara tabla pero no tiene contenido ni imagen
        $emptyTableStim = Stimulus::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 2,
            'source_document_id' => $doc->id,
            'title'              => 'Tabla vacía',
            'content_text'       => '',
            'media_type'         => 'table',
        ]);
        $qMissingTable = $this->makeQuestionWithOptions($doc, [
            'order_in_group' => 3,
            'stimulus_id'    => $emptyTableStim->id,
            'statement'      => 'De acuerdo con la tabla, ¿cuál fue el mayor valor?',
        ]);

        // Caso 4: Menos de 4 opciones (incompleta)
        $qFewOptions = $this->makeQuestionWithOptions($doc, [
            'order_in_group' => 4,
            'statement'      => '¿Cuál es el resultado de 2 + 2?',
        ], [
            ['letter' => 'A', 'text' => '3', 'is_correct' => false],
            ['letter' => 'B', 'text' => '4', 'is_correct' => true],
        ]);

        // Caso 5: Opción placeholder sin imagen asociada
        $qPlaceholderOpt = $this->makeQuestionWithOptions($doc, [
            'order_in_group' => 5,
            'statement'      => 'Selecciona la representación correcta de la función.',
        ], [
            ['letter' => 'A', 'text' => 'Gráfica A', 'is_correct' => false],
            ['letter' => 'B', 'text' => 'Gráfica B', 'is_correct' => true],
            ['letter' => 'C', 'text' => 'Gráfica C', 'is_correct' => false],
            ['letter' => 'D', 'text' => 'Gráfica D', 'is_correct' => false],
        ]);

        $job = IqpeDocumentJob::create([
            'id'                 => (string) Str::uuid(),
            'source_document_id' => $doc->id,
            'run_number'         => 1,
            'status'             => 'solving_round_1',
        ]);

        $storage = Mockery::mock(SupabaseStorageService::class);
        $storage->shouldReceive('exists')->with('icfes-media', 'extracted/doc/missing.png')->andReturn(false);
        $storage->shouldReceive('exists')->with('icfes-media', 'extracted/doc/corrupt.png')->andReturn(true);
        $storage->shouldReceive('get')->with('icfes-media', 'extracted/doc/corrupt.png')->andReturn('not-an-image-bytes');
        $this->app->instance(SupabaseStorageService::class, $storage);

        Http::fake();

        $solver = $this->app->make(GeminiQuestionSolverService::class);
        $questions = collect([$qMissingImg, $qCorruptImg, $qMissingTable, $qFewOptions, $qPlaceholderOpt]);
        $stats = $solver->solveBatchForJob($job, $questions, 1, 1);

        // Ninguno de los 5 reactivos incompletos/rotos debe invocar a Gemini
        Http::assertNothingSent();
        $this->assertSame(5, $stats['uncertain']);
        $this->assertSame(0, $stats['success']);
        $this->assertSame(0, $stats['failed']);

        foreach ($questions as $q) {
            $res = IqpeQuestionResolution::where('job_id', $job->id)->where('question_id', $q->id)->first();
            $this->assertNotNull($res);
            $this->assertSame('uncertain', $res->status);
            $this->assertNull($res->solved_option_letter);
            $this->assertTrue((bool) ($res->raw_metadata['requires_review'] ?? false));
        }

        $this->assertSame(5, IqpeExecutionLog::where('job_id', $job->id)->where('level', 'warning')->count());
    }

    public function test_daily_quota_429_switches_to_fallback_without_sleeping_and_timeouts_record_error(): void
    {
        $doc = $this->makeDocument();
        $q1 = $this->makeQuestionWithOptions($doc, ['order_in_group' => 1]);
        $q2 = $this->makeQuestionWithOptions($doc, [
            'order_in_group' => 2,
            'statement'      => 'Calcula el perímetro de un cuadrado de lado 5 cm.',
        ]);
        $job = IqpeDocumentJob::create([
            'id'                 => (string) Str::uuid(),
            'source_document_id' => $doc->id,
            'run_number'         => 1,
            'status'             => 'solving_round_1',
        ]);

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        Http::fake(function (HttpClientRequest $request) {
            $url = $request->url();
            $bodyStr = json_encode($request->data());

            if (str_contains($bodyStr, 'cuadrado de lado 5 cm')) {
                throw new ConnectionException('Simulated network timeout');
            }

            // q1: primario devuelve 429 cuota diaria -> cambia inmediatamente a fallback gemini-3.1-flash-lite
            if (str_contains($url, 'gemini-3-flash-preview')) {
                return Http::response([
                    'error' => [
                        'code'    => 429,
                        'message' => 'Generate content requests per day limit exceeded on Free Tier.',
                        'details' => [[
                            'retryDelay' => '55000s',
                            'violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']],
                        ]],
                    ],
                ], 429);
            }

            if (str_contains($url, 'gemini-3.1-flash-lite')) {
                return Http::response([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => json_encode([
                                'status'                => 'success',
                                'solved_option_letter'  => 'B',
                                'solution_explanation'  => 'Resuelto con modelo de respaldo.',
                                'visual_interpretation' => null,
                                'confidence_score'      => 0.9,
                            ]),
                        ]]],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 40],
                ], 200);
            }

            return Http::response([], 500);
        });

        $solver = $this->app->make(GeminiQuestionSolverService::class);
        $res1 = $solver->solveSingleQuestionRound($job->id, $q1, 1);

        $this->assertSame('success', $res1->status);
        $this->assertSame('gemini-3.1-flash-lite', $res1->ai_model);
        $this->assertSame('B', $res1->solved_option_letter);

        $res2 = $solver->solveSingleQuestionRound($job->id, $q2, 1);
        $this->assertSame('timeout', $res2->status);
        $this->assertNull($res2->solved_option_letter);
        $this->assertNotNull($res2->latency_ms);
        $this->assertArrayHasKey('error', $res2->raw_metadata);
        $this->assertStringContainsString('Simulated network timeout', $res2->raw_metadata['error']);
    }

    public function test_controlled_concurrency_batch_handles_fifty_plus_questions_for_r1_and_r2(): void
    {
        $doc = $this->makeDocument();
        $questions = collect();
        for ($i = 1; $i <= 52; $i++) {
            $questions->push($this->makeQuestionWithOptions($doc, [
                'order_in_group' => $i,
                'statement'      => "Pregunta #{$i} de matemáticas sobre proporcionalidad directa e inversa.",
            ]));
        }

        $job = IqpeDocumentJob::create([
            'id'                 => (string) Str::uuid(),
            'source_document_id' => $doc->id,
            'run_number'         => 1,
            'status'             => 'solving_round_1',
        ]);

        $storage = Mockery::mock(SupabaseStorageService::class);
        $this->app->instance(SupabaseStorageService::class, $storage);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'status'                => 'success',
                            'solved_option_letter'  => 'B',
                            'solution_explanation'  => 'Resolución controlada en lote.',
                            'visual_interpretation' => null,
                            'confidence_score'      => 0.95,
                        ]),
                    ]]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 150, 'candidatesTokenCount' => 35],
            ], 200),
        ]);

        $solver = $this->app->make(GeminiQuestionSolverService::class);
        $r1Stats = $solver->solveBatchForJob($job, $questions, 1, 4);
        $r2Stats = $solver->solveBatchForJob($job, $questions, 2, 4);

        $this->assertSame(52, $r1Stats['success']);
        $this->assertSame(52, $r2Stats['success']);
        $this->assertSame(0, $r1Stats['failed']);
        $this->assertSame(0, $r2Stats['failed']);
        $this->assertSame(104, IqpeQuestionResolution::where('job_id', $job->id)->count());
        Http::assertSentCount(104);
    }
}
