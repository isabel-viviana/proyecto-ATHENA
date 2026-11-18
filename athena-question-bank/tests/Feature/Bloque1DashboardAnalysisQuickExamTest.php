<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Models\Topic;
use App\Services\StudentTokenService;
use Illuminate\Support\Str;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class Bloque1DashboardAnalysisQuickExamTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    protected string $studentToken;
    protected string $otherStudentToken;
    protected string $docId;
    protected array $questionsBySubject = [];
    protected array $optionsByQuestion = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();

        $this->studentToken = StudentTokenService::generateToken([
            'id'    => '501',
            'name'  => 'Estudiante Bloque Uno',
            'email' => 'bloque1@athena.edu.co',
            'role'  => 'student',
        ]);

        $this->otherStudentToken = StudentTokenService::generateToken([
            'id'    => '502',
            'name'  => 'Estudiante Dos',
            'email' => 'bloque1_otro@athena.edu.co',
            'role'  => 'student',
        ]);

        $this->docId = (string) Str::uuid();
        SourceDocument::create([
            'id'             => $this->docId,
            'title'          => 'Cuadernillo Oficial Multiárea ICFES',
            'storage_path'   => 'documents/2026/cuadernillo_multiarea.pdf',
            'exam_type'      => 'Saber 11',
            'total_pages'    => 10,
            'has_answer_key' => true,
            'status'         => 'extracted',
            'created_at'     => now(),
        ]);

        // Crear temas reales en las 5 áreas ICFES
        $topics = [
            1 => ['id' => 101, 'name' => 'Comprensión e Interpretación Textual'],
            2 => ['id' => 102, 'name' => 'Razonamiento Cuantitativo y Álgebra'],
            3 => ['id' => 103, 'name' => 'Uso Comprensivo del Conocimiento Científico'],
            4 => ['id' => 104, 'name' => 'Pensamiento Social y Ciudadano'],
            5 => ['id' => 105, 'name' => 'Lectura y Gramática en Inglés'],
        ];

        foreach ($topics as $subjectId => $t) {
            Topic::create([
                'id'         => $t['id'],
                'subject_id' => $subjectId,
                'name'       => $t['name'],
            ]);

            // Crear 2 preguntas publicadas por cada una de las 5 áreas ICFES
            for ($i = 1; $i <= 2; $i++) {
                $qId = $this->seedQuestion(
                    $subjectId,
                    $t['id'],
                    $i,
                    "Pregunta {$i} del área {$subjectId} ({$t['name']})",
                    'B'
                );
                $this->questionsBySubject[$subjectId][] = $qId;
            }
        }
    }

    protected function seedQuestion(
        int $subjectId,
        int $topicId,
        int $order,
        string $statement,
        string $correctLetter
    ): string {
        $qId = (string) Str::uuid();
        Question::create([
            'id'                 => $qId,
            'root_question_id'   => $qId,
            'version'            => 1,
            'is_current_version' => true,
            'subject_id'         => $subjectId,
            'topic_id'           => $topicId,
            'source_document_id' => $this->docId,
            'order_in_group'     => $order,
            'statement'          => $statement,
            'difficulty'         => 'medium',
            'explanation'        => "Justificación oficial para {$statement}. Respuesta: {$correctLetter}.",
            'key_status'         => 'verified',
            'status'             => 'published',
        ]);

        $this->optionsByQuestion[$qId] = [];
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $optId = (string) Str::uuid();
            QuestionOption::create([
                'id'            => $optId,
                'question_id'   => $qId,
                'option_letter' => $letter,
                'option_text'   => "Opción {$letter} para {$statement}",
                'is_correct'    => ($letter === $correctLetter),
                'created_at'    => now(),
            ]);
            $this->optionsByQuestion[$qId][$letter] = $optId;
        }

        return $qId;
    }

    /**
     * 1. Verifica estado vacío e insuficiencia de datos antes de realizar actividades.
     */
    public function test_empty_dashboard_and_insufficient_data_for_diagnosis(): void
    {
        $res = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->getJson('/api/v1/student/progress');

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_answered', 0)
            ->assertJsonPath('data.summary.completed_exams_count', 0)
            ->assertJsonPath('data.summary.practice_sessions_count', 0)
            ->assertJsonPath('data.academic_analysis.has_sufficient_data', false)
            ->assertJsonPath('data.weak_topics', [])
            ->assertJsonPath('data.recent_exams', []);
    }

    /**
     * 2. Verifica que las prácticas actualizan las estadísticas reales y el análisis diagnóstico
     *    por área y por tema sin datos estáticos ni inventados.
     */
    public function test_practice_answers_feed_real_dashboard_and_diagnostic_analysis(): void
    {
        // Responder 2 preguntas correctas en Lectura Crítica (subject 1, topic 101)
        foreach ($this->questionsBySubject[1] as $qId) {
            $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
                ->postJson("/api/v1/student/questions/{$qId}/answer", [
                    'selected_option_id' => $this->optionsByQuestion[$qId]['B'],
                    'practice_mode'      => 'practice',
                    'time_spent_seconds' => 30,
                ])->assertStatus(200)
                ->assertJsonPath('data.is_correct', true);
        }

        // Responder 2 preguntas incorrectas en Matemáticas (subject 2, topic 102)
        foreach ($this->questionsBySubject[2] as $qId) {
            $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
                ->postJson("/api/v1/student/questions/{$qId}/answer", [
                    'selected_option_id' => $this->optionsByQuestion[$qId]['A'],
                    'practice_mode'      => 'practice',
                    'time_spent_seconds' => 45,
                ])->assertStatus(200)
                ->assertJsonPath('data.is_correct', false);
        }

        $progressRes = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->getJson('/api/v1/student/progress');

        $progressRes->assertStatus(200)
            ->assertJsonPath('data.summary.total_answered', 4)
            ->assertJsonPath('data.summary.correct_count', 2)
            ->assertJsonPath('data.summary.incorrect_count', 2)
            ->assertJsonPath('data.summary.accuracy', 50)
            ->assertJsonPath('data.summary.practice_questions_answered', 4)
            ->assertJsonPath('data.summary.practice_sessions_count', 1)
            ->assertJsonPath('data.academic_analysis.has_sufficient_data', true)
            ->assertJsonPath('data.academic_analysis.strongest_area.name', 'Lectura Crítica')
            ->assertJsonPath('data.academic_analysis.strongest_area.accuracy', 100)
            ->assertJsonPath('data.academic_analysis.needs_practice_area.name', 'Matemáticas')
            ->assertJsonPath('data.academic_analysis.needs_practice_area.accuracy', 0);

        // Verificar que weak_topics contiene el tema real de Matemáticas con 100% de error
        $weakTopics = $progressRes->json('data.weak_topics');
        $this->assertCount(1, $weakTopics);
        $this->assertSame('Razonamiento Cuantitativo y Álgebra', $weakTopics[0]['topic_name']);
        $this->assertEquals(100, $weakTopics[0]['error_rate']);

        // Verificar que strong_topics contiene el tema real de Lectura Crítica con 100% de acierto
        $strongTopics = $progressRes->json('data.strong_topics');
        $this->assertCount(1, $strongTopics);
        $this->assertSame('Comprensión e Interpretación Textual', $strongTopics[0]['topic_name']);
        $this->assertEquals(100, $strongTopics[0]['accuracy']);

        // Verificar recomendaciones de estudio basadas en datos reales
        $recommendations = $progressRes->json('data.academic_analysis.study_recommendations');
        $this->assertNotEmpty($recommendations);
        $this->assertStringContainsString('Matemáticas', $recommendations[0]['title']);
    }

    /**
     * 3. Verifica el flujo completo E2E del Mini Simulacro Rápido (/mock/quick):
     *    - Inicia sin error HTTP 422 (soportando payload legacy y payload actual con las 5 áreas ICFES).
     *    - Selecciona preguntas válidas de las 5 áreas.
     *    - Guarda respuestas.
     *    - Finaliza el intento y calcula resultados.
     *    - Actualiza el historial y el Dashboard del estudiante.
     */
    public function test_quick_mock_exam_end_to_end_flow_and_dashboard_reflection(): void
    {
        // Iniciar Mini Simulacro Rápido enviando el payload multiárea de las 5 áreas ICFES
        $startRes = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->postJson('/api/v1/student/exams/start', [
                'exam_type'   => 'quick',
                'difficulty'  => 'medium',
                'subject_ids' => [1, 2, 3, 4, 5],
            ]);

        $startRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attempt.mode', 'quick')
            ->assertJsonPath('data.attempt.questions_count', 5)
            ->assertJsonPath('data.attempt.status', 'in_progress');

        $attemptId = $startRes->json('data.attempt.id');
        $questions = $startRes->json('data.questions');
        $this->assertCount(5, $questions);

        // Verificar que seleccionó 1 pregunta de cada una de las 5 áreas ICFES
        $selectedSubjectIds = collect($questions)->pluck('subject.id')->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4, 5], $selectedSubjectIds);

        // Guardar 4 respuestas (3 correctas con 'B' y 1 incorrecta con 'D') y dejar la 5ª omitida
        for ($i = 0; $i < 4; $i++) {
            $qId = $questions[$i]['id'];
            $chosenLetter = ($i < 3) ? 'B' : 'D';
            $chosenOptionId = $this->optionsByQuestion[$qId][$chosenLetter];

            $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
                ->postJson("/api/v1/student/exams/{$attemptId}/answers", [
                    'question_id'            => $qId,
                    'selected_option_id'     => $chosenOptionId,
                    'selected_option_letter' => $chosenLetter,
                    'time_spent_seconds'     => 25,
                    'flagged'                => ($i === 3),
                ])->assertStatus(200)
                ->assertJsonPath('success', true);
        }

        // Consultar el intento en curso (show) y verificar que devuelve answers y answers_map
        $showRes = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->getJson("/api/v1/student/exams/{$attemptId}");
        $showRes->assertStatus(200);
        $this->assertCount(5, $showRes->json('data.answers_map'));
        $this->assertCount(5, $showRes->json('data.answers'));

        // Finalizar el Mini Simulacro Rápido
        $finishRes = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/finish");

        $finishRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attempt.status', 'completed')
            ->assertJsonPath('data.attempt.correct_answers', 3)
            ->assertJsonPath('data.attempt.incorrect_answers', 1)
            ->assertJsonPath('data.attempt.omitted_answers', 1)
            ->assertJsonPath('data.attempt.score', 30)
            ->assertJsonPath('data.attempt.accuracy', 60);

        // Verificar que results() incluye el desglose por área (subject_performance)
        $resultsRes = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->getJson("/api/v1/student/exams/{$attemptId}/results");
        $resultsRes->assertStatus(200);
        $this->assertCount(5, $resultsRes->json('data.attempt.subject_performance'));
        $this->assertCount(5, $resultsRes->json('data.questions_detail'));

        // Verificar que el historial se actualiza
        $historyRes = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->getJson('/api/v1/student/exams/history');
        $historyRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attemptId)
            ->assertJsonPath('data.0.mode', 'quick')
            ->assertJsonPath('data.0.score', 30);

        // Verificar que el Dashboard (/api/v1/student/progress) refleja la nueva actividad y el simulacro completado
        $dashboardRes = $this->withHeaders(['Authorization' => "Bearer {$this->studentToken}"])
            ->getJson('/api/v1/student/progress');

        $dashboardRes->assertStatus(200)
            ->assertJsonPath('data.summary.completed_exams_count', 1)
            ->assertJsonPath('data.summary.total_answered', 4)
            ->assertJsonPath('data.summary.correct_count', 3)
            ->assertJsonPath('data.summary.incorrect_count', 1)
            ->assertJsonPath('data.summary.omitted_count', 1)
            ->assertJsonPath('data.resumen_general.total_simulacros_completados', 1)
            ->assertJsonPath('data.recent_exams.0.id', $attemptId)
            ->assertJsonPath('data.ultimos_simulacros.0.id', $attemptId);

        // Verificar aislamiento: otro estudiante no ve este simulacro en su Dashboard
        $otherDashboard = $this->withHeaders(['Authorization' => "Bearer {$this->otherStudentToken}"])
            ->getJson('/api/v1/student/progress');
        $otherDashboard->assertStatus(200)
            ->assertJsonPath('data.summary.completed_exams_count', 0)
            ->assertJsonPath('data.summary.total_answered', 0);
    }
}
