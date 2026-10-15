<?php

namespace App\Services;

use Gemini;
use Illuminate\Support\Facades\Http;
use Throwable;
use RuntimeException;

class GeminiService
{
    /**
     * Envía un prompt a Gemini Flash y retorna el texto generado.
     * Sanitiza activamente cualquier excepción para evitar exponer credenciales.
     */
    public function generateText(string $prompt): string
    {
        $apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        $model = config('services.gemini.model', 'gemini-3.1-flash-lite');

        if (empty($apiKey)) {
            throw new RuntimeException('La clave GEMINI_API_KEY no se encuentra configurada en el archivo de entorno.');
        }

        try {
            // 1. Utilizar el SDK oficial google-gemini-php si la clase existe y el modelo es compatible
            if (class_exists(Gemini::class)) {
                try {
                    $client = Gemini::client($apiKey);
                    $result = $client->generativeModel($model)->generateContent($prompt);
                    $text = $result->text();
                    if (!empty($text)) {
                        return trim($text);
                    }
                } catch (Throwable $e) {
                    // Fallback directo a petición HTTP estructurada
                }
            }

            // 2. Petición HTTP directa a la API v1beta de Google Generative AI
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
            
            $lastStatusCode = null;
            $lastErrorDetail = null;

            $response = Http::timeout(25)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $generatedText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($generatedText) {
                    return trim($generatedText);
                }
            }

            $lastStatusCode = $response->status();
            $lastErrorDetail = $response->json()['error']['message'] ?? "HTTP {$lastStatusCode}";

            // 3. Fallback controlado a modelos alternativos verificados si el primario falla (404, 503, etc.)
            if (!$response->successful()) {
                $fallbackModels = ['gemini-3.1-flash-lite', 'gemini-3.5-flash'];
                foreach ($fallbackModels as $altModel) {
                    if ($altModel === $model) continue;
                    $altUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$altModel}:generateContent?key=" . $apiKey;
                    try {
                        $altResponse = Http::timeout(25)->withHeaders([
                            'Content-Type' => 'application/json',
                        ])->post($altUrl, [
                            'contents' => [
                                [
                                    'parts' => [
                                        ['text' => $prompt]
                                    ]
                                ]
                            ]
                        ]);

                        if ($altResponse->successful()) {
                            $altData = $altResponse->json();
                            $altGeneratedText = $altData['candidates'][0]['content']['parts'][0]['text'] ?? null;
                            if ($altGeneratedText) {
                                return trim($altGeneratedText);
                            }
                        }

                        $lastStatusCode = $altResponse->status();
                        $lastErrorDetail = $altResponse->json()['error']['message'] ?? "HTTP {$lastStatusCode}";
                    } catch (Throwable $eAlt) {
                        $lastErrorDetail = $eAlt->getMessage();
                    }
                }
            }

            // 4. Mapeo técnico seguro de códigos de error HTTP de Gemini
            $errorExplanation = match ($lastStatusCode) {
                400 => "Solicitud inválida a Gemini API (HTTP 400).",
                401 => "Clave API no autorizada o inválida (HTTP 401).",
                403 => "Permisos insuficientes o clave suspendida en Gemini API (HTTP 403).",
                404 => "Modelo no encontrado o no soportado en Gemini API (HTTP 404).",
                429 => "Límite de cuota excedido en Gemini API (HTTP 429).",
                503 => "Servicio de Gemini temporalmente saturado por alta demanda (HTTP 503).",
                default => "Error en la solicitud a Gemini API (HTTP {$lastStatusCode}). Detalle: {$lastErrorDetail}",
            };

            throw new RuntimeException($errorExplanation, $lastStatusCode ?: 500);

        } catch (Throwable $e) {
            // Sanitización estricta para garantizar que nunca se expongan claves API
            $cleanMessage = preg_replace('/key=[a-zA-Z0-9_\-]+/', 'key=***HIDDEN***', $e->getMessage());
            if (!empty($apiKey)) {
                $cleanMessage = str_replace($apiKey, '***HIDDEN***', $cleanMessage);
            }

            throw new RuntimeException("Fallo en la comunicación con Gemini: " . $cleanMessage, 500);
        }
    }
}
