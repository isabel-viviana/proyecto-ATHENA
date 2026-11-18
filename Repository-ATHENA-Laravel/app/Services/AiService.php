<?php

namespace App\Services;

use App\Models\Mongo\AiConversation;
use App\Models\Mongo\AiInteraction;
use App\Models\Mongo\AiLearningContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
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
        // 1. Obtener o crear conversación en MongoDB (con respaldo de aislamiento por usuario en Cache)
        $conversation = null;
        if (!empty($conversationId)) {
            $mongoFound = null;
            try {
                $mongoFound = AiConversation::where('_id', $conversationId)->first();
            } catch (Throwable $e) {
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
                        'title' => $cachedConv['title'] ?? Str::limit(trim($userMessage), 35, '...'),
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

        // 5. Guardar mensajes en MongoDB y sincronizar registro de propietario en Cache
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

        $convIdStr = (string) $conversation->_id;
        $existingCache = Cache::get("ai_conversation:{$convIdStr}");
        $createdAtStr = is_array($existingCache) && !empty($existingCache['created_at'])
            ? $existingCache['created_at']
            : ($conversation->created_at ? $conversation->created_at->toDateTimeString() : now()->toDateTimeString());

        Cache::put("ai_conversation:{$convIdStr}", [
            'id' => $convIdStr,
            'user_id' => $userId,
            'title' => $conversation->title,
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
            // Manejar error de MongoDB sin comprometer la respuesta
        }

        // 6. Registrar interacciones y snapshot documental en MongoDB
        try {
            AiInteraction::create([
                'user_id' => $userId,
                'conversation_id' => $convIdStr,
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
            'conversation_id' => $convIdStr,
            'title' => $conversation->title,
            'message' => $userMessage,
            'ai_response' => $aiResponse,
        ];
    }

    /**
     * Obtiene el listado de conversaciones del estudiante desde MongoDB (con respaldo aislado por usuario en Cache).
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

                $results[] = [
                    'id' => (string) ($cachedConv['id'] ?? $convIdStr),
                    'title' => $cachedConv['title'] ?? '',
                    'total_messages' => count($messages),
                    'last_message' => is_array($lastMsg) ? ($lastMsg['content'] ?? '') : '',
                    'updated_at' => $cachedConv['updated_at'] ?? null,
                ];
            }

            return $results;
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
            $cachedConv = Cache::get("ai_conversation:{$conversationId}");
            if ($cachedConv !== null && (int) ($cachedConv['user_id'] ?? 0) === $userId) {
                return [
                    'id' => $cachedConv['id'],
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
