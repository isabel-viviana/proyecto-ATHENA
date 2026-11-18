<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeminiConnectionTest extends TestCase
{
    /**
     * Probar la conexión real con Gemini a través del endpoint de diagnóstico (protegido para superadmin).
     */
    public function test_gemini_connection_endpoint(): void
    {
        $this->artisan('migrate');
        $superadmin = User::factory()->create([
            'email' => 'superadmin.gemini@athena.edu.co',
            'role' => 'superadmin',
        ]);
        Sanctum::actingAs($superadmin);

        $response = $this->getJson('/api/test-db/gemini');

        // Si la API key es válida y responde exitosamente
        if ($response->status() === 200) {
            $response->assertJson([
                'success' => true,
                'message' => 'Gemini conectado correctamente.',
            ])->assertJsonStructure([
                'data' => ['response'],
            ]);

            $text = $response->json('data.response');
            $this->assertNotEmpty($text);
            
            // Verificar que la API Key nunca aparezca en el JSON retornado
            $apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
            if (!empty($apiKey)) {
                $this->assertStringNotContainsString($apiKey, json_encode($response->json()));
            }
        } else {
            // Si retorna 500 por cuota o credenciales inválidas, verificar mensaje controlado sin expurgar la clave
            $response->assertStatus(500)
                ->assertJson(['success' => false]);

            $apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
            if (!empty($apiKey)) {
                $this->assertStringNotContainsString($apiKey, json_encode($response->json()));
            }
        }
    }
}
