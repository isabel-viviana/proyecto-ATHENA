<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamConfig;
use App\Models\ExamConfigSubject;
use App\Models\Inventory;
use App\Models\Notification;
use App\Models\PaymentTransaction;
use App\Models\Purchase;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\StoreItem;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserTopicPerformance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create('es_CO');

        // 1. Usuarios (Tu usuario principal + 9 estudiantes aleatorios)
        $users = collect();
        
        // Creación fija de tu perfil de pruebas
        $users->push(User::factory()->create([
            'full_name' => 'Juan Sebastian Acevedo Medina',
            'email' => 'juan.acevedo@athena.edu.co',
        ]));

        // Completa los 10 registros requeridos
        for ($i = 0; $i < 9; $i++) {
            $users->push(User::factory()->create([
                'full_name' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
            ]));
        }

        // 2. Materias Oficiales ICFES
        $subjectsData = [
            ['name' => 'Matemáticas', 'slug' => 'matematicas', 'description' => 'Razonamiento cuantitativo, álgebra, cálculo y geometría.'],
            ['name' => 'Ciencias Naturales', 'slug' => 'ciencias-naturales', 'description' => 'Biología, física, química y componente CTS.'],
            ['name' => 'Lectura Crítica', 'slug' => 'lectura-critica', 'description' => 'Textos literarios, argumentativos y discontinuos.'],
            ['name' => 'Sociales y Ciudadanas', 'slug' => 'sociales-ciudadanas', 'description' => 'Historia, geografía, constitución y análisis de perspectivas.'],
            ['name' => 'Inglés', 'slug' => 'ingles', 'description' => 'Vocabulario, gramática y comprensión de lectura (A1-B1).'],
        ];

        $subjects = collect();
        foreach ($subjectsData as $data) {
            $subjects->push(Subject::factory()->create($data));
        }

        // 3. Temas Reales por Materia
        $topicsData = [
            'matematicas' => ['Estadística y Probabilidad', 'Trigonometría Básica', 'Ecuaciones Lineales'],
            'ciencias-naturales' => ['Genética y Célula', 'Cinemática (Física)', 'Estequiometría (Química)'],
            'lectura-critica' => ['Identificación de Tesis', 'Figuras Literarias', 'Análisis de Infografías'],
            'sociales-ciudadanas' => ['Mecanismos de Participación', 'La Patria Boba', 'Conflictos Globales (Siglo XX)'],
            'ingles' => ['Tiempos Verbales (Past/Present)', 'Phrasal Verbs', 'Reading Comprehension Parts 1-7'],
        ];

        $topics = collect();
        foreach ($subjects as $subject) {
            foreach ($topicsData[$subject->slug] as $topicName) {
                $topics->push(Topic::factory()->create([
                    'subject_id' => $subject->id,
                    'name' => $topicName,
                    'slug' => Str::slug($topicName),
                    'description' => "Material de preparación y simulacros para " . $topicName,
                ]));
            }
        }

        // 4. Preguntas y Opciones (Generación estructurada)
        $questions = collect();
        foreach ($topics as $topic) {
            for ($i = 0; $i < 5; $i++) {
                $question = Question::factory()->create([
                    'subject_id' => $topic->subject_id,
                    'topic_id' => $topic->id,
                    'statement' => 'De acuerdo con los conceptos de ' . $topic->name . ', ' . $faker->realText(80) . '?',
                    'difficulty' => $faker->randomElement(['easy', 'medium', 'hard']),
                    'explanation' => 'La respuesta correcta se deduce aplicando los principios de ' . $topic->name . '. Según el Icfes, este tipo de competencia evalúa tu capacidad de análisis.',
                    'source' => $faker->randomElement(['Simulacro ICFES 2023', 'Material Propio ATHENA', 'Prueba Piloto 2024']),
                ]);
                $questions->push($question);

                $correctLetter = $faker->randomElement(['A', 'B', 'C', 'D']);
                foreach (['A', 'B', 'C', 'D'] as $letter) {
                    QuestionOption::factory()->create([
                        'question_id' => $question->id,
                        'option_letter' => $letter,
                        'option_text' => $faker->sentence(4),
                        'is_correct' => $letter === $correctLetter,
                    ]);
                }
            }
        }

// 5. Configuración de Exámenes (Ajustado a los ENUMs de tu base de datos)
        $examConfigs = collect();
        foreach ($users as $user) {
            $examConfig = ExamConfig::factory()->create([
                'user_id' => $user->id,
                // Volvemos a los enums permitidos: quick, full o practice
                'exam_type' => $faker->randomElement(['quick', 'practice', 'full']),
                'difficulty' => $faker->randomElement(['easy', 'medium', 'hard']),
            ]);
            $examConfigs->push($examConfig);

            $randomSubjects = $subjects->random(rand(1, 5));
            foreach ($randomSubjects as $rs) {
                ExamConfigSubject::create([
                    'exam_config_id' => $examConfig->id,
                    'subject_id' => $rs->id,
                ]);
            }
        }

        // 6. Intentos de Examen
        $examAttempts = collect();
        foreach ($examConfigs as $config) {
            for ($i = 0; $i < 2; $i++) {
                $examAttempts->push(ExamAttempt::factory()->create([
                    'user_id' => $config->user_id,
                    'exam_config_id' => $config->id,
                ]));
            }
        }

        // 7. Respuestas de Intentos
        foreach ($examAttempts as $attempt) {
            $attemptQuestions = $questions->random(5);
            foreach ($attemptQuestions as $q) {
                $options = $q->options;
                if ($options->isNotEmpty()) {
                    $selectedOption = $options->where('is_correct', true)->first() ?? $options->first();
                    if ($faker->boolean(40)) {
                        $selectedOption = $options->where('is_correct', false)->first() ?? $selectedOption;
                    }
                    AttemptAnswer::create([
                        'exam_attempt_id' => $attempt->id,
                        'question_id' => $q->id,
                        'selected_option_id' => $selectedOption->id,
                        'is_correct' => $selectedOption->is_correct,
                        'time_spent_seconds' => $faker->numberBetween(30, 150),
                        'explanation_viewed' => $faker->boolean(70),
                    ]);
                }
            }
        }

        // 8. Logros de Gamificación
        $achievementsData = [
            ['title' => 'Primera Sangre', 'desc' => 'Completa tu primer simulacro diagnóstico.', 'cat' => 'exams'],
            ['title' => 'Racha Imparable', 'desc' => 'Estudia 7 días seguidos en ATHENA.', 'cat' => 'streak'],
            ['title' => 'Einstein Moderno', 'desc' => 'Saca 100% en un simulacro de Ciencias Naturales.', 'cat' => 'study'],
            ['title' => 'Pionero ATHENA', 'desc' => 'Participa en la fase beta de la plataforma.', 'cat' => 'special'],
        ];

        $achievements = collect();
        foreach ($achievementsData as $ach) {
            $achievements->push(Achievement::factory()->create([
                'title' => $ach['title'],
                'description' => $ach['desc'],
                'category' => $ach['cat'],
                'xp_reward' => $faker->numberBetween(50, 200),
                'coin_reward' => $faker->numberBetween(20, 100),
                'unlock_rule' => 'Regla interna del sistema',
            ]));
        }

        // 9. Asignación de Logros y Rendimiento
        foreach ($users as $user) {
            $userAchievements = $achievements->random(rand(1, 2));
            foreach ($userAchievements as $ua) {
                UserAchievement::factory()->create([
                    'user_id' => $user->id,
                    'achievement_id' => $ua->id,
                    'unlocked_at' => $faker->dateTimeBetween('-1 month', 'now'),
                    'progress' => 100,
                ]);
            }

            $userTopics = $topics->random(rand(3, 8));
            foreach ($userTopics as $ut) {
                UserTopicPerformance::create([
                    'user_id' => $user->id,
                    'topic_id' => $ut->id,
                    'correct_count' => $faker->numberBetween(5, 30),
                    'incorrect_count' => $faker->numberBetween(2, 15),
                    'omitted_count' => $faker->numberBetween(0, 3),
                    'mastery_score' => $faker->randomFloat(2, 0.3, 0.95),
                ]);
            }
        }

        // 10. Planes de Suscripción
        $plans = collect([
            ['name' => 'Básico', 'price' => 0.00, 'duration_days' => 30, 'description' => 'Simulacros limitados y tutoría IA básica.', 'is_active' => true],
            ['name' => 'ATHENA Pro', 'price' => 15000.00, 'duration_days' => 30, 'description' => 'Simulacros ilimitados y tutoría IA GPT-4 Turbo.', 'is_active' => true],
            ['name' => 'ATHENA Anual', 'price' => 120000.00, 'duration_days' => 365, 'description' => 'Preparación total hasta el día del examen ICFES.', 'is_active' => true],
        ])->map(fn (array $attributes) => SubscriptionPlan::factory()->create($attributes));

        // 11. Suscripciones y Pagos
        foreach ($users as $user) {
            if ($faker->boolean(60)) {
                $plan = $plans->random();
                $subscription = Subscription::factory()->create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $plan->id,
                    'start_date' => now(),
                    'end_date' => now()->addDays($plan->duration_days),
                    'status' => 'active',
                ]);

                if ($plan->price > 0) {
                    PaymentTransaction::factory()->create([
                        'user_id' => $user->id,
                        'subscription_id' => $subscription->id,
                        'amount' => $plan->price,
                        'status' => 'completed',
                    ]);
                }
            }
        }

        // 12. Artículos de la Tienda (Personalización de Mascota ATHENA)
        $storeItemsData = [
            ['name' => 'Gafas de Intelectual', 'cat' => 'avatar', 'desc' => 'Equipa a tu mascota con gafas de lectura.'],
            ['name' => 'Sombrero de Graduado', 'cat' => 'avatar', 'desc' => 'Un birrete para tu mascota.'],
            ['name' => 'Fondo Biblioteca', 'cat' => 'theme', 'desc' => 'Cambia el fondo de tu panel a una biblioteca.'],
            ['name' => 'Doble XP (24h)', 'cat' => 'booster', 'desc' => 'Gana el doble de experiencia hoy.'],
        ];

        $storeItems = collect();
        foreach ($storeItemsData as $item) {
            $storeItems->push(StoreItem::factory()->create([
                'name' => $item['name'],
                'description' => $item['desc'],
                'category' => $item['cat'],
                'price_coins' => $faker->numberBetween(100, 500),
                'image_url' => 'assets/store/' . Str::slug($item['name']) . '.png',
                'is_active' => true,
            ]));
        }

        // 13. Compras e Inventarios
        foreach ($users as $user) {
            if ($faker->boolean(50)) {
                $itemsToBuy = $storeItems->random(rand(1, 2));
                foreach ($itemsToBuy as $item) {
                    Purchase::create([
                        'user_id' => $user->id,
                        'store_item_id' => $item->id,
                        'coins_spent' => $item->price_coins,
                    ]);
                    Inventory::create([
                        'user_id' => $user->id,
                        'store_item_id' => $item->id,
                        'equipped' => $faker->boolean(),
                        'acquired_at' => now(),
                    ]);
                }
            }
        }

        // 14. Notificaciones
        foreach ($users as $user) {
            Notification::factory()->create([
                'user_id' => $user->id,
                'title' => '¡Tu simulacro adaptativo está listo!',
                'message' => 'ATHENA ha generado un nuevo simulacro enfocado en tus debilidades en Matemáticas.',
                'is_read' => $faker->boolean(),
            ]);
        }

        // 15. Sesiones de Chat con IA (Tutor ATHENA)
        foreach ($users as $user) {
            if ($faker->boolean(70)) {
                $session = AiChatSession::factory()->create([
                    'user_id' => $user->id,
                    'title' => 'Duda sobre pregunta de Estequiometría',
                ]);

                AiChatMessage::factory()->create([
                    'session_id' => $session->id,
                    'sender_type' => 'user',
                    'message' => 'No entiendo por qué la respuesta correcta en la pregunta de Ciencias era la B. ¿Me explicas cómo balancear la ecuación?',
                ]);
                AiChatMessage::factory()->create([
                    'session_id' => $session->id,
                    'sender_type' => 'ai',
                    'message' => '¡Hola! Claro que sí. En esa pregunta del ICFES se evalúa la ley de conservación de la masa. Para balancear la ecuación, debes asegurarte de que el número de átomos de reactivos sea igual al de los productos. Vamos paso a paso...',
                ]);
            }
        }
    }
}