<?php

namespace App\Services;

use App\Exceptions\InsufficientQuestionsException;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamConfig;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\UserTopicPerformance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ExamService
{
    /**
     * Configura y crea un nuevo simulacro o práctica e inicializa el intento.
     */
    public function configureExam(int $userId, array $data): array
    {
        $examType = $data['exam_type'] ?? 'quick';
        $difficulty = $data['difficulty'] ?? 'medium';
        $subjectIds = $data['subject_ids'] ?? [];
        $topicId = isset($data['topic_id']) ? (int) $data['topic_id'] : null;

        // Modo PRÁCTICA (Sprint E4.6: 25 preguntas por materia, selección atómica y persistencia inmutable)
        if ($examType === 'practice') {
            return $this->configurePracticeExam($userId, $subjectIds, $topicId, $difficulty);
        }

        // Modos QUICK y FULL (Simulacros existentes preservados)
        return DB::transaction(function () use ($userId, $examType, $difficulty, $subjectIds) {
            $config = ExamConfig::create([
                'user_id' => $userId,
                'exam_type' => $examType,
                'difficulty' => $difficulty,
            ]);

            if (!empty($subjectIds)) {
                $config->subjects()->sync($subjectIds);
            } else {
                $allSubjectIds = Subject::pluck('id')->toArray();
                $config->subjects()->sync($allSubjectIds);
            }

            $attempt = ExamAttempt::create([
                'user_id' => $userId,
                'exam_config_id' => $config->id,
                'mode' => $examType,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            return [
                'config' => $config->load('subjects'),
                'attempt' => $attempt,
            ];
        });
    }

    /**
     * Configura una práctica de 25 preguntas con selección atómica de grupos y persistencia inmutable.
     *
     * @throws InsufficientQuestionsException|InvalidArgumentException
     */
    protected function configurePracticeExam(int $userId, array $subjectIds, ?int $topicId, string $difficulty): array
    {
        if (empty($subjectIds)) {
            throw new InvalidArgumentException("Debes seleccionar una materia para iniciar una sesión de práctica.");
        }

        $subjectId = (int) $subjectIds[0];
        $subject = Subject::findOrFail($subjectId);

        if ($topicId) {
            $topic = Topic::where('id', $topicId)->where('subject_id', $subjectId)->first();
            if (!$topic) {
                throw new InvalidArgumentException("El tema seleccionado no pertenece a la materia indicada.");
            }
        }

        $targetCount = 25;

        // 1. Ejecutar algoritmo de selección atómica de reactivos
        $selection = $this->selectPracticeQuestions($subjectId, $topicId, $difficulty, $targetCount);
        $selectedQuestions = $selection['questions'];
        $difficultyMetadata = $selection['metadata'];

        // 2. Persistir configuración, intento y preguntas asignadas dentro de una transacción atómica
        return DB::transaction(function () use ($userId, $subjectId, $topicId, $difficulty, $targetCount, $selectedQuestions, $difficultyMetadata) {
            $config = ExamConfig::create([
                'user_id' => $userId,
                'topic_id' => $topicId,
                'exam_type' => 'practice',
                'difficulty' => $difficulty,
                'questions_count' => $targetCount,
            ]);

            $config->subjects()->sync([$subjectId]);

            $attempt = ExamAttempt::create([
                'user_id' => $userId,
                'exam_config_id' => $config->id,
                'mode' => 'practice',
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            // Persistir cada una de las 25 preguntas con su snapshot inmutable y clave correcta
            foreach ($selectedQuestions as $q) {
                $correctOption = $q->options->firstWhere('is_correct', true);

                $snapshotData = [
                    'question_id' => $q->id,
                    'statement' => $q->statement,
                    'question_image_url' => $q->question_image_url,
                    'explanation' => $q->explanation,
                    'difficulty' => $q->difficulty,
                    'source' => $q->source,
                    'source_pdf' => $q->source_pdf,
                    'source_page' => $q->source_page,
                    'original_question_number' => $q->original_question_number,
                    'subject' => [
                        'id' => $q->subject_id,
                        'name' => $q->subject->name ?? '',
                        'slug' => $q->subject->slug ?? '',
                    ],
                    'topic' => [
                        'id' => $q->topic_id,
                        'name' => $q->topic->name ?? '',
                        'slug' => $q->topic->slug ?? '',
                    ],
                    'stimulus' => $q->stimulus ? [
                        'id' => $q->stimulus->id,
                        'title' => $q->stimulus->title,
                        'instructions' => $q->stimulus->instructions,
                        'content_text' => $q->stimulus->content_text,
                        'media_type' => $q->stimulus->media_type,
                        'media_url' => $q->stimulus->media_url,
                        'media_caption' => $q->stimulus->media_caption,
                        'source_pdf' => $q->stimulus->source_pdf,
                        'source_page' => $q->stimulus->source_page,
                    ] : null,
                    'options' => $q->options->map(fn($opt) => [
                        'id' => $opt->id,
                        'option_letter' => $opt->option_letter,
                        'option_text' => $opt->option_text,
                        'option_image_url' => $opt->option_image_url,
                        'is_correct' => (bool) $opt->is_correct,
                    ])->values()->toArray(),
                    'correct_option_id' => $correctOption?->id,
                    'correct_option_letter' => $correctOption?->option_letter,
                ];

                AttemptAnswer::create([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $q->id,
                    'order_position' => $q->order_position,
                    'selected_option_id' => null,
                    'snapshot_correct_option_id' => $correctOption?->id,
                    'snapshot_data' => $snapshotData,
                    'is_correct' => false,
                    'time_spent_seconds' => 0,
                    'explanation_viewed' => false,
                ]);
            }

            return [
                'config' => $config->load('subjects'),
                'attempt' => $attempt,
                'metadata' => $difficultyMetadata,
            ];
        });
    }

    /**
     * Algoritmo de selección atómica de 25 preguntas para prácticas.
     * Respeta indivisibilidad de grupos de estímulo y aplica filtro de dificultad seguro.
     *
     * @throws InsufficientQuestionsException
     */
    public function selectPracticeQuestions(int $subjectId, ?int $topicId, ?string $difficulty, int $targetCount = 25): array
    {
        // 1. Consultar universo de preguntas publicadas válidas con clave correcta
        $baseQuery = Question::where('subject_id', $subjectId)
            ->published()
            ->when($topicId, fn($q) => $q->where('topic_id', $topicId))
            ->whereHas('options', fn($q) => $q->where('is_correct', true))
            ->with([
                'stimulus',
                'subject:id,name,slug',
                'topic:id,name,slug',
                'options' => fn($q) => $q->orderBy('option_letter', 'asc'),
            ]);

        $allEligible = $baseQuery->get();
        $totalAvailable = $allEligible->count();

        if ($totalAvailable < $targetCount) {
            throw new InsufficientQuestionsException(
                "La materia dispone únicamente de {$totalAvailable} preguntas publicadas válidas. Se requiere un mínimo de {$targetCount} preguntas para formar una práctica completa.",
                $totalAvailable,
                $targetCount
            );
        }

        // 2. Evaluar filtro de dificultad
        $difficultyMetadata = [
            'difficulty_applied' => $difficulty ?? 'all',
            'difficulty_fallback' => false,
        ];

        $pool = $allEligible;
        if ($difficulty && in_array($difficulty, ['easy', 'medium', 'hard'])) {
            $diffQuestions = $allEligible->where('difficulty', $difficulty);
            if ($diffQuestions->count() >= $targetCount && $this->canFormValidSet($diffQuestions, $targetCount)) {
                $pool = $diffQuestions;
                $difficultyMetadata['difficulty_applied'] = $difficulty;
            } else {
                // Fallback a dificultad mixta con aviso formativo
                $difficultyMetadata['difficulty_applied'] = 'mixed';
                $difficultyMetadata['difficulty_requested'] = $difficulty;
                $difficultyMetadata['difficulty_fallback'] = true;
                $difficultyMetadata['difficulty_reason'] = "Contenido insuficiente para dificultad '{$difficulty}'. Se amplió a dificultad mixta.";
                $pool = $allEligible;
            }
        }

        // 3. Estructurar unidades atómicas de selección
        $multiGroups = collect();
        $groupedByStimulus = $pool->whereNotNull('stimulus_id')->groupBy('stimulus_id');

        foreach ($groupedByStimulus as $stimulusId => $questions) {
            // Verificar que el grupo esté completo (todas las preguntas publicadas del estímulo están presentes)
            $totalInStimulus = Question::where('stimulus_id', $stimulusId)->published()->count();
            if ($questions->count() === $totalInStimulus) {
                $orderedQuestions = $questions->sortBy('order_in_group')->values();
                $multiGroups->push([
                    'stimulus_id' => $stimulusId,
                    'count' => $orderedQuestions->count(),
                    'questions' => $orderedQuestions,
                ]);
            }
        }

        $singletons = $pool->whereNull('stimulus_id')->values();

        // 4. Buscar combinación exacta de unidades que sume 25 preguntas
        $selectedQuestions = $this->findExactUnitsSubset($multiGroups, $singletons, $targetCount);

        if ($selectedQuestions === null) {
            throw new InsufficientQuestionsException(
                "No fue posible consolidar un conjunto exacto de {$targetCount} preguntas sin fragmentar grupos de estímulo compartido (disponibles: {$totalAvailable}).",
                $totalAvailable,
                $targetCount
            );
        }

        return [
            'questions' => $selectedQuestions,
            'metadata' => $difficultyMetadata,
        ];
    }

    /**
     * Determina si un conjunto de preguntas puede formar exactamente targetCount sin cortar grupos.
     */
    protected function canFormValidSet(Collection $pool, int $targetCount): bool
    {
        $multiGroups = collect();
        $groupedByStimulus = $pool->whereNotNull('stimulus_id')->groupBy('stimulus_id');

        foreach ($groupedByStimulus as $stimulusId => $questions) {
            $totalInStimulus = Question::where('stimulus_id', $stimulusId)->published()->count();
            if ($questions->count() === $totalInStimulus) {
                $multiGroups->push([
                    'stimulus_id' => $stimulusId,
                    'count' => $questions->count(),
                    'questions' => $questions,
                ]);
            }
        }

        $singletons = $pool->whereNull('stimulus_id')->values();

        return $this->findExactUnitsSubset($multiGroups, $singletons, $targetCount) !== null;
    }

    /**
     * Algoritmo combinatorio que selecciona unidades sin dividir clústeres.
     */
    protected function findExactUnitsSubset(Collection $multiGroups, Collection $singletons, int $target = 25): ?Collection
    {
        $singletonsCount = $singletons->count();
        $shuffledGroups = $multiGroups->shuffle()->values();

        // Buscar combinación de grupos enteros cuyo remanente pueda cubrirse con singletons
        $selectedGroupQuestions = $this->searchGroupCombination($shuffledGroups, 0, 0, $target, $singletonsCount);

        if ($selectedGroupQuestions === null) {
            return null;
        }

        $neededSingletons = $target - $selectedGroupQuestions->count();
        $chosenSingletons = $singletons->shuffle()->take($neededSingletons);

        // Agrupar en unidades para presentación
        $allUnits = collect();

        // Agregar clústeres de estímulo
        $groupedByStimulus = $selectedGroupQuestions->groupBy('stimulus_id');
        foreach ($groupedByStimulus as $group) {
            $allUnits->push($group->sortBy('order_in_group')->values());
        }

        // Agregar cada singleton como unidad independiente
        foreach ($chosenSingletons as $singleton) {
            $allUnits->push(collect([$singleton]));
        }

        // Mezclar las unidades respetando el orden interno de cada clúster
        $shuffledUnits = $allUnits->shuffle();

        $flatQuestions = collect();
        $pos = 1;
        foreach ($shuffledUnits as $unit) {
            foreach ($unit as $q) {
                $q->order_position = $pos++;
                $flatQuestions->push($q);
            }
        }

        return $flatQuestions;
    }

    /**
     * Búsqueda combinatoria recursiva para emparejamiento con singletons.
     */
    protected function searchGroupCombination(Collection $groups, int $index, int $currentWeight, int $target, int $availableSingletons): ?Collection
    {
        $remaining = $target - $currentWeight;
        if ($remaining >= 0 && $remaining <= $availableSingletons) {
            return collect();
        }

        if ($index >= $groups->count() || $currentWeight > $target) {
            return null;
        }

        $currentGroup = $groups[$index];
        $groupQuestions = $currentGroup['questions'];
        $groupWeight = $currentGroup['count'];

        // Opción 1: Incluir grupo actual
        if ($currentWeight + $groupWeight <= $target) {
            $sub = $this->searchGroupCombination($groups, $index + 1, $currentWeight + $groupWeight, $target, $availableSingletons);
            if ($sub !== null) {
                return $groupQuestions->concat($sub);
            }
        }

        // Opción 2: Omitir grupo actual
        return $this->searchGroupCombination($groups, $index + 1, $currentWeight, $target, $availableSingletons);
    }

    /**
     * Obtiene las preguntas correspondientes a un intento.
     * Si el intento ya tiene preguntas pre-asignadas (práctica), devuelve exactamente esas en su orden guardado.
     */
    public function getQuestions(int $attemptId, int $userId): array
    {
        $attempt = ExamAttempt::where('id', $attemptId)
            ->where('user_id', $userId)
            ->with(['examConfig.subjects'])
            ->firstOrFail();

        // Consultar preguntas persistidas en attempt_answers
        $persistedAnswers = AttemptAnswer::where('exam_attempt_id', $attempt->id)
            ->orderBy('order_position', 'asc')
            ->with(['question.subject', 'question.topic', 'question.stimulus', 'question.options'])
            ->get();

        if ($persistedAnswers->isNotEmpty()) {
            $formattedQuestions = $persistedAnswers->map(function ($ans) {
                // Si existe snapshot inmutable, usar los datos congelados
                if (!empty($ans->snapshot_data)) {
                    $snap = $ans->snapshot_data;
                    return [
                        'id' => $ans->question_id,
                        'order_position' => $ans->order_position,
                        'selected_option_id' => $ans->selected_option_id,
                        'statement' => $snap['statement'] ?? '',
                        'question_image_url' => $snap['question_image_url'] ?? null,
                        'difficulty' => $snap['difficulty'] ?? 'medium',
                        'subject' => $snap['subject'] ?? ['id' => null, 'name' => 'Materia', 'slug' => ''],
                        'topic' => $snap['topic'] ?? ['id' => null, 'name' => 'Tema', 'slug' => ''],
                        'stimulus' => $snap['stimulus'] ?? null,
                        'options' => array_map(function ($opt) {
                            return [
                                'id' => $opt['id'],
                                'option_letter' => $opt['option_letter'],
                                'option_text' => $opt['option_text'],
                                'option_image_url' => $opt['option_image_url'] ?? null,
                            ];
                        }, $snap['options'] ?? []),
                    ];
                }

                // Fallback para intentos pre-existentes sin snapshot
                $q = $ans->question;
                return [
                    'id' => $q->id,
                    'order_position' => $ans->order_position,
                    'selected_option_id' => $ans->selected_option_id,
                    'statement' => $q->statement,
                    'question_image_url' => $q->question_image_url,
                    'difficulty' => $q->difficulty,
                    'subject' => [
                        'id' => $q->subject->id ?? null,
                        'name' => $q->subject->name ?? 'Materia',
                        'slug' => $q->subject->slug ?? '',
                    ],
                    'topic' => [
                        'id' => $q->topic->id ?? null,
                        'name' => $q->topic->name ?? 'Tema',
                        'slug' => $q->topic->slug ?? '',
                    ],
                    'stimulus' => $q->stimulus ? [
                        'id' => $q->stimulus->id,
                        'title' => $q->stimulus->title,
                        'instructions' => $q->stimulus->instructions,
                        'content_text' => $q->stimulus->content_text,
                        'media_type' => $q->stimulus->media_type,
                        'media_url' => $q->stimulus->media_url,
                        'media_caption' => $q->stimulus->media_caption,
                    ] : null,
                    'options' => $q->options->map(fn($opt) => [
                        'id' => $opt->id,
                        'option_letter' => $opt->option_letter,
                        'option_text' => $opt->option_text,
                        'option_image_url' => $opt->option_image_url,
                    ])->values()->toArray(),
                ];
            });

            return [
                'attempt_id' => $attempt->id,
                'status' => $attempt->status,
                'total_questions' => $formattedQuestions->count(),
                'questions' => $formattedQuestions,
            ];
        }

        // Lógica de compatibilidad para simulacros QUICK y FULL sin persistencia anticipada
        $subjectIds = $attempt->examConfig->subjects->pluck('id')->toArray();
        $difficulty = $attempt->examConfig->difficulty;

        $limit = match ($attempt->mode) {
            'quick' => 5,
            'full' => 20,
            default => 10,
        };

        $query = Question::whereIn('subject_id', $subjectIds)
            ->with(['subject:id,name,slug', 'topic:id,name,slug', 'options']);

        if ($difficulty) {
            $query->where('difficulty', $difficulty);
        }

        $questions = $query->inRandomOrder()->limit($limit)->get();

        if ($questions->count() < $limit) {
            $questions = Question::whereIn('subject_id', $subjectIds)
                ->with(['subject:id,name,slug', 'topic:id,name,slug', 'options'])
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        }

        $formattedQuestions = $questions->map(function ($q) {
            return [
                'id' => $q->id,
                'statement' => $q->statement,
                'difficulty' => $q->difficulty,
                'subject' => [
                    'id' => $q->subject->id ?? null,
                    'name' => $q->subject->name ?? 'Materia',
                    'slug' => $q->subject->slug ?? '',
                ],
                'topic' => [
                    'id' => $q->topic->id ?? null,
                    'name' => $q->topic->name ?? 'Tema',
                    'slug' => $q->topic->slug ?? '',
                ],
                'options' => $q->options->map(fn($opt) => [
                    'id' => $opt->id,
                    'option_letter' => $opt->option_letter,
                    'option_text' => $opt->option_text,
                ]),
            ];
        });

        return [
            'attempt_id' => $attempt->id,
            'status' => $attempt->status,
            'total_questions' => $formattedQuestions->count(),
            'questions' => $formattedQuestions,
        ];
    }

    /**
     * Registra o actualiza la respuesta enviada por el estudiante.
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function submitAnswer(int $attemptId, int $userId, array $data): array
    {
        $attempt = ExamAttempt::where('id', $attemptId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // 1. Rechazar respuestas en intentos finalizados
        if ($attempt->status !== 'in_progress') {
            throw new RuntimeException("No es posible registrar o modificar respuestas en un simulacro finalizado.");
        }

        $questionId = (int) $data['question_id'];
        $selectedOptionId = isset($data['selected_option_id']) ? (int) $data['selected_option_id'] : null;
        $timeSpentSeconds = $data['time_spent_seconds'] ?? ($data['time'] ?? 0);

        // 2. Verificar asignación de la pregunta al intento
        $assignedAnswer = AttemptAnswer::where('exam_attempt_id', $attempt->id)
            ->where('question_id', $questionId)
            ->first();

        // Si el intento posee preguntas persistidas (práctica), la pregunta DEBE pertenecer al intento
        $hasPersistedQuestions = AttemptAnswer::where('exam_attempt_id', $attempt->id)->exists();
        if ($hasPersistedQuestions && !$assignedAnswer) {
            throw new InvalidArgumentException("La pregunta indicada no pertenece a este intento de examen.");
        }

        // Si no está persistida (simulacro legacy), validar que exista en Question
        if (!$assignedAnswer) {
            Question::findOrFail($questionId);
        }

        // 3. Validar que la opción seleccionada pertenezca a la pregunta
        $isCorrect = false;
        if ($selectedOptionId !== null) {
            $option = QuestionOption::where('id', $selectedOptionId)
                ->where('question_id', $questionId)
                ->first();

            if (!$option) {
                throw new InvalidArgumentException("La opción seleccionada no pertenece a la pregunta especificada.");
            }

            // Comparar contra el snapshot inmutable si está definido, o contra la opción actual
            if ($assignedAnswer && $assignedAnswer->snapshot_correct_option_id) {
                $isCorrect = ($selectedOptionId === $assignedAnswer->snapshot_correct_option_id);
            } else {
                $isCorrect = (bool) $option->is_correct;
            }
        }

        // 4. Guardar o actualizar sin duplicados
        if ($assignedAnswer) {
            $assignedAnswer->update([
                'selected_option_id' => $selectedOptionId,
                'is_correct' => $isCorrect,
                'time_spent_seconds' => $timeSpentSeconds,
            ]);
            $answer = $assignedAnswer;
        } else {
            $answer = AttemptAnswer::updateOrCreate(
                [
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $questionId,
                ],
                [
                    'selected_option_id' => $selectedOptionId,
                    'is_correct' => $isCorrect,
                    'time_spent_seconds' => $timeSpentSeconds,
                ]
            );
        }

        return [
            'answer_id' => $answer->id,
            'question_id' => $answer->question_id,
            'recorded' => true,
        ];
    }

    /**
     * Finaliza el simulacro, califica y calcula el rendimiento.
     * Es completamente idempotente: llamadas repetidas devuelven el resultado persistido.
     */
    public function finishExam(int $attemptId, int $userId): array
    {
        $attempt = ExamAttempt::where('id', $attemptId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Idempotencia: si ya está completado, no volver a calificar
        if ($attempt->status === 'completed') {
            return [
                'attempt_id' => $attempt->id,
                'status' => 'completed',
                'score_percentage' => $attempt->score,
                'total_questions' => $attempt->correct_answers + $attempt->incorrect_answers + $attempt->omitted_answers,
                'correct_answers' => $attempt->correct_answers,
                'incorrect_answers' => $attempt->incorrect_answers,
                'omitted_answers' => $attempt->omitted_answers,
                'duration_seconds' => $attempt->duration_seconds,
            ];
        }

        return DB::transaction(function () use ($attempt, $userId) {
            // Verificar si el intento tiene preguntas persistidas (práctica de 25 preguntas)
            $assignedAnswers = AttemptAnswer::where('exam_attempt_id', $attempt->id)
                ->orderBy('order_position', 'asc')
                ->get();

            if ($assignedAnswers->isNotEmpty()) {
                $totalQuestions = $assignedAnswers->count();
                $correctCount = 0;
                $incorrectCount = 0;
                $omittedCount = 0;
                $totalTimeSpent = 0;
                $topicStats = [];

                foreach ($assignedAnswers as $ans) {
                    $totalTimeSpent += $ans->time_spent_seconds;

                    // Extraer topic_id desde el snapshot o desde el modelo Question
                    $topicId = $ans->snapshot_data['topic']['id'] ?? ($ans->question->topic_id ?? null);
                    if ($topicId && !isset($topicStats[$topicId])) {
                        $topicStats[$topicId] = ['correct' => 0, 'incorrect' => 0, 'omitted' => 0];
                    }

                    if ($ans->selected_option_id === null) {
                        $omittedCount++;
                        if ($topicId) {
                            $topicStats[$topicId]['omitted']++;
                        }
                    } else {
                        // Determinar exactitud usando el snapshot de clave correcta
                        $isCorrect = $ans->snapshot_correct_option_id
                            ? ($ans->selected_option_id === $ans->snapshot_correct_option_id)
                            : (bool) $ans->is_correct;

                        if ($ans->is_correct !== $isCorrect) {
                            $ans->update(['is_correct' => $isCorrect]);
                        }

                        if ($isCorrect) {
                            $correctCount++;
                            if ($topicId) {
                                $topicStats[$topicId]['correct']++;
                            }
                        } else {
                            $incorrectCount++;
                            if ($topicId) {
                                $topicStats[$topicId]['incorrect']++;
                            }
                        }
                    }
                }

                $durationSeconds = now()->diffInSeconds($attempt->started_at);
                if ($durationSeconds < $totalTimeSpent) {
                    $durationSeconds = $totalTimeSpent;
                }

                $scorePercentage = $totalQuestions > 0 ? (int) round(($correctCount / $totalQuestions) * 100) : 0;

                $attempt->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'duration_seconds' => $durationSeconds,
                    'score' => $scorePercentage,
                    'correct_answers' => $correctCount,
                    'incorrect_answers' => $incorrectCount,
                    'omitted_answers' => $omittedCount,
                ]);

                // Actualizar rendimiento académico por tema
                foreach ($topicStats as $tId => $stats) {
                    $performance = UserTopicPerformance::firstOrNew([
                        'user_id' => $userId,
                        'topic_id' => $tId,
                    ]);

                    $performance->correct_count += $stats['correct'];
                    $performance->incorrect_count += $stats['incorrect'];
                    $performance->omitted_count += $stats['omitted'];

                    $totalTopicAns = $performance->correct_count + $performance->incorrect_count;
                    $masteryScore = $totalTopicAns > 0 ? round($performance->correct_count / $totalTopicAns, 2) : 0.00;

                    $performance->mastery_score = $masteryScore;
                    $performance->save();
                }

                return [
                    'attempt_id' => $attempt->id,
                    'status' => 'completed',
                    'score_percentage' => $scorePercentage,
                    'total_questions' => $totalQuestions,
                    'correct_answers' => $correctCount,
                    'incorrect_answers' => $incorrectCount,
                    'omitted_answers' => $omittedCount,
                    'duration_seconds' => $durationSeconds,
                ];
            }

            // Lógica de compatibilidad para simulacros QUICK y FULL sin preguntas pre-persistidas
            $subjectIds = $attempt->examConfig->subjects->pluck('id')->toArray();
            $difficulty = $attempt->examConfig->difficulty;

            $limit = match ($attempt->mode) {
                'quick' => 5,
                'full' => 20,
                default => 10,
            };

            $assignedQuestions = Question::whereIn('subject_id', $subjectIds)
                ->when($difficulty, fn($q) => $q->where('difficulty', $difficulty))
                ->limit($limit)
                ->get();

            if ($assignedQuestions->count() < $limit) {
                $assignedQuestions = Question::whereIn('subject_id', $subjectIds)->limit($limit)->get();
            }

            $totalQuestions = $assignedQuestions->count();
            $answers = AttemptAnswer::where('exam_attempt_id', $attempt->id)->get()->keyBy('question_id');

            $correctCount = 0;
            $incorrectCount = 0;
            $omittedCount = 0;
            $totalTimeSpent = 0;
            $topicStats = [];

            foreach ($assignedQuestions as $q) {
                $topicId = $q->topic_id;
                if (!isset($topicStats[$topicId])) {
                    $topicStats[$topicId] = ['correct' => 0, 'incorrect' => 0, 'omitted' => 0];
                }

                if (isset($answers[$q->id])) {
                    $ans = $answers[$q->id];
                    $totalTimeSpent += $ans->time_spent_seconds;

                    if ($ans->selected_option_id === null) {
                        $omittedCount++;
                        $topicStats[$topicId]['omitted']++;
                    } elseif ($ans->is_correct) {
                        $correctCount++;
                        $topicStats[$topicId]['correct']++;
                    } else {
                        $incorrectCount++;
                        $topicStats[$topicId]['incorrect']++;
                    }
                } else {
                    $omittedCount++;
                    $topicStats[$topicId]['omitted']++;
                }
            }

            $durationSeconds = now()->diffInSeconds($attempt->started_at);
            if ($durationSeconds < $totalTimeSpent) {
                $durationSeconds = $totalTimeSpent;
            }

            $scorePercentage = $totalQuestions > 0 ? (int) round(($correctCount / $totalQuestions) * 100) : 0;

            $attempt->update([
                'status' => 'completed',
                'finished_at' => now(),
                'duration_seconds' => $durationSeconds,
                'score' => $scorePercentage,
                'correct_answers' => $correctCount,
                'incorrect_answers' => $incorrectCount,
                'omitted_answers' => $omittedCount,
            ]);

            foreach ($topicStats as $topicId => $stats) {
                $performance = UserTopicPerformance::firstOrNew([
                    'user_id' => $userId,
                    'topic_id' => $topicId,
                ]);

                $performance->correct_count += $stats['correct'];
                $performance->incorrect_count += $stats['incorrect'];
                $performance->omitted_count += $stats['omitted'];

                $totalTopicAns = $performance->correct_count + $performance->incorrect_count;
                $masteryScore = $totalTopicAns > 0 ? round($performance->correct_count / $totalTopicAns, 2) : 0.00;

                $performance->mastery_score = $masteryScore;
                $performance->save();
            }

            return [
                'attempt_id' => $attempt->id,
                'status' => 'completed',
                'score_percentage' => $scorePercentage,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctCount,
                'incorrect_answers' => $incorrectCount,
                'omitted_answers' => $omittedCount,
                'duration_seconds' => $durationSeconds,
            ];
        });
    }

    /**
     * Obtiene el reporte de resultados completo de un simulacro o práctica finalizada.
     * Incluye todas las preguntas asignadas, incluidas las omitidas.
     */
    public function getExamResults(int $attemptId, int $userId): array
    {
        $attempt = ExamAttempt::where('id', $attemptId)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->with(['examConfig.subjects'])
            ->firstOrFail();

        $answers = AttemptAnswer::where('exam_attempt_id', $attempt->id)
            ->orderBy('order_position', 'asc')
            ->with([
                'question.subject',
                'question.topic',
                'question.stimulus',
                'question.options',
                'selectedOption',
                'snapshotCorrectOption',
            ])
            ->get();

        $subjectBreakdown = [];
        $topicBreakdown = [];

        foreach ($answers as $ans) {
            // Extraer metadatos desde snapshot o relaciones Eloquent
            $subjectName = $ans->snapshot_data['subject']['name'] ?? ($ans->question->subject->name ?? 'Materia');
            $subjectId = $ans->snapshot_data['subject']['id'] ?? ($ans->question->subject_id ?? 0);
            $topicName = $ans->snapshot_data['topic']['name'] ?? ($ans->question->topic->name ?? 'Tema');
            $topicId = $ans->snapshot_data['topic']['id'] ?? ($ans->question->topic_id ?? 0);

            if (!isset($subjectBreakdown[$subjectId])) {
                $subjectBreakdown[$subjectId] = [
                    'subject_id' => $subjectId,
                    'subject_name' => $subjectName,
                    'total' => 0,
                    'correct' => 0,
                    'incorrect' => 0,
                    'omitted' => 0,
                ];
            }

            if (!isset($topicBreakdown[$topicId])) {
                $topicBreakdown[$topicId] = [
                    'topic_id' => $topicId,
                    'topic_name' => $topicName,
                    'subject_name' => $subjectName,
                    'total' => 0,
                    'correct' => 0,
                    'incorrect' => 0,
                    'omitted' => 0,
                ];
            }

            $subjectBreakdown[$subjectId]['total']++;
            $topicBreakdown[$topicId]['total']++;

            if ($ans->selected_option_id === null) {
                $subjectBreakdown[$subjectId]['omitted']++;
                $topicBreakdown[$topicId]['omitted']++;
            } elseif ($ans->is_correct) {
                $subjectBreakdown[$subjectId]['correct']++;
                $topicBreakdown[$topicId]['correct']++;
            } else {
                $subjectBreakdown[$subjectId]['incorrect']++;
                $topicBreakdown[$topicId]['incorrect']++;
            }
        }

        $formattedSubjects = array_values(array_map(function ($s) {
            $s['score_percentage'] = $s['total'] > 0 ? round(($s['correct'] / $s['total']) * 100) : 0;
            return $s;
        }, $subjectBreakdown));

        $formattedTopics = array_values(array_map(function ($t) {
            $t['score_percentage'] = $t['total'] > 0 ? round(($t['correct'] / $t['total']) * 100) : 0;
            return $t;
        }, $topicBreakdown));

        // Mapeo detallado de cada pregunta asignada (incluyendo omitidas)
        $questionsDetail = $answers->map(function ($ans) {
            // Si tiene snapshot congelado, usarlo fielmente
            if (!empty($ans->snapshot_data)) {
                $snap = $ans->snapshot_data;

                $userSelectedOption = null;
                if ($ans->selected_option_id !== null) {
                    $foundOpt = collect($snap['options'])->firstWhere('id', $ans->selected_option_id);
                    if ($foundOpt) {
                        $userSelectedOption = [
                            'id' => $foundOpt['id'],
                            'option_letter' => $foundOpt['option_letter'],
                            'option_text' => $foundOpt['option_text'],
                            'option_image_url' => $foundOpt['option_image_url'] ?? null,
                        ];
                    }
                }

                $correctOption = null;
                $correctOptId = $ans->snapshot_correct_option_id ?? ($snap['correct_option_id'] ?? null);
                if ($correctOptId) {
                    $foundOpt = collect($snap['options'])->firstWhere('id', $correctOptId);
                    if ($foundOpt) {
                        $correctOption = [
                            'id' => $foundOpt['id'],
                            'option_letter' => $foundOpt['option_letter'],
                            'option_text' => $foundOpt['option_text'],
                            'option_image_url' => $foundOpt['option_image_url'] ?? null,
                        ];
                    }
                }

                return [
                    'question_id' => $ans->question_id,
                    'order_position' => $ans->order_position,
                    'statement' => $snap['statement'] ?? '',
                    'question_image_url' => $snap['question_image_url'] ?? null,
                    'explanation' => !empty($snap['explanation']) ? $snap['explanation'] : null,
                    'difficulty' => $snap['difficulty'] ?? 'medium',
                    'subject_name' => $snap['subject']['name'] ?? '',
                    'topic_name' => $snap['topic']['name'] ?? '',
                    'stimulus' => $snap['stimulus'] ?? null,
                    'user_selected_option' => $userSelectedOption,
                    'correct_option' => $correctOption,
                    'is_correct' => (bool) $ans->is_correct,
                    'time_spent_seconds' => $ans->time_spent_seconds,
                ];
            }

            // Fallback para intentos legacy
            $q = $ans->question;
            $correctOption = $q->options->where('is_correct', true)->first();

            return [
                'question_id' => $q->id,
                'order_position' => $ans->order_position,
                'statement' => $q->statement,
                'explanation' => !empty($q->explanation) ? $q->explanation : null,
                'difficulty' => $q->difficulty,
                'subject_name' => $q->subject->name ?? '',
                'topic_name' => $q->topic->name ?? '',
                'stimulus' => $q->stimulus ? [
                    'id' => $q->stimulus->id,
                    'title' => $q->stimulus->title,
                    'instructions' => $q->stimulus->instructions,
                    'content_text' => $q->stimulus->content_text,
                    'media_type' => $q->stimulus->media_type,
                    'media_url' => $q->stimulus->media_url,
                    'media_caption' => $q->stimulus->media_caption,
                ] : null,
                'user_selected_option' => $ans->selectedOption ? [
                    'id' => $ans->selectedOption->id,
                    'option_letter' => $ans->selectedOption->option_letter,
                    'option_text' => $ans->selectedOption->option_text,
                ] : null,
                'correct_option' => $correctOption ? [
                    'id' => $correctOption->id,
                    'option_letter' => $correctOption->option_letter,
                    'option_text' => $correctOption->option_text,
                ] : null,
                'is_correct' => (bool) $ans->is_correct,
                'time_spent_seconds' => $ans->time_spent_seconds,
            ];
        });

        return [
            'attempt_id' => $attempt->id,
            'exam_type' => $attempt->mode,
            'status' => $attempt->status,
            'started_at' => $attempt->started_at->toDateTimeString(),
            'finished_at' => $attempt->finished_at ? $attempt->finished_at->toDateTimeString() : null,
            'duration_seconds' => $attempt->duration_seconds,
            'score_percentage' => $attempt->score,
            'total_questions' => $attempt->correct_answers + $attempt->incorrect_answers + $attempt->omitted_answers,
            'correct_answers' => $attempt->correct_answers,
            'incorrect_answers' => $attempt->incorrect_answers,
            'omitted_answers' => $attempt->omitted_answers,
            'subject_performance' => $formattedSubjects,
            'topic_performance' => $formattedTopics,
            'questions_detail' => $questionsDetail,
        ];
    }
}
