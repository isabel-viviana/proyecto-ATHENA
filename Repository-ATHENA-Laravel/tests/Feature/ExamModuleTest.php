<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTopicPerformance;
use Tests\TestCase;

class ExamModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    /**
     * Probar el ciclo de vida completo del módulo de simulacros.
     */
    public function test_full_exam_lifecycle(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        // 1. POST /api/exams/configure
        $configResponse = $this->postJson('/api/exams/configure', [
            'exam_type' => 'quick',
            'difficulty' => 'easy',
        ]);

        $configResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Simulacro configurado e iniciado correctamente.',
            ]);

        $attemptId = $configResponse->json('data.attempt.id');
        $this->assertNotNull($attemptId);

        // 2. GET /api/exams/{id}/questions
        $questionsResponse = $this->getJson("/api/exams/{$attemptId}/questions");
        $questionsResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $questions = $questionsResponse->json('data.questions');
        $this->assertIsArray($questions);
        $this->assertNotEmpty($questions);

        // Verificar que no expone 'is_correct' ni 'explanation'
        $firstQuestion = $questions[0];
        $this->assertArrayNotHasKey('explanation', $firstQuestion);
        $this->assertArrayNotHasKey('is_correct', $firstQuestion['options'][0]);

        // 3. POST /api/exams/{id}/answer
        $q1 = $questions[0];
        $selectedOptionId = $q1['options'][0]['id'];

        $answerResponse = $this->postJson("/api/exams/{$attemptId}/answer", [
            'question_id' => $q1['id'],
            'selected_option_id' => $selectedOptionId,
            'time_spent_seconds' => 30,
        ]);

        $answerResponse->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['recorded' => true]]);

        // 4. POST /api/exams/{id}/finish
        $finishResponse = $this->postJson("/api/exams/{$attemptId}/finish", []);
        $finishResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                ],
            ]);

        // 5. GET /api/exams/{id}/results
        $resultsResponse = $this->getJson("/api/exams/{$attemptId}/results");
        $resultsResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'attempt_id',
                    'score_percentage',
                    'total_questions',
                    'correct_answers',
                    'incorrect_answers',
                    'omitted_answers',
                    'subject_performance',
                    'topic_performance',
                    'questions_detail',
                ],
            ]);

        // Verificar actualización en user_topic_performance
        $performanceCount = UserTopicPerformance::where('user_id', $user->id)->count();
        $this->assertGreaterThan(0, $performanceCount);
    }
}
