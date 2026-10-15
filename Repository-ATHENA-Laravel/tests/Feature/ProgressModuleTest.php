<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserTopicPerformance;
use Tests\TestCase;

class ProgressModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    /**
     * Probar historial de simulacros para un estudiante.
     */
    public function test_get_history(): void
    {
        $response = $this->getJson('/api/progress/history');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'fecha',
                        'tipo',
                        'cantidad_preguntas',
                        'correctas',
                        'incorrectas',
                        'omitidas',
                        'porcentaje',
                        'tiempo_empleado_segundos',
                        'estado',
                    ],
                ],
            ]);
    }

    /**
     * Probar estadísticas generales consolidadas.
     */
    public function test_get_general_stats(): void
    {
        $response = $this->getJson('/api/progress/stats');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'total_simulacros_completados',
                    'total_preguntas_respondidas',
                    'total_correctas',
                    'total_incorrectas',
                    'total_omitidas',
                    'porcentaje_global_aciertos',
                    'promedio_porcentaje_simulacro',
                    'mejor_resultado',
                    'peor_resultado',
                ],
            ]);
    }

    /**
     * Probar rendimiento por materia específica.
     */
    public function test_get_subject_performance(): void
    {
        $subject = Subject::first();
        $this->assertNotNull($subject);

        $response = $this->getJson("/api/progress/subject/{$subject->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'subject_id',
                    'subject_name',
                    'subject_slug',
                    'total_preguntas',
                    'correctas',
                    'incorrectas',
                    'omitidas',
                    'porcentaje_aciertos',
                    'nivel_desempeno',
                ],
            ]);
    }

    /**
     * Probar rendimiento por tema específico utilizando user_topic_performance.
     */
    public function test_get_topic_performance(): void
    {
        $topic = Topic::first();
        $this->assertNotNull($topic);

        $response = $this->getJson("/api/progress/topic/{$topic->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'topic_id',
                    'topic_name',
                    'subject_id',
                    'subject_name',
                    'total_preguntas',
                    'correctas',
                    'incorrectas',
                    'omitidas',
                    'porcentaje_aciertos',
                    'mastery_score',
                ],
            ]);
    }

    /**
     * Probar el dashboard general de estudiante.
     */
    public function test_get_dashboard_overview(): void
    {
        $response = $this->getJson('/api/progress');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'resumen_general',
                    'materias',
                    'temas_fuertes',
                    'temas_debiles',
                    'ultimos_simulacros',
                ],
            ]);
    }

    /**
     * Probar comportamiento cuando un estudiante no tiene simulacros.
     */
    public function test_student_without_exams(): void
    {
        $newUser = User::factory()->create([
            'full_name' => 'Estudiante Nuevo Sin Simulacros',
            'email' => 'nuevo.estudiante@athena.edu.co',
        ]);

        $this->actingAs($newUser);

        $statsResponse = $this->getJson('/api/progress/stats');
        $statsResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_simulacros_completados' => 0,
                    'total_preguntas_respondidas' => 0,
                    'porcentaje_global_aciertos' => 0,
                ],
            ]);

        $historyResponse = $this->getJson('/api/progress/history');
        $historyResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    /**
     * Probar el aislamiento estricto entre usuarios (un usuario no ve datos de otro).
     */
    public function test_user_data_isolation(): void
    {
        $user1 = User::first();
        $user2 = User::factory()->create(['email' => 'user2@athena.edu.co']);

        // Crear desempeño de tema solo para user1
        $topic = Topic::first();
        UserTopicPerformance::updateOrCreate(
            ['user_id' => $user1->id, 'topic_id' => $topic->id],
            ['correct_count' => 10, 'incorrect_count' => 0, 'omitted_count' => 0, 'mastery_score' => 1.00]
        );

        // Consultar como user2
        $this->actingAs($user2);
        $responseUser2 = $this->getJson("/api/progress/topic/{$topic->id}");

        $responseUser2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_preguntas' => 0,
                    'correctas' => 0,
                    'mastery_score' => 0,
                ],
            ]);
    }
}
