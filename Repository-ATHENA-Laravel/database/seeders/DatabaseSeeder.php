<?php

namespace Database\Seeders;

use App\Http\Controllers\Auth\AuthController;
use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamConfig;
use App\Models\ExamConfigSubject;
use App\Models\Notification;
use App\Models\PaymentTransaction;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserTopicPerformance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create('es_CO');

        // 0. SuperAdministradores maestros (idempotente: crea si no existen, asciende a superadmin si ya existen).
        foreach (AuthController::MASTER_ADMIN_EMAILS as $adminEmail) {
            $admin = User::firstOrNew(['email' => $adminEmail]);

            if (!$admin->exists) {
                $admin->full_name = Str::before($adminEmail, '@');
                $admin->password = Hash::make(Str::random(32));
            }

            $admin->role = 'superadmin';
            $admin->status = User::STATUS_ACTIVE;
            $admin->save();
        }

        // 0.1. Cuenta base reproducible de Administrador Académico (rol 'admin')
        $baseAdmin = User::firstOrNew(['email' => 'admin@athena.edu.co']);
        $baseAdmin->full_name = 'Administrador Académico ATHENA';
        $baseAdmin->password = 'Athena2026*';
        $baseAdmin->role = 'admin';
        $baseAdmin->status = User::STATUS_ACTIVE;
        $baseAdmin->save();

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

        // 8. Rendimiento por Tema
        foreach ($users as $user) {
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

        // 9. Planes de Suscripción
        $plans = collect([
            ['name' => 'Básico', 'price' => 0.00, 'duration_days' => 30, 'description' => 'Simulacros limitados y tutoría IA básica.', 'is_active' => true],
            ['name' => 'ATHENA Pro', 'price' => 15000.00, 'duration_days' => 30, 'description' => 'Simulacros ilimitados y tutoría IA GPT-4 Turbo.', 'is_active' => true],
            ['name' => 'ATHENA Anual', 'price' => 120000.00, 'duration_days' => 365, 'description' => 'Preparación total hasta el día del examen ICFES.', 'is_active' => true],
        ])->map(fn (array $attributes) => SubscriptionPlan::factory()->create($attributes));

        // 10. Suscripciones y Pagos
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

        // 11. Notificaciones
        foreach ($users as $user) {
            Notification::factory()->create([
                'user_id' => $user->id,
                'title' => '¡Tu simulacro adaptativo está listo!',
                'message' => 'ATHENA ha generado un nuevo simulacro enfocado en tus debilidades en Matemáticas.',
                'is_read' => $faker->boolean(),
            ]);
        }

        // 12. Banco de Preguntas Mínimo para Prácticas de Matemáticas
        $this->call(MathematicsPracticeQuestionsSeeder::class);
    }
}