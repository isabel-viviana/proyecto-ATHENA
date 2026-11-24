<?php

namespace Tests\Feature;

use App\Services\StudentTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class Bloque2AdminCompleteTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    protected int $superAdminId;
    protected int $adminId;
    protected int $supportId;
    protected int $studentId;

    protected string $superAdminToken;
    protected string $adminToken;
    protected string $supportToken;
    protected string $studentToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();

        $this->superAdminId = DB::connection('athena_core')->table('users')->insertGetId([
            'full_name'  => 'Super Admin Test',
            'email'      => 'superadmin.test@athena.edu.co',
            'password'   => Hash::make('Secret123!'),
            'role'       => 'superadmin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->adminId = DB::connection('athena_core')->table('users')->insertGetId([
            'full_name'  => 'Admin Academico Test',
            'email'      => 'admin.test@athena.edu.co',
            'password'   => Hash::make('Secret123!'),
            'role'       => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->supportId = DB::connection('athena_core')->table('users')->insertGetId([
            'full_name'  => 'Soporte Tecnico Test',
            'email'      => 'support.test@athena.edu.co',
            'password'   => Hash::make('Secret123!'),
            'role'       => 'support',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->studentId = DB::connection('athena_core')->table('users')->insertGetId([
            'full_name'          => 'Estudiante Prueba Uno',
            'email'              => 'estudiante1.test@athena.edu.co',
            'password'           => Hash::make('Secret123!'),
            'role'               => 'student',
            'grade'              => '11°',
            'target_icfes_score' => 410,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $this->superAdminToken = StudentTokenService::generateToken([
            'id'        => $this->superAdminId,
            'full_name' => 'Super Admin Test',
            'email'     => 'superadmin.test@athena.edu.co',
            'role'      => 'superadmin',
        ]);

        $this->adminToken = StudentTokenService::generateToken([
            'id'        => $this->adminId,
            'full_name' => 'Admin Academico Test',
            'email'     => 'admin.test@athena.edu.co',
            'role'      => 'admin',
        ]);

        $this->supportToken = StudentTokenService::generateToken([
            'id'        => $this->supportId,
            'full_name' => 'Soporte Tecnico Test',
            'email'     => 'support.test@athena.edu.co',
            'role'      => 'support',
        ]);

        $this->studentToken = StudentTokenService::generateToken([
            'id'        => $this->studentId,
            'full_name' => 'Estudiante Prueba Uno',
            'email'     => 'estudiante1.test@athena.edu.co',
            'role'      => 'student',
        ]);
    }

    public function test_users_crud_search_filters_and_status_toggle(): void
    {
        $listRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/users');

        $listRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.total', 4)
            ->assertJsonPath('stats.by_role.student', 1);

        $createRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/admin/users', [
                'full_name'          => 'Camila Prueba Bloque 2',
                'email'              => 'camila.bloque2@athena.edu.co',
                'password'           => 'Pass12345!',
                'phone'              => '3001234567',
                'role'               => 'student',
                'grade'              => '10°',
                'target_icfes_score' => 430,
                'status'             => 'Activo',
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'camila.bloque2@athena.edu.co')
            ->assertJsonPath('data.grade', '10°');

        $createdUserId = $createRes->json('data.id');
        $this->assertNotEmpty($createdUserId);

        $showRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/admin/users/{$createdUserId}");

        $showRes->assertOk()
            ->assertJsonPath('data.full_name', 'Camila Prueba Bloque 2')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'full_name',
                    'email',
                    'role',
                    'status',
                    'academic_activity' => [
                        'exam_attempts_count',
                        'completed_exams_count',
                        'answers_count',
                        'accuracy',
                    ],
                ],
            ]);

        $updateRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/admin/users/{$createdUserId}", [
                'full_name'          => 'Camila Prueba Editada',
                'grade'              => '11°',
                'target_icfes_score' => 455,
            ]);

        $updateRes->assertOk()
            ->assertJsonPath('data.full_name', 'Camila Prueba Editada')
            ->assertJsonPath('data.grade', '11°')
            ->assertJsonPath('data.target_icfes_score', 455);

        $suspendRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/admin/users/{$createdUserId}/status", [
                'status' => 'Suspendido',
            ]);

        $suspendRes->assertOk()
            ->assertJsonPath('data.status', 'Suspendido');

        $reactivateRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/admin/users/{$createdUserId}/status", [
                'status' => 'Activo',
            ]);

        $reactivateRes->assertOk()
            ->assertJsonPath('data.status', 'Activo');
    }

    public function test_role_permissions_and_restrictions_are_strictly_enforced(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->getJson('/api/v1/admin/users')
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$this->studentToken}")
            ->getJson('/api/v1/admin/stats')
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->getJson('/api/v1/admin/stats')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->getJson('/api/v1/admin/users')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->postJson('/api/v1/admin/users', [
                'full_name' => 'Intento Soporte',
                'email'     => 'intento.soporte@athena.edu.co',
                'password'  => 'Secret123!',
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->postJson('/api/v1/admin/catalogs/subjects', [
                'name' => 'Area No Autorizada',
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/admin/users', [
                'full_name' => 'Intento Admin Escalado',
                'email'     => 'escalado@athena.edu.co',
                'password'  => 'Secret123!',
                'role'      => 'superadmin',
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/admin/users/{$this->studentId}/role", [
                'role' => 'admin',
            ])
            ->assertStatus(403);

        $roleChangeRes = $this->withHeader('Authorization', "Bearer {$this->superAdminToken}")
            ->patchJson("/api/v1/admin/users/{$this->studentId}/role", [
                'role' => 'support',
            ]);

        $roleChangeRes->assertOk()
            ->assertJsonPath('data.role', 'support');
    }

    public function test_subjects_and_topics_crud_in_question_bank(): void
    {
        $createSubject = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/admin/catalogs/subjects', [
                'name'        => 'Competencias Financieras',
                'description' => 'Educación económica y matemática financiera.',
            ]);

        $createSubject->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Competencias Financieras');

        $newSubjectId = $createSubject->json('data.id');
        $this->assertGreaterThan(5, $newSubjectId);

        $updateSubject = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/admin/catalogs/subjects/{$newSubjectId}", [
                'name'        => 'Competencias Financieras y Económicas',
                'description' => 'Descripción actualizada de prueba.',
            ]);

        $updateSubject->assertOk()
            ->assertJsonPath('data.name', 'Competencias Financieras y Económicas');

        $createTopic = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/admin/catalogs/topics', [
                'subject_id'  => 2,
                'name'        => 'Álgebra y Funciones Lineales',
                'description' => 'Modelación de ecuaciones y sistemas lineales.',
                'is_verified' => true,
            ]);

        $createTopic->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subject_id', 2)
            ->assertJsonPath('data.name', 'Álgebra y Funciones Lineales');

        $newTopicId = $createTopic->json('data.id');

        $updateTopic = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/admin/catalogs/topics/{$newTopicId}", [
                'name'        => 'Álgebra, Funciones y Sistemas Lineales',
                'description' => 'Ecuaciones lineales y cuadráticas contextualizadas.',
            ]);

        $updateTopic->assertOk()
            ->assertJsonPath('data.name', 'Álgebra, Funciones y Sistemas Lineales');

        $deleteSubject = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/v1/admin/catalogs/subjects/{$newSubjectId}");

        $deleteSubject->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_manual_question_creation_and_review_publish_workflow(): void
    {
        $topicRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/admin/catalogs/topics', [
                'subject_id'  => 2,
                'name'        => 'Probabilidad y Estadística ICFES',
                'description' => 'Análisis de tablas y probabilidad condicional.',
            ]);
        $topicId = $topicRes->json('data.id');

        $createQ = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/admin/questions', [
                'subject_id'            => 2,
                'topic_id'              => $topicId,
                'difficulty'            => 'medium',
                'statement'             => 'En una urna hay 4 esferas rojas y 6 azules. ¿Cuál es la probabilidad de extraer al azar una esfera roja?',
                'explanation'           => 'Hay 4 casos favorables de un total de 10 esferas (4/10 = 2/5 = 40%).',
                'correct_option'        => 'B',
                'stimulus_title'        => 'Experimento de Probabilidad en el Laboratorio',
                'stimulus_content_text' => 'Un grupo de estudiantes realiza extracciones con reemplazo en una urna opaca.',
                'options'               => [
                    ['option_letter' => 'A', 'option_text' => '20% (1 de 5)'],
                    ['option_letter' => 'B', 'option_text' => '40% (2 de 5)'],
                    ['option_letter' => 'C', 'option_text' => '60% (3 de 5)'],
                    ['option_letter' => 'D', 'option_text' => '80% (4 de 5)'],
                ],
            ]);

        $createQ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.key_status', 'verified')
            ->assertJsonPath('data.subject_id', 2)
            ->assertJsonPath('data.topic_id', $topicId);

        $questionId = $createQ->json('data.id');
        $this->assertNotEmpty($createQ->json('data.stimulus_id'));

        $deleteLinkedTopic = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/v1/admin/catalogs/topics/{$topicId}");

        $deleteLinkedTopic->assertOk()
            ->assertJsonPath('deactivated', true)
            ->assertJsonPath('data.is_active', false);

        $approveRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/admin/questions/{$questionId}/approve", [
                'acknowledge_warnings' => true,
                'notes'                => 'Pregunta manual revisada y aprobada por comité académico.',
            ]);

        $approveRes->assertOk()
            ->assertJsonPath('data.status', 'review');

        $publishRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/admin/questions/{$questionId}/publish", [
                'notes' => 'Publicada para simulacros y prácticas.',
            ]);

        $publishRes->assertOk()
            ->assertJsonPath('data.status', 'published');

        $statsRes = $this->withHeader('Authorization', "Bearer {$this->superAdminToken}")
            ->getJson('/api/v1/admin/stats');

        $statsRes->assertOk()
            ->assertJsonPath('data.permissions.is_superadmin', true)
            ->assertJsonPath('data.catalog.total_subjects', 5)
            ->assertJsonPath('data.catalog.total_topics', 1)
            ->assertJsonPath('data.questions.total', 1)
            ->assertJsonPath('data.questions.by_status.published', 1);
    }
}
