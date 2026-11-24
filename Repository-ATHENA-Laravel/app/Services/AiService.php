<?php

namespace App\Services;

use App\Models\Mongo\ChatHistory;
use App\Models\Mongo\AiConversation;
use App\Models\Mongo\AiInteraction;
use App\Models\Mongo\AiLearningContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
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
     * Procesa un mensaje enviado por el estudiante a ATHENA.
     * Persiste en MongoDB (ChatHistory y AiConversation) y mantiene respaldo aislado por usuario en Cache.
     */
    public function sendMessage(int $userId, string $userMessage, ?string $conversationId = null): array
    {
        $userId = (int) $userId;
        $titleSnippet = Str::limit(trim($userMessage), 35, '...');

        // 1. Obtener o crear conversación en MongoDB (con respaldo de aislamiento por usuario en Cache)
        $conversation = null;
        if (!empty($conversationId)) {
            $mongoFound = null;
            try {
                $mongoFound = AiConversation::find($conversationId);
                if (!$mongoFound) {
                    $mongoFound = AiConversation::where('_id', $conversationId)->first();
                }
            } catch (Throwable $e) {
                Log::error('Error consultando AiConversation en MongoDB: ' . $e->getMessage());
                $mongoFound = null;
            }

            if ($mongoFound) {
                if ((int) $mongoFound->user_id !== $userId) {
                    throw new ModelNotFoundException('La conversación no pertenece al usuario autenticado.');
                }
                $conversation = $mongoFound;
            } else {
                $cachedConv = Cache::get("ai_conversation:{$conversationId}");
                if ($cachedConv !== null) {
                    if ((int) ($cachedConv['user_id'] ?? 0) !== $userId) {
                        throw new ModelNotFoundException('La conversación no pertenece al usuario autenticado.');
                    }
                    $conversation = new AiConversation([
                        'user_id' => $userId,
                        'title' => $cachedConv['title'] ?? $titleSnippet,
                        'messages' => $cachedConv['messages'] ?? [],
                        'context' => $cachedConv['context'] ?? [],
                    ]);
                    $conversation->_id = $conversationId;
                } else {
                    throw new ModelNotFoundException('Conversación no encontrada o inaccesible para este usuario.');
                }
            }
        }

        $academicContextData = $this->aiContextService->buildAcademicContext($userId);
        $formattedContext = $this->aiContextService->formatContextForPrompt($academicContextData);

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
                $conversation = new AiConversation([
                    'user_id' => $userId,
                    'title' => $titleSnippet,
                    'messages' => [],
                    'context' => $academicContextData,
                ]);
                $conversation->_id = (string) Str::uuid();
            }
        }

        $convIdStr = (string) ($conversation->id ?? $conversation->_id ?? $conversation->getKey() ?? Str::uuid());

        // 2. Consultar en MongoDB (ChatHistory o AiConversation) los últimos 10 mensajes cronológicos
        $existingMessages = is_string($conversation->messages)
            ? (json_decode($conversation->messages, true) ?: [])
            : ($conversation->messages ?? []);

        $recentMessages = [];
        try {
            $historyDocs = ChatHistory::where('user_id', $userId)
                ->where('conversation_id', $convIdStr)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->reverse()
                ->values();

            foreach ($historyDocs as $doc) {
                $recentMessages[] = [
                    'role' => $doc->role === 'model' ? 'model' : 'user',
                    'content' => (string) $doc->content,
                ];
            }
        } catch (Throwable $e) {
            Log::error('Error consultando ChatHistory en MongoDB: ' . $e->getMessage());
        }

        if (empty($recentMessages) && !empty($existingMessages)) {
            $recentMessages = array_slice($existingMessages, -10);
        }

        $formattedHistory = "";
        if (!empty($recentMessages)) {
            $formattedHistory .= "\nHISTORIAL RECIENTE DE LA CONVERSACIÓN:\n";
            foreach ($recentMessages as $msg) {
                $roleLabel = ($msg['role'] ?? 'user') === 'user' ? 'Estudiante' : 'ATHENA';
                $formattedHistory .= "{$roleLabel}: " . ($msg['content'] ?? '') . "\n";
            }
        }

        // 3. Construir Prompt completo con contexto e historial y enviarlo a Gemini
        $fullPrompt = $this->getSystemPrompt() . "\n\n"
            . $formattedContext . "\n"
            . $formattedHistory . "\n"
            . "MENSAJE ACTUAL DEL ESTUDIANTE:\n"
            . "\"{$userMessage}\"\n\n"
            . "RESPUESTA DE ATHENA:";

        $aiResponse = $this->geminiService->generateText($fullPrompt);

        // 4. Guardar mensajes en ChatHistory (MongoDB)
        try {
            ChatHistory::create([
                'user_id' => $userId,
                'conversation_id' => $convIdStr,
                'role' => 'user',
                'content' => $userMessage,
            ]);

            ChatHistory::create([
                'user_id' => $userId,
                'conversation_id' => $convIdStr,
                'role' => 'model',
                'content' => $aiResponse,
            ]);
        } catch (Throwable $e) {
            Log::error('Error guardando en MongoDB (ChatHistory): ' . $e->getMessage());
        }

        // 5. Guardar mensajes en AiConversation (MongoDB) y sincronizar respaldo aislado en Cache
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

        $existingMessages[] = $userMsgStruct;
        $existingMessages[] = $aiMsgStruct;

        $existingCache = Cache::get("ai_conversation:{$convIdStr}");
        $createdAtStr = is_array($existingCache) && !empty($existingCache['created_at'])
            ? $existingCache['created_at']
            : ($conversation->created_at ? $conversation->created_at->toDateTimeString() : now()->toDateTimeString());

        Cache::put("ai_conversation:{$convIdStr}", [
            '_id' => $convIdStr,
            'id' => $convIdStr,
            'user_id' => $userId,
            'title' => $conversation->title ?? $titleSnippet,
            'messages' => $existingMessages,
            'context' => $academicContextData,
            'created_at' => $createdAtStr,
            'updated_at' => now()->toDateTimeString(),
        ], 86400);

        $userConvIndex = Cache::get("ai_user_conversations:{$userId}", []);
        if (!is_array($userConvIndex)) {
            $userConvIndex = [];
        }
        $userConvIndex = array_values(array_diff($userConvIndex, [$convIdStr]));
        array_unshift($userConvIndex, $convIdStr);
        Cache::put("ai_user_conversations:{$userId}", $userConvIndex, 86400);

        try {
            $conversation->messages = $existingMessages;
            $conversation->context = $academicContextData;
            $conversation->save();
        } catch (Throwable $e) {
            Log::error('Error guardando en MongoDB (AiConversation::save): ' . $e->getMessage());
        }

        try {
            AiInteraction::create([
                'user_id' => $userId,
                'conversation_id' => $convIdStr,
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
            // Capturar silenciosamente cualquier error en colecciones secundarias
        }

        return [
            'conversation_id' => $convIdStr,
            'title' => $conversation->title ?? $titleSnippet,
            'message' => $userMessage,
            'ai_response' => $aiResponse,
        ];
    }

    /**
     * Obtiene el listado de conversaciones del estudiante desde MongoDB (con respaldo aislado por usuario en Cache).
     */
    public function getUserConversations(int $userId): array
    {
        $userId = (int) $userId;

        try {
            $conversations = AiConversation::where('user_id', $userId)
                ->orderBy('updated_at', 'desc')
                ->get();

            return $conversations->map(function ($conv) {
                $serializedId = (string) ($conv->id ?? $conv->_id ?? $conv->getKey());
                $messages = is_string($conv->messages)
                    ? (json_decode($conv->messages, true) ?: [])
                    : ($conv->messages ?? []);
                $lastMsg = !empty($messages) ? end($messages) : null;

                return [
                    '_id' => $serializedId,
                    'id' => $serializedId,
                    'title' => (string) ($conv->title ?: 'Conversación'),
                    'total_messages' => count($messages),
                    'last_message' => is_array($lastMsg) ? ($lastMsg['content'] ?? '') : '',
                    'updated_at' => $conv->updated_at ? $conv->updated_at->toDateTimeString() : null,
                ];
            })->toArray();
        } catch (Throwable $e) {
            Log::error('Error en getUserConversations (MongoDB): ' . $e->getMessage());

            $userConvIndex = Cache::get("ai_user_conversations:{$userId}", []);
            if (!is_array($userConvIndex) || empty($userConvIndex)) {
                return [];
            }

            $results = [];
            $seenIds = [];

            foreach ($userConvIndex as $convId) {
                $convIdStr = (string) $convId;
                if ($convIdStr === '' || isset($seenIds[$convIdStr])) {
                    continue;
                }

                $cachedConv = Cache::get("ai_conversation:{$convIdStr}");
                if (!is_array($cachedConv) || (int) ($cachedConv['user_id'] ?? 0) !== $userId) {
                    continue;
                }

                $seenIds[$convIdStr] = true;
                $messages = is_array($cachedConv['messages'] ?? null) ? $cachedConv['messages'] : [];
                $lastMsg = !empty($messages) ? end($messages) : null;
                $resolvedId = (string) ($cachedConv['id'] ?? $convIdStr);

                $results[] = [
                    '_id' => $resolvedId,
                    'id' => $resolvedId,
                    'title' => $cachedConv['title'] ?? 'Conversación',
                    'total_messages' => count($messages),
                    'last_message' => is_array($lastMsg) ? ($lastMsg['content'] ?? '') : '',
                    'updated_at' => $cachedConv['updated_at'] ?? null,
                ];
            }

            return $results;
        }
    }

    /**
     * Obtiene los mensajes de una conversación específica validando pertenencia al usuario.
     */
    public function getConversation(int $userId, string $conversationId): array
    {
        $userId = (int) $userId;

        try {
            $conv = AiConversation::where('user_id', $userId)->find($conversationId);
            if (!$conv) {
                $conv = AiConversation::where('_id', $conversationId)
                    ->where('user_id', $userId)
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
                'created_at' => $conv->created_at ? $conv->created_at->toDateTimeString() : null,
            ];
        } catch (Throwable $e) {
            $cachedConv = Cache::get("ai_conversation:{$conversationId}");
            if ($cachedConv !== null && (int) ($cachedConv['user_id'] ?? 0) === $userId) {
                $resolvedId = (string) ($cachedConv['id'] ?? $conversationId);
                return [
                    '_id' => $resolvedId,
                    'id' => $resolvedId,
                    'title' => $cachedConv['title'],
                    'messages' => $cachedConv['messages'] ?? [],
                    'context' => $cachedConv['context'] ?? [],
                    'created_at' => $cachedConv['created_at'] ?? null,
                ];
            }

            throw new ModelNotFoundException("Conversación no encontrada.");
        }
    }
}
