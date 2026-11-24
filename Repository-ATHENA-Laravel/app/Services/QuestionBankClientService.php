<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class QuestionBankClientService
{
    protected string $baseUrl;
    protected string $adminKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.question_bank.base_url', 'http://127.0.0.1:8001/api/v1'), '/');
        $this->adminKey = config('services.question_bank.admin_key', 'athena_iqpe_super_secret_admin_key_2026');
        $this->timeout = (int) config('services.question_bank.timeout', 10);
    }

    /**
     * Verifica la disponibilidad del microservicio Question Bank.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Consulta preguntas oficiales directamente desde Question Bank para un área específica.
     * Retorna la lista formateada con reactivo, estímulo, opciones y clave oficial verificada.
     *
     * @param int|string $subjectId ID del área en Question Bank o Core
     * @param int $limit Cantidad máxima de preguntas a recuperar
     * @param string|null $difficulty Dificultad deseada
     * @return array
     */
    public function getQuestionsBySubject($subjectId, int $limit = 25, ?string $difficulty = null): array
    {
        try {
            $params = [
                'subject_id' => $subjectId,
                'status'     => 'published',
                'per_page'   => min($limit * 2, 50),
            ];

            if ($difficulty && in_array($difficulty, ['easy', 'medium', 'hard'])) {
                $params['difficulty'] = $difficulty;
            }

            // Llamada M2M administrativa para obtener también la clave correcta verificada
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Athena-Admin-Key' => $this->adminKey,
                    'Accept'             => 'application/json',
                ])
                ->get("{$this->baseUrl}/admin/questions", $params);

            if (!$response->successful()) {
                Log::warning("QuestionBankClientService: Error HTTP {$response->status()} al consultar preguntas.");
                return [];
            }

            $json = $response->json();
            $items = $json['data'] ?? [];

            // Si es un paginador anidado
            if (isset($items['data']) && is_array($items['data'])) {
                $items = $items['data'];
            }

            $formatted = [];
            foreach ($items as $item) {
                // Verificar que tenga al menos una opción correcta
                $options = $item['options'] ?? [];
                $hasCorrect = false;
                foreach ($options as $opt) {
                    if (!empty($opt['is_correct'])) {
                        $hasCorrect = true;
                        break;
                    }
                }

                if (!$hasCorrect) {
                    continue;
                }

                $formatted[] = [
                    'source'             => 'question_bank',
                    'qb_question_id'     => $item['id'] ?? null,
                    'statement'          => $item['statement'] ?? '',
                    'difficulty'         => $item['difficulty'] ?? 'medium',
                    'subject_id'         => $item['subject_id'] ?? $subjectId,
                    'topic_id'           => $item['topic_id'] ?? null,
                    'stimulus_id'        => $item['stimulus_id'] ?? null,
                    'stimulus'           => !empty($item['stimulus']) ? [
                        'id'           => $item['stimulus']['id'] ?? null,
                        'title'        => $item['stimulus']['title'] ?? null,
                        'instructions' => $item['stimulus']['instructions'] ?? null,
                        'content_text' => $item['stimulus']['content_text'] ?? null,
                        'media_type'   => $item['stimulus']['media_type'] ?? null,
                    ] : null,
                    'options'            => array_map(function ($opt) {
                        return [
                            'id'            => $opt['id'] ?? null,
                            'option_letter' => $opt['option_letter'] ?? 'A',
                            'option_text'   => $opt['option_text'] ?? '',
                            'is_correct'    => !empty($opt['is_correct']),
                        ];
                    }, $options),
                ];

                if (count($formatted) >= $limit) {
                    break;
                }
            }

            return $formatted;
        } catch (Throwable $e) {
            Log::error("QuestionBankClientService Exception: " . $e->getMessage());
            return [];
        }
    }
}
