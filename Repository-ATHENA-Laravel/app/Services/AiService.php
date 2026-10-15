<?php

namespace App\Services;

use App\Models\Mongo\AiConversation;
use App\Models\Mongo\AiInteraction;
use App\Models\Mongo\AiLearningContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Throwable;
use RuntimeException;

class AiService
{
    protected GeminiService $geminiService;
    protected AiContextService $aiContextService;

    public function __construct(GeminiService $geminiService, AiContextService $aiContextService)
    {
        $this->geminiService = $geminiService;
        $this->aiContextService = $aiContextService;
    }

    /**
     * Prompt base de ATHENA como tutora virtual oficial ICFES.
     */
    protected function getSystemPrompt(): string
    {
        return <<<PROMPT
Eres ATHENA, una tutora virtual especializada exclusivamente en preparación para las pruebas ICFES (Saber 11).

Tu objetivo es ayudar al estudiante a comprender y aprender.

Reglas:
- Explica claramente y con lenguaje adecuado para estudiantes de secundaria/bachillerato.
- Enseña el procedimiento y razonamiento, no solamente la respuesta final.
- Utiliza ejemplos claros cuando ayuden a comprender el concepto.
- Adapta las explicaciones según el desempeño académico proporcionado en el contexto.
- Presta especial atención a los temas débiles del estudiante.
- No inventes resultados académicos ni calificaciones que no existan.
- Si el contexto no contiene determinada información, no afirmes conocerla.
- Mantente estrictamente en temas educativos relacionados con el examen ICFES.
- Cuando una pregunta pueda resolverse paso a paso, explica el procedimiento metódicamente.
- Sé clara, motivadora y relativamente concisa.
PROMPT;
    }

    /**
     * Procesa un mensaje enviado por el estudiante a ATHENA.
     */
    public function sendMessage(int $userId, string $userMessage, ?string $conversationId = null): array
    {
        // 1. Obtener o crear conversación en MongoDB
        $conversation = null;
        if (!empty($conversationId)) {
            try {
                $conversation = AiConversation::where('_id', $conversationId)
                    ->where('user_id', $userId)
                    ->first();
            } catch (Throwable $e) {
                $conversation = null;
            }

            if (!$conversation) {
                // Fallback si MongoDB no estuviera disponible o si la conversación previa fue generada en memoria
                $conversation = new AiConversation([
                    'user_id' => $userId,
                    'title' => Str::limit(trim($userMessage), 35, '...'),
                    'messages' => [],
                ]);
                $conversation->_id = $conversationId;
            }
        }

        $academicContextData = $this->aiContextService->buildAcademicContext($userId);
        $formattedContext = $this->aiContextService->formatContextForPrompt($academicContextData);

        if (!$conversation) {
            $titleSnippet = Str::limit(trim($userMessage), 35, '...');
            try {
                $conversation = AiConversation::create([
                    'user_id' => $userId,
                    'title' => $titleSnippet,
                    'messages' => [],
                    'context' => $academicContextData,
                ]);
            } catch (Throwable $e) {
                // Fallback si MongoDB no estuviera disponible en este instante
                $conversation = new AiConversation([
                    'user_id' => $userId,
                    'title' => $titleSnippet,
                    'messages' => [],
                ]);
                $conversation->_id = (string) Str::uuid();
            }
        }

        // 2. Formatear historial reciente (últimos 6 mensajes)
        $existingMessages = $conversation->messages ?? [];
        $recentMessages = array_slice($existingMessages, -6);
        $formattedHistory = "";
        if (!empty($recentMessages)) {
            $formattedHistory .= "\nHISTORIAL RECIENTE DE LA CONVERSACIÓN:\n";
            foreach ($recentMessages as $msg) {
                $roleLabel = ($msg['role'] ?? 'user') === 'user' ? 'Estudiante' : 'ATHENA';
                $formattedHistory .= "{$roleLabel}: " . ($msg['content'] ?? '') . "\n";
            }
        }

        // 3. Construir Prompt completo
        $fullPrompt = $this->getSystemPrompt() . "\n\n"
            . $formattedContext . "\n"
            . $formattedHistory . "\n"
            . "MENSAJE ACTUAL DEL ESTUDIANTE:\n"
            . "\"{$userMessage}\"\n\n"
            . "RESPUESTA DE ATHENA:";

        // 4. Invocar a GeminiService
        $aiResponse = $this->geminiService->generateText($fullPrompt);

        // 5. Guardar mensajes en MongoDB
        $userMsgStruct = [
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => now()->toIso8601String(),
        ];

        $aiMsgStruct = [
            'role' => 'model',
            'content' => $aiResponse,
            'created_at' => now()->toIso8601String(),
        ];

        try {
            $existingMessages[] = $userMsgStruct;
            $existingMessages[] = $aiMsgStruct;
            $conversation->messages = $existingMessages;
            $conversation->context = $academicContextData;
            $conversation->save();
        } catch (Throwable $e) {
            // Manejar error de MongoDB sin comprometer la respuesta
        }

        // 6. Registrar interacciones y snapshot documental en MongoDB
        try {
            AiInteraction::create([
                'user_id' => $userId,
                'conversation_id' => (string) $conversation->_id,
                'type' => 'chat',
                'user_message' => $userMessage,
                'ai_response' => $aiResponse,
                'metadata' => [
                    'model' => config('services.gemini.model', 'gemini-2.5-flash'),
                ],
            ]);

            AiLearningContext::updateOrCreate(
                ['user_id' => $userId],
                [
                    'strengths' => $academicContextData['strong_topics'] ?? [],
                    'weaknesses' => $academicContextData['weak_topics'] ?? [],
                    'recent_performance' => $academicContextData['stats'] ?? [],
                    'updated_at' => now(),
                ]
            );
        } catch (Throwable $e) {
            // Capturar silenciosamente cualquier error en colecciones secundarias
        }

        return [
            'conversation_id' => (string) $conversation->_id,
            'title' => $conversation->title,
            'message' => $userMessage,
            'ai_response' => $aiResponse,
        ];
    }

    /**
     * Obtiene el listado de conversaciones del estudiante desde MongoDB.
     */
    public function getUserConversations(int $userId): array
    {
        try {
            $conversations = AiConversation::where('user_id', $userId)
                ->orderBy('updated_at', 'desc')
                ->get();

            return $conversations->map(function ($conv) {
                $messages = $conv->messages ?? [];
                $lastMsg = end($messages);
                return [
                    'id' => (string) $conv->_id,
                    'title' => $conv->title,
                    'total_messages' => count($messages),
                    'last_message' => $lastMsg['content'] ?? '',
                    'updated_at' => $conv->updated_at ? $conv->updated_at->toDateTimeString() : null,
                ];
            })->toArray();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Obtiene los mensajes de una conversación específica.
     */
    public function getConversation(int $userId, string $conversationId): array
    {
        try {
            $conv = AiConversation::where('_id', $conversationId)
                ->where('user_id', $userId)
                ->firstOrFail();

            return [
                'id' => (string) $conv->_id,
                'title' => $conv->title,
                'messages' => $conv->messages ?? [],
                'context' => $conv->context ?? [],
                'created_at' => $conv->created_at ? $conv->created_at->toDateTimeString() : null,
            ];
        } catch (Throwable $e) {
            throw new ModelNotFoundException("Conversación no encontrada.");
        }
    }
}
