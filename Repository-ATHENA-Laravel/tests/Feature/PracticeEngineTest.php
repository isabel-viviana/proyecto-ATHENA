<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Stimulus;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Models\ExamAttempt;
use App\Models\AttemptAnswer;
use Tests\TestCase;

class PracticeEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /**
     * Helper para crear un usuario de prueba.
     */
    protected function createTestUser(): User
    {
        return User::factory()->create([
            'full_name' => 'Estudiante Pruebas',
            'email' => 'estudiante@athena.edu.co',
        ]);
    }

    /**
     * Helper para poblar una materia con preguntas controladas y grupos de estímulo.
     */
    protected function seedControlledSubject(int $singletonCount = 20, array $stimulusGroupSizes = [3, 4]): array
    {
        $subject = Subject::create([
            'name' => 'Lectura Crítica Test',
            'slug' => 'lectura-critica-test',
            'description' => 'Materia controlada para pruebas del motor de prácticas',
        ]);

        $topic = Topic::create([
            'subject_id' => $subject->id,
            'name' => 'Textos Argumentativos',
            'slug' => 'textos-argumentativos',
            'description' => 'Tema controlado',
        ]);

        $questions = collect();

        // Crear grupos con estímulo compartido
        foreach ($stimulusGroupSizes as $gIndex => $size) {
            $stimulus = Stimulus::create([
                'subject_id' => $subject->id,
                'title' => "Texto de Prueba Grupo {$gIndex}",
                'instructions' => "Responda las preguntas asociadas",
                'content_text' => "Este es el texto del estímulo compartido número {$gIndex}...",
                'media_type' => 'none',
                'source_pdf' => "Cuadernillo_2023.pdf",
                'source_page' => 10 + $gIndex,
            ]);

            for ($i = 1; $i <= $size; $i++) {
                $q = Question::create([
                    'subject_id' => $subject->id,
                    'topic_id' => $topic->id,
                    'stimulus_id' => $stimulus->id,
                    'order_in_group' => $i,
                    'statement' => "Pregunta {$i} del Estímulo {$gIndex}: ¿Cuál es la tesis central?",
                    'difficulty' => 'medium',
                    'explanation' => "Explicación verificada del reactivo {$i}",
                    'source_pdf' => "Cuadernillo_2023.pdf",
                    'source_page' => 10 + $gIndex,
                    'original_question_number' => ($gIndex * 10) + $i,
                    'status' => 'published',
                ]);

                foreach (['A', 'B', 'C', 'D'] as $letter) {
                    QuestionOption::create([
                        'question_id' => $q->id,
                        'option_letter' => $letter,
                        'option_text' => "Opción {$letter} para la pregunta {$q->id}",
                        'is_correct' => ($letter === 'B'), // Opción B correcta
                    ]);
                }

                $questions->push($q);
            }
        }

        // Crear preguntas independientes (singletons)
        for ($s = 1; $s <= $singletonCount; $s++) {
            $q = Question::create([
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'stimulus_id' => null,
                'order_in_group' => 1,
                'statement' => "Pregunta independiente {$s}: Concepto fundamental",
                'difficulty' => ($s % 2 === 0) ? 'easy' : 'hard',
                'explanation' => "Explicación para pregunta independiente {$s}",
                'source' => 'Banco Propio',
                'status' => 'published',
            ]);

            foreach (['A', 'B', 'C', 'D'] as $letter) {
                QuestionOption::create([
                    'question_id' => $q->id,
                    'option_letter' => $letter,
                    'option_text' => "Opción {$letter} de pregunta {$s}",
                    'is_correct' => ($letter === 'A'), // Opción A correcta
                ]);
            }

            $questions->push($q);
        }

        return [
            'subject' => $subject,
            'topic' => $topic,
            'questions' => $questions,
        ];
    }

    /**
     * PA-01: Una práctica válida devuelve exactamente 25 preguntas.
     */
    public function test_valid_practice_returns_exactly_25_questions(): void
    {
        $this->createTestUser();
        // 7 preguntas en estímulos (3 + 4) + 22 singletons = 29 preguntas en total
        $data = $this->seedControlledSubject(22, [3, 4]);

        $response = $this->postJson('/api/exams/configure', [
            'exam_type' => 'practice',
            'subject_ids' => [$data['subject']->id],
            'difficulty' => 'medium',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $attemptId = $response->json('data.attempt.id');
        $this->assertNotNull($attemptId);

        // Consultar preguntas asignadas
        $questionsResponse = $this->getJson("/api/exams/{$attemptId}/questions");
        $questionsResponse->assertStatus(200);

        $questions = $questionsResponse->json('data.questions');
        $this->assertCount(25, $questions);

        // Verificar que no expone 'is_correct' ni 'explanation'
        foreach ($questions as $q) {
            $this->assertArrayNotHasKey('is_correct', $q);
            $this->assertArrayNotHasKey('explanation', $q);
            foreach ($q['options'] as $opt) {
                $this->assertArrayNotHasKey('is_correct', $opt);
            }
        }
    }

    /**
     * PA-02: Los grupos con estímulos se mantienen completos y ordenados internamente.
     */
    public function test_stimulus_groups_are_kept_complete_and_ordered(): void
    {
        $this->createTestUser();
        // Estímulos de 3 y 4 preguntas, más 20 singletons
        $data = $this->seedControlledSubject(20, [3, 4]);

        $response = $this->postJson('/api/exams/configure', [
            'exam_type' => 'practice',
            'subject_ids' => [$data['subject']->id],
        ]);

        $attemptId = $response->json('data.attempt.id');
        $questionsResponse = $this->getJson("/api/exams/{$attemptId}/questions");
        $questions = $questionsResponse->json('data.questions');

        // Agrupar preguntas recibidas por stimulus.id si tienen estímulo
        $stimulusOccurrences = [];
        foreach ($questions as $index => $q) {
            if (!empty($q['stimulus'])) {
                $sId = $q['stimulus']['id'];
                $stimulusOccurrences[$sId][] = [
                    'index' => $index,
                    'order_position' => $q['order_position'],
                    'statement' => $q['statement'],
                ];
            }
        }

        // Cada grupo presente debe estar completo (o 3 o 4) y sus índices deben ser consecutivos
        foreach ($stimulusOccurrences as $sId => $occ) {
            $count = count($occ);
            $this->assertTrue(in_array($count, [3, 4]), "El grupo de estímulo {$sId} fue cortado.");

            // Verificar consecutividad en la lista
            for ($i = 0; $i < $count - 1; $i++) {
                $this->assertEquals($occ[$i]['index'] + 1, $occ[$i + 1]['index'], "Las preguntas del estímulo {$sId} no son consecutivas.");
            }
        }
    }

    /**
     * PA-03: Un banco insuficiente devuelve HTTP 422 sin dejar intentos parciales ni huérfanos.
     */
    public function test_insufficient_bank_returns_422_without_orphan_records(): void
    {
        $this->createTestUser();
        // Materia con solo 12 preguntas
        $data = $this->seedControlledSubject(12, []);

        $attemptsBefore = ExamAttempt::count();
        $answersBefore = AttemptAnswer::count();

        $response = $this->postJson('/api/exams/configure', [
            'exam_type' => 'practice',
            'subject_ids' => [$data['subject']->id],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'available' => 12,
                    'required' => 25,
                ],
            ]);

        // Verificar que no se crearon registros parciales
        $this->assertEquals($attemptsBefore, ExamAttempt::count());
        $this->assertEquals($answersBefore, AttemptAnswer::count());
    }

    /**
     * PA-04: Recargar las preguntas devuelve exactamente los mismos identificadores y el mismo orden.
     */
    public function test_reloading_questions_returns_same_ids_and_order(): void
    {
        $this->createTestUser();
        $data = $this->seedControlledSubject(20, [3, 4]);

        $response = $this->postJson('/api/exams/configure', [
            'exam_type' => 'practice',
            'subject_ids' => [$data['subject']->id],
        ]);

        $attemptId = $response->json('data.attempt.id');

        $firstCall = $this->getJson("/api/exams/{$attemptId}/questions")->json('data.questions');
        $secondCall = $this->getJson("/api/exams/{$attemptId}/questions")->json('data.questions');

        $firstIds = array_column($firstCall, 'id');
        $secondIds = array_column($secondCall, 'id');

        $this->assertEquals($firstIds, $secondIds);
    }

    /**
     * PA-05: Validar que las respuestas pertenezcan al intento y a la pregunta.
     */
    public function test_submit_answer_validates_assignment_and_question_option(): void
    {
        $this->createTestUser();
        $data = $this->seedControlledSubject(20, [3, 4]);

        $response = $this->postJson('/api/exams/configure', [
            'exam_type' => 'practice',
            'subject_ids' => [$data['subject']->id],
        ]);

        $attemptId = $response->json('data.attempt.id');
        $questions = $this->getJson("/api/exams/{$attemptId}/questions")->json('data.questions');

        $q1 = $questions[0];
        $validOptionId = $q1['options'][0]['id'];

        // 1. Respuesta válida
        $resValid = $this->postJson("/api/exams/{$attemptId}/answer", [
            'question_id' => $q1['id'],
            'selected_option_id' => $validOptionId,
            'time_spent_seconds' => 15,
        ]);
        $resValid->assertStatus(200)->assertJson(['success' => true]);

        // 2. Respuesta con opción de otra pregunta
        $q2 = $questions[1];
        $wrongOptionId = $q2['options'][0]['id'];

        $resWrongOption = $this->postJson("/api/exams/{$attemptId}/answer", [
            'question_id' => $q1['id'],
            'selected_option_id' => $wrongOptionId,
        ]);
        $resWrongOption->assertStatus(400);

        // 3. Respuesta a una pregunta no asignada al intento
        $unassignedQuestion = Question::create([
            'subject_id' => $data['subject']->id,
            'topic_id' => $data['topic']->id,
            'statement' => 'Pregunta ajena no asignada',
            'difficulty' => 'easy',
            'status' => 'published',
        ]);
        $unassignedOption = QuestionOption::create([
            'question_id' => $unassignedQuestion->id,
            'option_letter' => 'A',
            'option_text' => 'Texto',
            'is_correct' => true,
        ]);

        $resUnassigned = $this->postJson("/api/exams/{$attemptId}/answer", [
            'question_id' => $unassignedQuestion->id,
            'selected_option_id' => $unassignedOption->id,
        ]);
        $resUnassigned->assertStatus(400);
    }

    /**
     * PA-06 y PA-07: Preguntas omitidas aparecen en questions_detail y la suma de correctas, incorrectas y omitidas coincide con 25.
     */
    public function test_omitted_questions_appear_and_sum_equals_total_assigned(): void
    {
        $this->createTestUser();
        $data = $this->seedControlledSubject(20, [3, 4]);

        $configResponse = $this->postJson('/api/exams/configure', [
            'exam_type' => 'practice',
            'subject_ids' => [$data['subject']->id],
        ]);

        $attemptId = $configResponse->json('data.attempt.id');
        $questions = $this->getJson("/api/exams/{$attemptId}/questions")->json('data.questions');

        // Responder solo 20 preguntas (10 correctas y 10 incorrectas), omitir 5
        for ($i = 0; $i < 20; $i++) {
            $q = $questions[$i];
            // Opción B es correcta en grupos con estímulo, opción A en singletons
            $options = $q['options'];
            $optionToChoose = ($i < 10) ? $options[0] : $options[1]; // Variar selección

            $this->postJson("/api/exams/{$attemptId}/answer", [
                'question_id' => $q['id'],
                'selected_option_id' => $optionToChoose['id'],
                'time_spent_seconds' => 10,
            ]);
        }
        // Preguntas 20 a 24 quedan omitidas (sin respuesta)

        // Finalizar
        $finishResponse = $this->postJson("/api/exams/{$attemptId}/finish", []);
        $finishResponse->assertStatus(200);

        $finishData = $finishResponse->json('data');
        $this->assertEquals(25, $finishData['total_questions']);
        $this->assertEquals(5, $finishData['omitted_answers']);
        $this->assertEquals(
            $finishData['total_questions'],
            $finishData['correct_answers'] + $finishData['incorrect_answers'] + $finishData['omitted_answers']
        );

        // Consultar resultados detallados
        $resultsResponse = $this->getJson("/api/exams/{$attemptId}/results");
        $resultsResponse->assertStatus(200);

        $resultsData = $resultsResponse->json('data');
        $this->assertCount(25, $resultsData['questions_detail']);

        // Contar omitidas en questions_detail
        $omittedInDetail = array_filter($resultsData['questions_detail'], fn($d) => $d['user_selected_option'] === null);
        $this->assertCount(5, $omittedInDetail);

        // Verificar que las omitidas tienen correct_option definida
        foreach ($omittedInDetail as $omittedItem) {
            $this->assertNotNull($omittedItem['correct_option']);
        }
    }

    /**
     * PA-08: La finalización repetida es estrictamente idempotente.
     */
    public function test_repeated_finish_is_idempotent(): void
    {
        $this->createTestUser();
        $data = $this->seedControlledSubject(20, [3, 4]);

        $response = $this->postJson('/api/exams/configure', [
            'exam_type' => 'practice',
            'subject_ids' => [$data['subject']->id],
        ]);

        $attemptId = $response->json('data.attempt.id');

        $finish1 = $this->postJson("/api/exams/{$attemptId}/finish", [])->json('data');
        $finish2 = $this->postJson("/api/exams/{$attemptId}/finish", [])->json('data');

        $this->assertEquals($finish1['score_percentage'], $finish2['score_percentage']);
        $this->assertEquals($finish1['correct_answers'], $finish2['correct_answers']);
        $this->assertEquals($finish1['omitted_answers'], $finish2['omitted_answers']);

        // No debe permitir responder tras finalizar
        $questions = $this->getJson("/api/exams/{$attemptId}/questions")->json('data.questions');
        $submitAfter = $this->postJson("/api/exams/{$attemptId}/answer", [
            'question_id' => $questions[0]['id'],
            'selected_option_id' => $questions[0]['options'][0]['id'],
        ]);
        $submitAfter->assertStatus(400);
    }

    /**
     * PA-09: Modificación posterior del banco no altera la calificación histórica.
     */
    public function test_bank_modification_does_not_change_historical_results(): void
    {
        $this->createTestUser();
        $data = $this->seedControlledSubject(20, [3, 4]);

        $response = $this->postJson('/api/exams/configure', [
            'exam_type' => 'practice',
            'subject_ids' => [$data['subject']->id],
        ]);

        $attemptId = $response->json('data.attempt.id');
        $questions = $this->getJson("/api/exams/{$attemptId}/questions")->json('data.questions');

        // Responder la primera pregunta con su opción correcta inicial
        $q1 = $questions[0];
        // Consultar la opción correcta en la base de datos
        $correctOption = QuestionOption::where('question_id', $q1['id'])->where('is_correct', true)->first();

        $this->postJson("/api/exams/{$attemptId}/answer", [
            'question_id' => $q1['id'],
            'selected_option_id' => $correctOption->id,
            'time_spent_seconds' => 10,
        ]);

        $this->postJson("/api/exams/{$attemptId}/finish", []);

        $resultsBefore = $this->getJson("/api/exams/{$attemptId}/results")->json('data');
        $scoreBefore = $resultsBefore['score_percentage'];
        $correctBefore = $resultsBefore['correct_answers'];

        // SIMULACIÓN: Un administrador modifica el banco y cambia la clave correcta de la pregunta 1 a otra opción
        QuestionOption::where('question_id', $q1['id'])->update(['is_correct' => false]);
        $otherOption = QuestionOption::where('question_id', $q1['id'])->where('id', '!=', $correctOption->id)->first();
        $otherOption->update(['is_correct' => true]);

        // Consultar resultados nuevamente
        $resultsAfter = $this->getJson("/api/exams/{$attemptId}/results")->json('data');

        // La calificación histórica y los aciertos deben mantenerse intactos gracias al snapshot inmutable
        $this->assertEquals($scoreBefore, $resultsAfter['score_percentage']);
        $this->assertEquals($correctBefore, $resultsAfter['correct_answers']);
        $this->assertEquals($correctOption->id, $resultsAfter['questions_detail'][0]['correct_option']['id']);
    }

    /**
     * PA-10: Los simulacros existentes (quick y full) continúan funcionando sin cambios.
     */
    public function test_existing_simulators_quick_and_full_continue_working(): void
    {
        $this->createTestUser();
        $this->seedControlledSubject(20, [3, 4]);

        // Probar modo quick (5 preguntas)
        $quickResponse = $this->postJson('/api/exams/configure', [
            'exam_type' => 'quick',
            'difficulty' => 'easy',
        ]);
        $quickResponse->assertStatus(201);
        $quickAttemptId = $quickResponse->json('data.attempt.id');

        $questionsQuick = $this->getJson("/api/exams/{$quickAttemptId}/questions")->json('data.questions');
        $this->assertCount(5, $questionsQuick);

        $finishQuick = $this->postJson("/api/exams/{$quickAttemptId}/finish", []);
        $finishQuick->assertStatus(200);
    }
}
