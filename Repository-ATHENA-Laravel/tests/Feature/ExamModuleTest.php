<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTopicPerformance;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
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
     * Probar que todos los endpoints de exámenes sin token devuelven 401.
     */
    public function test_exam_endpoints_require_sanctum_authentication(): void
    {
        Auth::forgetGuards();

        $this->postJson('/api/exams/configure', ['exam_type' => 'quick', 'difficulty' => 'easy'])->assertStatus(401);
        $this->getJson('/api/exams/1/questions')->assertStatus(401);
        $this->postJson('/api/exams/1/answer', ['question_id' => 1, 'selected_option_id' => 1])->assertStatus(401);
        $this->postJson('/api/exams/1/finish')->assertStatus(401);
        $this->getJson('/api/exams/1/results')->assertStatus(401);
    }

    /**
     * Probar el ciclo de vida completo del módulo de simulacros y aislamiento entre usuarios.
     */
    public function test_full_exam_lifecycle(): void
    {
        $user = User::where('email', 'juan.acevedo@athena.edu.co')->first() ?? User::first();
        $this->assertNotNull($user);
        Sanctum::actingAs($user);

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

        // 6. Verificar que otro usuario autenticado NO puede consultar preguntas ni resultados del intento de $user
        $otherUser = User::factory()->create(['email' => 'otro.estudiante.exam@athena.edu.co']);
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/exams/{$attemptId}/questions")->assertStatus(404);
        $this->getJson("/api/exams/{$attemptId}/results")->assertStatus(404);
        $this->postJson("/api/exams/{$attemptId}/finish")->assertStatus(400);
    }
}
