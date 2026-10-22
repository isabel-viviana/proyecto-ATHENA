<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Stimulus;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MathematicsPracticeQuestionsSeeder extends Seeder
{
    /**
     * Completa temporalmente el banco de preguntas de Matemáticas para alcanzar
     * el mínimo de 25 preguntas válidas requeridas para el módulo de prácticas.
     */
    public function run(): void
    {
        $subject = Subject::where('slug', 'matematicas')->first();

        if (!$subject) {
            $this->command?->error("No se encontró la materia Matemáticas en la base de datos.");
            return;
        }

        $validCount = Question::where('subject_id', $subject->id)
            ->published()
            ->whereHas('options', fn($q) => $q->where('is_correct', true))
            ->count();

        $target = 25;
        if ($validCount >= $target) {
            $this->command?->info("Matemáticas ya dispone de {$validCount} preguntas publicadas válidas (mínimo requerido: {$target}). No se realizaron modificaciones.");
            return;
        }

        $needed = $target - $validCount;
        $this->command?->info("Matemáticas dispone actualmente de {$validCount} preguntas válidas. Incorporando {$needed} preguntas de prueba estructuradas...");

        $topics = Topic::where('subject_id', $subject->id)->get()->keyBy('slug');
        $statTopic = $topics->get('estadistica-y-probabilidad') ?? $topics->first();
        $trigTopic = $topics->get('trigonometria-basica') ?? $topics->first();
        $algTopic = $topics->get('ecuaciones-lineales') ?? $topics->first();

        DB::transaction(function () use ($subject, $statTopic, $trigTopic, $algTopic) {
            // 1. Estímulo indivisible de prueba para Estadística y Probabilidad
            $stimulus = Stimulus::firstOrCreate(
                [
                    'subject_id' => $subject->id,
                    'title' => 'Distribución de Horas de Estudio y Rendimiento Académico (Datos de Prueba ATHENA)',
                ],
                [
                    'instructions' => 'Responda las preguntas asociadas con base en la información del estudio muestral.',
                    'content_text' => "En un estudio piloto con 100 estudiantes que preparan la prueba de Estado, se analizaron las horas semanales de estudio independiente y el puntaje obtenido en los simulacros diagnósticos:\n- El 30% de los estudiantes dedicó menos de 5 horas semanales, obteniendo un puntaje medio de 52 puntos.\n- El 50% de los estudiantes dedicó entre 5 y 10 horas semanales, obteniendo un puntaje medio de 71 puntos.\n- El 20% de los estudiantes dedicó más de 10 horas semanales, obteniendo un puntaje medio de 89 puntos.",
                    'media_type' => 'none',
                    'source_pdf' => null,
                    'source_page' => null,
                ]
            );

            // 2. Definición estructurada de las 10 preguntas de prueba faltantes
            $questionsData = [
                // --- Grupo con Estímulo Compartido (Estadística, 2 preguntas indivisibles) ---
                [
                    'topic_id' => $statTopic->id,
                    'stimulus_id' => $stimulus->id,
                    'order_in_group' => 1,
                    'statement' => '[Prueba ATHENA - Estadística] De acuerdo con la información del estudio muestral, ¿cuál es el porcentaje de estudiantes que dedicó al menos 5 horas semanales de estudio independiente?',
                    'difficulty' => 'medium',
                    'explanation' => 'Los estudiantes que dedicaron al menos 5 horas corresponden a los tramos de 5 a 10 horas (50%) y más de 10 horas (20%). Sumando ambos grupos: 50% + 20% = 70%.',
                    'options' => [
                        ['letter' => 'A', 'text' => '30%', 'is_correct' => false],
                        ['letter' => 'B', 'text' => '50%', 'is_correct' => false],
                        ['letter' => 'C', 'text' => '70%', 'is_correct' => true],
                        ['letter' => 'D', 'text' => '80%', 'is_correct' => false],
                    ],
                ],
                [
                    'topic_id' => $statTopic->id,
                    'stimulus_id' => $stimulus->id,
                    'order_in_group' => 2,
                    'statement' => '[Prueba ATHENA - Estadística] Si se selecciona al azar un estudiante del grupo evaluado, ¿cuál es la probabilidad de que pertenezca al segmento con puntaje medio de 89 puntos?',
                    'difficulty' => 'easy',
                    'explanation' => 'El grupo con puntaje medio de 89 puntos corresponde al 20% de la muestra de 100 estudiantes. Por consiguiente, la probabilidad es 20/100 = 0.20.',
                    'options' => [
                        ['letter' => 'A', 'text' => '0.20', 'is_correct' => true],
                        ['letter' => 'B', 'text' => '0.30', 'is_correct' => false],
                        ['letter' => 'C', 'text' => '0.50', 'is_correct' => false],
                        ['letter' => 'D', 'text' => '0.89', 'is_correct' => false],
                    ],
                ],

                // --- Pregunta independiente de Estadística (1 singleton) ---
                [
                    'topic_id' => $statTopic->id,
                    'stimulus_id' => null,
                    'order_in_group' => 1,
                    'statement' => '[Prueba ATHENA - Estadística] En un conjunto ordenado de seis calificaciones {4, 6, 7, 8, 9, 10}, ¿cuál es el valor de la mediana?',
                    'difficulty' => 'medium',
                    'explanation' => 'Al haber un número par de datos (6), la mediana se calcula como el promedio de los dos datos centrales en las posiciones 3 y 4 (7 y 8): (7 + 8) / 2 = 7.5.',
                    'options' => [
                        ['letter' => 'A', 'text' => '7.0', 'is_correct' => false],
                        ['letter' => 'B', 'text' => '7.5', 'is_correct' => true],
                        ['letter' => 'C', 'text' => '8.0', 'is_correct' => false],
                        ['letter' => 'D', 'text' => '8.5', 'is_correct' => false],
                    ],
                ],

                // --- Preguntas independientes de Trigonometría Básica (3 singletons) ---
                [
                    'topic_id' => $trigTopic->id,
                    'stimulus_id' => null,
                    'order_in_group' => 1,
                    'statement' => '[Prueba ATHENA - Trigonometría] En un triángulo rectángulo, si el cateto opuesto a un ángulo agudo θ mide 3 cm y la hipotenusa mide 5 cm, ¿cuál es el valor de sen(θ)?',
                    'difficulty' => 'easy',
                    'explanation' => 'Por definición trigonométrica básica, el seno de un ángulo agudo equivale a la razón entre el cateto opuesto y la hipotenusa: sen(θ) = 3 / 5 = 0.6.',
                    'options' => [
                        ['letter' => 'A', 'text' => '3/5', 'is_correct' => true],
                        ['letter' => 'B', 'text' => '4/5', 'is_correct' => false],
                        ['letter' => 'C', 'text' => '3/4', 'is_correct' => false],
                        ['letter' => 'D', 'text' => '5/3', 'is_correct' => false],
                    ],
                ],
                [
                    'topic_id' => $trigTopic->id,
                    'stimulus_id' => null,
                    'order_in_group' => 1,
                    'statement' => '[Prueba ATHENA - Trigonometría] ¿Cuál de las siguientes relaciones expresa de forma correcta la identidad pitagórica fundamental para cualquier ángulo real α?',
                    'difficulty' => 'medium',
                    'explanation' => 'La identidad pitagórica fundamental para cualquier ángulo α establece que sen²(α) + cos²(α) = 1.',
                    'options' => [
                        ['letter' => 'A', 'text' => 'sen(α) + cos(α) = 1', 'is_correct' => false],
                        ['letter' => 'B', 'text' => 'sen²(α) + cos²(α) = 1', 'is_correct' => true],
                        ['letter' => 'C', 'text' => 'tan²(α) + 1 = sen²(α)', 'is_correct' => false],
                        ['letter' => 'D', 'text' => 'sen²(α) - cos²(α) = 1', 'is_correct' => false],
                    ],
                ],
                [
                    'topic_id' => $trigTopic->id,
                    'stimulus_id' => null,
                    'order_in_group' => 1,
                    'statement' => '[Prueba ATHENA - Trigonometría] Si cos(β) = 1/2 y el ángulo β pertenece al primer cuadrante (0° < β < 90°), ¿cuál es el valor del ángulo β?',
                    'difficulty' => 'medium',
                    'explanation' => 'El valor notable del coseno en el primer cuadrante para 60° es 1/2 (cos(60°) = 0.5).',
                    'options' => [
                        ['letter' => 'A', 'text' => '30°', 'is_correct' => false],
                        ['letter' => 'B', 'text' => '45°', 'is_correct' => false],
                        ['letter' => 'C', 'text' => '60°', 'is_correct' => true],
                        ['letter' => 'D', 'text' => '90°', 'is_correct' => false],
                    ],
                ],

                // --- Preguntas independientes de Ecuaciones Lineales (4 singletons) ---
                [
                    'topic_id' => $algTopic->id,
                    'stimulus_id' => null,
                    'order_in_group' => 1,
                    'statement' => '[Prueba ATHENA - Álgebra] ¿Cuál es el valor de x que satisface la ecuación de primer grado 2x + 7 = 19?',
                    'difficulty' => 'easy',
                    'explanation' => 'Restando 7 a ambos miembros se obtiene 2x = 12. Al dividir entre 2, se concluye que x = 6.',
                    'options' => [
                        ['letter' => 'A', 'text' => 'x = 4', 'is_correct' => false],
                        ['letter' => 'B', 'text' => 'x = 5', 'is_correct' => false],
                        ['letter' => 'C', 'text' => 'x = 6', 'is_correct' => true],
                        ['letter' => 'D', 'text' => 'x = 12', 'is_correct' => false],
                    ],
                ],
                [
                    'topic_id' => $algTopic->id,
                    'stimulus_id' => null,
                    'order_in_group' => 1,
                    'statement' => '[Prueba ATHENA - Álgebra] En el plano cartesiano, si dos ecuaciones lineales representan dos rectas paralelas distintas (no coincidentes), ¿cuántas soluciones comunes tiene el sistema?',
                    'difficulty' => 'easy',
                    'explanation' => 'Dos rectas paralelas distintas no tienen ningún punto de corte o intersección en el plano cartesiano, por lo que el sistema es incompatible y no tiene solución.',
                    'options' => [
                        ['letter' => 'A', 'text' => 'Ninguna solución', 'is_correct' => true],
                        ['letter' => 'B', 'text' => 'Exactamente una solución', 'is_correct' => false],
                        ['letter' => 'C', 'text' => 'Exactamente dos soluciones', 'is_correct' => false],
                        ['letter' => 'D', 'text' => 'Infinitas soluciones', 'is_correct' => false],
                    ],
                ],
                [
                    'topic_id' => $algTopic->id,
                    'stimulus_id' => null,
                    'order_in_group' => 1,
                    'statement' => '[Prueba ATHENA - Álgebra] La suma de dos números es 40 y su diferencia es 10. ¿Cuál es el valor del número mayor?',
                    'difficulty' => 'medium',
                    'explanation' => 'Planteando el sistema x + y = 40 y x - y = 10. Al sumar ambas ecuaciones miembro a miembro: 2x = 50, lo que da x = 25. Sustituyendo, y = 15. El mayor de los dos números es 25.',
                    'options' => [
                        ['letter' => 'A', 'text' => '20', 'is_correct' => false],
                        ['letter' => 'B', 'text' => '25', 'is_correct' => true],
                        ['letter' => 'C', 'text' => '30', 'is_correct' => false],
                        ['letter' => 'D', 'text' => '35', 'is_correct' => false],
                    ],
                ],
                [
                    'topic_id' => $algTopic->id,
                    'stimulus_id' => null,
                    'order_in_group' => 1,
                    'statement' => '[Prueba ATHENA - Álgebra] ¿Cuál es la pendiente de la recta cuya ecuación general es 3x - y + 5 = 0?',
                    'difficulty' => 'medium',
                    'explanation' => 'Despejando y en términos de x para obtener la ecuación explícita y = mx + b, tenemos y = 3x + 5. El coeficiente de x corresponde a la pendiente m = 3.',
                    'options' => [
                        ['letter' => 'A', 'text' => '-3', 'is_correct' => false],
                        ['letter' => 'B', 'text' => '1/3', 'is_correct' => false],
                        ['letter' => 'C', 'text' => '3', 'is_correct' => true],
                        ['letter' => 'D', 'text' => '5', 'is_correct' => false],
                    ],
                ],
            ];

            foreach ($questionsData as $item) {
                // Verificar si ya existe por enunciado para garantizar idempotencia total
                $question = Question::where('statement', $item['statement'])->first();

                if (!$question) {
                    $question = Question::create([
                        'subject_id' => $subject->id,
                        'topic_id' => $item['topic_id'],
                        'stimulus_id' => $item['stimulus_id'],
                        'order_in_group' => $item['order_in_group'],
                        'statement' => $item['statement'],
                        'difficulty' => $item['difficulty'],
                        'explanation' => $item['explanation'],
                        'source' => 'Banco de Pruebas ATHENA (Dev - Práctica)',
                        'source_pdf' => null,
                        'source_page' => null,
                        'original_question_number' => null,
                        'status' => 'published',
                    ]);

                    foreach ($item['options'] as $opt) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'option_letter' => $opt['letter'],
                            'option_text' => $opt['text'],
                            'is_correct' => $opt['is_correct'],
                        ]);
                    }
                }
            }
        });

        $finalCount = Question::where('subject_id', $subject->id)
            ->published()
            ->whereHas('options', fn($q) => $q->where('is_correct', true))
            ->count();

        $this->command?->info("Seeder finalizado. Matemáticas cuenta ahora con {$finalCount} preguntas publicadas válidas.");
    }
}
