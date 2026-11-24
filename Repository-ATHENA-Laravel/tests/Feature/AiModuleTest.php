<?php

namespace Tests\Feature;

use App\Services\AiContextService;
use App\Services\AiService;
use App\Services\GeminiService;
use App\Services\ProgressService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AiModuleTest extends TestCase
{
    protected User $authUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');

        $this->authUser = User::where('email', 'juan.acevedo@athena.edu.co')->first() ?? User::first();
        Sanctum::actingAs($this->authUser);
    }

    /**
     * Prueba: POST /api/ai/message y demás endpoints IA sin token devuelven 401.
     */
    public function test_ai_endpoints_require_sanctum_authentication(): void
    {
        Auth::forgetGuards();

        $this->postJson('/api/ai/message', ['message' => 'Hola'])->assertStatus(401);
        $this->getJson('/api/ai/history')->assertStatus(401);
        $this->getJson('/api/ai/conversations/conv-123')->assertStatus(401);
    }

    /**
     * Verifica si MongoDB está disponible en el entorno actual.
     */
    protected function isMongoAvailable(): bool
    {
        try {
            \App\Models\Mongo\AiConversation::count();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Helper para registrar un mock de GeminiService.
     */
    protected function mockGemini(string $fakeResponse = 'Respuesta simulada de ATHENA para prueba.'): void
    {
        $mock = Mockery::mock(GeminiService::class);
        $mock->shouldReceive('generateText')
            ->andReturn($fakeResponse);
        $this->app->instance(GeminiService::class, $mock);
    }

    /**
     * Prueba: Crear una conversación nueva enviando un mensaje.
     */
    public function test_create_new_conversation(): void
    {
        $this->mockGemini();

        $response = $this->postJson('/api/ai/message', [
            'message' => '¿Qué son las ecuaciones cuadráticas?',
            'conversation_id' => null,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'conversation_id',
                    'title',
                    'message',
                    'ai_response',
                ],
            ]);

        $this->assertEquals('Respuesta simulada de ATHENA para prueba.', $response->json('data.ai_response'));
        $this->assertNotEmpty($response->json('data.conversation_id'));
    }

    /**
     * Prueba: Continuar una conversación existente (requiere MongoDB).
     */
    public function test_continue_existing_conversation(): void
    {
        if (!$this->isMongoAvailable()) {
            $this->markTestSkipped('MongoDB no disponible en este entorno.');
        }

        $this->mockGemini('Primera respuesta.');

        $first = $this->postJson('/api/ai/message', [
            'message' => 'Hola ATHENA',
            'conversation_id' => null,
        ]);
        $first->assertStatus(200);
        $conversationId = $first->json('data.conversation_id');

        $this->mockGemini('Segunda respuesta con contexto.');

        $second = $this->postJson('/api/ai/message', [
            'message' => 'Explícame más sobre Lectura Crítica',
            'conversation_id' => $conversationId,
        ]);

        $second->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'conversation_id' => $conversationId,
                    'ai_response' => 'Segunda respuesta con contexto.',
                ],
            ]);
    }

    /**
     * Prueba: Mensajes se guardan en la conversación (requiere MongoDB).
     */
    public function test_messages_are_stored(): void
    {
        if (!$this->isMongoAvailable()) {
            $this->markTestSkipped('MongoDB no disponible en este entorno.');
        }

        $this->mockGemini('Respuesta guardada.');

        $response = $this->postJson('/api/ai/message', [
            'message' => '¿Cómo resuelvo problemas de proporcionalidad?',
            'conversation_id' => null,
        ]);

        $response->assertStatus(200);
        $conversationId = $response->json('data.conversation_id');

        $detailResponse = $this->getJson("/api/ai/conversations/{$conversationId}");
        $detailResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'messages',
                ],
            ]);

        $messages = $detailResponse->json('data.messages');
        $this->assertCount(2, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('model', $messages[1]['role']);
    }

    /**
     * Prueba: Recuperar el historial de conversaciones del estudiante (requiere MongoDB).
     */
    public function test_get_conversation_history(): void
    {
        if (!$this->isMongoAvailable()) {
            $this->markTestSkipped('MongoDB no disponible en este entorno.');
        }

        $this->mockGemini();

        $this->postJson('/api/ai/message', ['message' => 'Primera conversación', 'conversation_id' => null]);
        $this->postJson('/api/ai/message', ['message' => 'Segunda conversación', 'conversation_id' => null]);

        $historyResponse = $this->getJson('/api/ai/history');
        $historyResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $historyResponse->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    /**
     * Prueba: El historial devuelve array vacío cuando no hay MongoDB (degradación elegante).
     */
    public function test_history_returns_empty_without_mongo(): void
    {
        if ($this->isMongoAvailable()) {
            $this->markTestSkipped('Esta prueba valida el comportamiento sin MongoDB.');
        }

        $historyResponse = $this->getJson('/api/ai/history');
        $historyResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    /**
     * Prueba: El contexto académico se construye desde MySQL.
     */
    public function test_academic_context_is_built(): void
    {
        $contextService = app(AiContextService::class);
        $user = User::first();

        $context = $contextService->buildAcademicContext($user->id);

        $this->assertArrayHasKey('stats', $context);
        $this->assertArrayHasKey('subjects', $context);
        $this->assertArrayHasKey('strong_topics', $context);
        $this->assertArrayHasKey('weak_topics', $context);

        $formatted = $contextService->formatContextForPrompt($context);
        $this->assertStringContainsString('CONTEXTO ACADÉMICO DEL ESTUDIANTE', $formatted);
    }

    /**
     * Prueba: Aislamiento entre usuarios — otro usuario no puede continuar conversación ajena.
     */
    public function test_user_data_isolation(): void
    {
        $this->mockGemini();

        $response1 = $this->postJson('/api/ai/message', [
            'message' => 'Mensaje privado de usuario 1',
            'conversation_id' => null,
        ]);
        $response1->assertStatus(200);
        $convId = $response1->json('data.conversation_id');

        // User 2 intenta continuar la conversación de User 1
        $user2 = User::factory()->create(['email' => 'user2ai@athena.edu.co']);
        Sanctum::actingAs($user2);

        $this->mockGemini();
        $response2 = $this->postJson('/api/ai/message', [
            'message' => 'Intento acceder a conversación ajena',
            'conversation_id' => $convId,
        ]);

        // Debe fallar: conversación no pertenece a user2
        $response2->assertStatus(500)
            ->assertJson(['success' => false]);

        // Y tampoco puede consultar el detalle de la conversación de User 1
        $this->getJson("/api/ai/conversations/{$convId}")
            ->assertStatus(404)
            ->assertJson(['success' => false]);

        // Verificar que la conversación quedó asociada al usuario autenticado original (authUser) y no a user2 ni a ID 1 arbitrario
        $cached = Cache::get("ai_conversation:{$convId}");
        $this->assertNotNull($cached);
        $this->assertEquals($this->authUser->id, $cached['user_id']);
    }

    /**
     * Prueba: Fallback de Cache en GET /api/ai/history recupera conversaciones por usuario sin mezclar ni duplicar cuando MongoDB no está disponible.
     */
    public function test_cache_fallback_history_and_user_isolation_without_mongo(): void
    {
        if ($this->isMongoAvailable()) {
            $this->markTestSkipped('Esta prueba valida específicamente el fallback de Cache cuando MongoDB no está disponible.');
        }

        // Usuario 1 crea dos conversaciones y continúa la primera
        Sanctum::actingAs($this->authUser);
        $this->mockGemini('Respuesta 1 para Usuario 1');
        $res1 = $this->postJson('/api/ai/message', [
            'message' => 'Primera consulta de Usuario 1',
            'conversation_id' => null,
        ])->assertOk();
        $conv1Id = $res1->json('data.conversation_id');

        $this->mockGemini('Respuesta 2 para Usuario 1');
        $res2 = $this->postJson('/api/ai/message', [
            'message' => 'Segunda consulta de Usuario 1',
            'conversation_id' => null,
        ])->assertOk();
        $conv2Id = $res2->json('data.conversation_id');

        // Continuar primera conversación para verificar que no se duplica en el índice y actualiza total_messages
        $this->mockGemini(' Continuación de primera consulta');
        $this->postJson('/api/ai/message', [
            'message' => 'Seguimiento en conversación 1',
            'conversation_id' => $conv1Id,
        ])->assertOk();

        // Usuario 2 crea su propia conversación
        $user2 = User::factory()->create(['email' => 'user2.history@athena.edu.co']);
        Sanctum::actingAs($user2);
        $this->mockGemini('Respuesta exclusiva para Usuario 2');
        $resUser2 = $this->postJson('/api/ai/message', [
            'message' => 'Consulta privada de Usuario 2',
            'conversation_id' => null,
        ])->assertOk();
        $convUser2Id = $resUser2->json('data.conversation_id');

        // Incluso si alguien inyectara el ID de conversación de Usuario 1 en el índice de Cache de Usuario 2, debe filtrarse por user_id
        $pollutedIndex = Cache::get("ai_user_conversations:{$user2->id}", []);
        $pollutedIndex[] = $conv1Id;
        $pollutedIndex[] = $convUser2Id; // duplicado intencional
        Cache::put("ai_user_conversations:{$user2->id}", $pollutedIndex, 86400);

        // Consultar historial como Usuario 2: solo debe recibir su conversación (sin duplicados y sin conv1Id)
        $historyUser2 = $this->getJson('/api/ai/history');
        $historyUser2->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    ['id', 'title', 'total_messages', 'last_message', 'updated_at'],
                ],
            ]);
        $this->assertEquals($convUser2Id, $historyUser2->json('data.0.id'));
        $this->assertEquals(2, $historyUser2->json('data.0.total_messages'));

        // Consultar historial como Usuario 1: debe recibir únicamente sus 2 conversaciones (conv1Id primero por haberse actualizado más recientemente)
        Sanctum::actingAs($this->authUser);
        $historyUser1 = $this->getJson('/api/ai/history');
        $historyUser1->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        $this->assertEquals($conv1Id, $historyUser1->json('data.0.id'));
        $this->assertEquals(4, $historyUser1->json('data.0.total_messages'));
        $this->assertEquals($conv2Id, $historyUser1->json('data.1.id'));
        $this->assertEquals(2, $historyUser1->json('data.1.total_messages'));
    }

    /**
     * Prueba: Estudiante sin progreso académico genera contexto vacío sin error.
     */
    public function test_student_without_progress(): void
    {
        $newUser = User::factory()->create(['email' => 'nuevo.ia@athena.edu.co']);
        Sanctum::actingAs($newUser);

        $this->mockGemini('Te ayudo aunque aún no tengas simulacros.');

        $response = $this->postJson('/api/ai/message', [
            'message' => '¿Cómo empiezo a prepararme para el ICFES?',
            'conversation_id' => null,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'ai_response' => 'Te ayudo aunque aún no tengas simulacros.',
                ],
            ]);
    }

    /**
     * Prueba: Error controlado si GeminiService falla.
     */
    public function test_gemini_error_is_handled(): void
    {
        $mock = Mockery::mock(GeminiService::class);
        $mock->shouldReceive('generateText')
            ->andThrow(new \RuntimeException('Fallo en la comunicación con Gemini: Error de conexión.'));
        $this->app->instance(GeminiService::class, $mock);

        $response = $this->postJson('/api/ai/message', [
            'message' => 'Esto debería fallar de forma controlada',
            'conversation_id' => null,
        ]);

        $response->assertStatus(500)
            ->assertJson(['success' => false]);

        $apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        if (!empty($apiKey)) {
            $this->assertStringNotContainsString($apiKey, json_encode($response->json()));
        }
    }

    /**
     * Prueba: Validación del request (mensaje requerido).
     */
    public function test_message_is_required(): void
    {
        $response = $this->postJson('/api/ai/message', [
            'message' => '',
            'conversation_id' => null,
        ]);

        $response->assertStatus(422);
    }
}
