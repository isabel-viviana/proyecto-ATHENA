<?php

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\Mongo\ChatHistory;
use App\Models\Mongo\AiConversation;
use App\Models\Mongo\AiInteraction;
use App\Models\Mongo\AiLearningContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

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
- Utiliza formato Markdown estructurado y escribe las fórmulas matemáticas en LaTeX (\$...\$ en línea y \$\$...\$\$ en bloque).
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
     * Procesa un mensaje enviado por el estudiante a ATHENA registrando cualquier fallo de MongoDB en el Log.
     */
    public function sendMessage(int $userId, string $userMessage, ?string $conversationId = null): array
    {
        $userId = (int) $userId;
        $titleSnippet = Str::limit(trim($userMessage), 35, '...');

        $academicContextData = $this->aiContextService->buildAcademicContext($userId);
        $formattedContext = $this->aiContextService->formatContextForPrompt($academicContextData);
        $systemInstruction = $this->getSystemPrompt() . "\n\n" . $formattedContext;

        // 1. Buscar o crear AiConversation en MongoDB
        $conversation = null;
        if (!empty($conversationId)) {
            try {
                $conversation = AiConversation::where('user_id', $userId)
                    ->find($conversationId);

                if (!$conversation) {
                    $conversation = AiConversation::where('_id', $conversationId)
                        ->where('user_id', $userId)
                        ->first();
                }
            } catch (Throwable $e) {
                Log::error('Error consultando AiConversation en MongoDB: ' . $e->getMessage());
                $conversation = null;
            }
        }

        if (!$conversation) {
            try {
                $conversation = AiConversation::create([
                    'user_id' => $userId,
                    'title' => $titleSnippet,
                    'messages' => [],
                    'context' => $academicContextData,
                ]);
            } catch (Throwable $e) {
                Log::error('Error guardando en MongoDB (AiConversation::create): ' . $e->getMessage());
            }
        }

        // En mongodb/laravel-mongodb v5 la clave primaria está en ->id o ->getKey()
        $activeConversationId = $conversation
            ? (string) ($conversation->id ?? $conversation->_id ?? $conversation->getKey())
            : ($conversationId ?: '');

        // 2. Consultar en MongoDB los últimos 10 mensajes del usuario ordenados cronológicamente
        $history = [];
        try {
            $query = ChatHistory::where('user_id', $userId);
            if (!empty($activeConversationId)) {
                $query->where('conversation_id', $activeConversationId);
            }

            $history = $query->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->reverse()
                ->values()
                ->map(function ($msg) {
                    return [
                        'role' => $msg->role === 'model' ? 'model' : 'user',
                        'content' => (string) $msg->content,
                    ];
                })
                ->toArray();

            if (empty($history) && $conversation) {
                $rawMessages = is_string($conversation->messages)
                    ? (json_decode($conversation->messages, true) ?: [])
                    : ($conversation->messages ?? []);

                foreach (array_slice($rawMessages, -10) as $m) {
                    $history[] = [
                        'role' => ($m['role'] ?? 'user') === 'model' ? 'model' : 'user',
                        'content' => (string) ($m['content'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::error('Error consultando ChatHistory en MongoDB: ' . $e->getMessage());
            $history = [];
        }

        // Fallback de historial desde PostgreSQL si MongoDB no estaba disponible y es una conversación existente
        if (empty($history) && !empty($conversationId) && is_numeric($conversationId)) {
            try {
                $pgMessages = AiChatMessage::where('session_id', (int) $conversationId)
                    ->orderBy('id', 'desc')
                    ->limit(10)
                    ->get()
                    ->reverse()
                    ->values();

                foreach ($pgMessages as $pm) {
                    $history[] = [
                        'role' => $pm->sender_type === 'user' ? 'user' : 'model',
                        'content' => (string) $pm->message,
                    ];
                }
            } catch (Throwable $e) {
                Log::error('Error consultando historial de respaldo en PostgreSQL: ' . $e->getMessage());
            }
        }

        // 3. Enviar historial + nuevo prompt a Gemini 3.1 Flash-Lite
        $aiResponse = $this->geminiService->generateChatWithHistory(
            $history,
            $userMessage,
            $systemInstruction
        );

        // 4. Guardar mensajes en ChatHistory (MongoDB)
        try {
            ChatHistory::create([
                'user_id' => $userId,
                'conversation_id' => $activeConversationId,
                'role' => 'user',
                'content' => $userMessage,
            ]);

            ChatHistory::create([
                'user_id' => $userId,
                'conversation_id' => $activeConversationId,
                'role' => 'model',
                'content' => $aiResponse,
            ]);
        } catch (Throwable $e) {
            Log::error('Error guardando en MongoDB (ChatHistory): ' . $e->getMessage());
        }

        // 5. Guardar el array de messages actualizado en AiConversation (MongoDB)
        $mongoSaved = false;
        if ($conversation) {
            try {
                $existingMessages = is_string($conversation->messages)
                    ? (json_decode($conversation->messages, true) ?: [])
                    : ($conversation->messages ?? []);

                $existingMessages[] = [
                    'role' => 'user',
                    'content' => $userMessage,
                    'created_at' => now()->toIso8601String(),
                ];
                $existingMessages[] = [
                    'role' => 'model',
                    'content' => $aiResponse,
                    'created_at' => now()->toIso8601String(),
                ];

                $conversation->messages = $existingMessages;
                $conversation->context = $academicContextData;
                $conversation->save();

                $activeConversationId = (string) ($conversation->id ?? $conversation->_id ?? $conversation->getKey());
                $mongoSaved = !empty($activeConversationId);

                AiInteraction::create([
                    'user_id' => $userId,
                    'conversation_id' => $activeConversationId,
                    'type' => 'chat',
                    'user_message' => $userMessage,
                    'ai_response' => $aiResponse,
                    'metadata' => [
                        'model' => config('services.gemini.model', 'gemini-3.1-flash-lite'),
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
                Log::error('Error guardando en MongoDB (AiConversation::save): ' . $e->getMessage());
            }
        }

        // Respaldo automático en PostgreSQL (AiChatSession / AiChatMessage) si MongoDB falló
        if (!$mongoSaved) {
            try {
                $pgSession = null;
                if (!empty($conversationId) && is_numeric($conversationId)) {
                    $pgSession = AiChatSession::where('id', (int) $conversationId)
                        ->where('user_id', $userId)
                        ->first();
                }
                if (!$pgSession) {
                    $pgSession = AiChatSession::create([
                        'user_id' => $userId,
                        'title' => $titleSnippet,
                        'started_at' => now(),
                    ]);
                } else {
                    $pgSession->touch();
                }

                AiChatMessage::create([
                    'session_id' => $pgSession->id,
                    'sender_type' => 'user',
                    'message' => $userMessage,
                    'sent_at' => now(),
                ]);
                AiChatMessage::create([
                    'session_id' => $pgSession->id,
                    'sender_type' => 'ai',
                    'message' => $aiResponse,
                    'sent_at' => now(),
                ]);

                $activeConversationId = (string) $pgSession->id;
            } catch (Throwable $e) {
                Log::error('Error guardando respaldo de chat en PostgreSQL: ' . $e->getMessage());
            }
        }

        // 6. Retornar respuesta al controlador / frontend
        return [
            'conversation_id' => $activeConversationId,
            'title' => $conversation->title ?? $titleSnippet,
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
            $conversations = AiConversation::where('user_id', (int) $userId)
                ->orderBy('updated_at', 'desc')
                ->get();

            return $conversations->map(function ($conv) {
                $serializedId = (string) ($conv->id ?? $conv->_id ?? $conv->getKey());
                $messages = is_string($conv->messages)
                    ? (json_decode($conv->messages, true) ?: [])
                    : ($conv->messages ?? []);
                $lastMsg = end($messages);

                return [
                    '_id' => $serializedId,
                    'id' => $serializedId,
                    'title' => (string) ($conv->title ?: 'Conversación'),
                    'total_messages' => count($messages),
                    'last_message' => $lastMsg['content'] ?? '',
                    'updated_at' => $conv->updated_at ? $conv->updated_at->toIso8601String() : null,
                ];
            })->toArray();
        } catch (Throwable $e) {
            Log::error('Error en getUserConversations (MongoDB): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los mensajes de una conversación específica.
     */
    public function getConversation(int $userId, string $conversationId): array
    {
        try {
            $conv = AiConversation::where('user_id', (int) $userId)->find($conversationId);
            if (!$conv) {
                $conv = AiConversation::where('_id', $conversationId)
                    ->where('user_id', (int) $userId)
                    ->firstOrFail();
            }

            $serializedId = (string) ($conv->id ?? $conv->_id ?? $conv->getKey());
            $messages = is_string($conv->messages)
                ? (json_decode($conv->messages, true) ?: [])
                : ($conv->messages ?? []);

            return [
                '_id' => $serializedId,
                'id' => $serializedId,
                'title' => (string) ($conv->title ?: 'Conversación'),
                'messages' => array_values($messages),
                'context' => $conv->context ?? [],
                'created_at' => $conv->created_at ? $conv->created_at->toIso8601String() : null,
            ];
        } catch (Throwable $e) {
            Log::error('Error en getConversation (MongoDB): ' . $e->getMessage());
            throw new ModelNotFoundException("Conversación no encontrada.");
        }
    }
}
