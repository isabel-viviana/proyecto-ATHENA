<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Models\StudentAnswer;
use App\Services\StudentTokenService;
use Illuminate\Support\Str;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class StudentSecurityAndIsolationTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    protected string $student1Token;
    protected string $student2Token;
    protected string $q1Id;
    protected string $q2Id;
    protected string $qUnassignedId;
    protected array $q1Options = [];
    protected array $q2Options = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();

        $this->student1Token = StudentTokenService::generateToken([
            'id'    => '101',
            'name'  => 'Estudiante Uno',
            'email' => 'estudiante1@athena.edu.co',
            'role'  => 'student',
        ]);

        $this->student2Token = StudentTokenService::generateToken([
            'id'    => '202',
            'name'  => 'Estudiante Dos',
            'email' => 'estudiante2@athena.edu.co',
            'role'  => 'student',
        ]);

        $docId = (string) Str::uuid();
        SourceDocument::create([
            'id'             => $docId,
            'title'          => 'Cuadernillo Seguridad P0-4',
            'storage_path'   => 'documents/2026/security_p0_4.pdf',
            'exam_type'      => 'Saber 11',
            'total_pages'    => 2,
            'has_answer_key' => true,
            'status'         => 'extracted',
            'created_at'     => now(),
        ]);

        // Crear 2 preguntas publicadas en materia 1 (Lectura Crítica) y 1 pregunta publicada en materia 2 (Matemáticas)
        $this->q1Id = $this->seedPublishedQuestion($docId, 1, 1, 'Pregunta 1 de Lectura Crítica', 'B', $this->q1Options);
        $this->q2Id = $this->seedPublishedQuestion($docId, 1, 2, 'Pregunta 2 de Lectura Crítica', 'C', $this->q2Options);
        $unassignedOpts = [];
        $this->qUnassignedId = $this->seedPublishedQuestion($docId, 2, 1, 'Pregunta de otra materia no asignada al intento', 'A', $unassignedOpts);
    }

    protected function seedPublishedQuestion(
        string $docId,
        int $subjectId,
        int $order,
        string $statement,
        string $correctLetter,
        array &$optionsMap
    ): string {
        $qId = (string) Str::uuid();
        Question::create([
            'id'                 => $qId,
            'root_question_id'   => $qId,
            'version'            => 1,
            'is_current_version' => true,
            'subject_id'         => $subjectId,
            'source_document_id' => $docId,
            'order_in_group'     => $order,
            'statement'          => $statement,
            'difficulty'         => 'medium',
            'explanation'        => "Explicación oficial para {$statement}. La clave es {$correctLetter}.",
            'key_status'         => 'verified',
            'status'             => 'published',
        ]);

        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $optId = (string) Str::uuid();
            QuestionOption::create([
                'id'            => $optId,
                'question_id'   => $qId,
                'option_letter' => $letter,
                'option_text'   => "Opción {$letter} de {$statement}",
                'is_correct'    => ($letter === $correctLetter),
                'created_at'    => now(),
            ]);
            $optionsMap[$letter] = $optId;
        }

        return $qId;
    }

    /**
     * A. RESULTADOS:
     * 1. Estudiante sin token: 401.
     * 2. Estudiante con intento en progreso: no puede consultar resultados finales ni respuestas correctas.
     * 3. Estudiante con intento finalizado: puede consultar sus resultados.
     * 4. Estudiante 2 intenta consultar el intento de estudiante 1: acceso denegado (403).
     * 5. Las respuestas correctas solo se muestran cuando corresponde (intento finalizado).
     */
    public function test_a_exam_results_security_and_student_isolation(): void
    {
        // Iniciar simulacro como Estudiante 1
        $startRes = $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson('/api/v1/student/exams/start', [
                'subject_id'       => 1,
                'mode'             => 'area',
                'questions_count'  => 10,
                'duration_minutes' => 20,
            ]);

        $startRes->assertStatus(201)
            ->assertJsonPath('data.attempt.status', 'in_progress');
        $attemptId = $startRes->json('data.attempt.id');

        // 1. Sin token: 401 en results, show y finish
        $this->flushHeaders();
        $this->getJson("/api/v1/student/exams/{$attemptId}/results")->assertStatus(401);
        $this->getJson("/api/v1/student/exams/{$attemptId}")->assertStatus(401);
        $this->postJson("/api/v1/student/exams/{$attemptId}/finish")->assertStatus(401);

        // 2. Intento en progreso: results devuelve 403 y no filtra respuestas correctas ni explicaciones
        $inProgressResults = $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->getJson("/api/v1/student/exams/{$attemptId}/results");
        $inProgressResults->assertStatus(403)
            ->assertJsonPath('success', false);
        $this->assertStringNotContainsString('correct_option_letter', $inProgressResults->getContent());
        $this->assertStringNotContainsString('Explicación oficial', $inProgressResults->getContent());

        // Verificar también que show (/api/v1/student/exams/{id}) en progreso oculta is_correct y explanation
        $showInProgress = $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->getJson("/api/v1/student/exams/{$attemptId}");
        $showInProgress->assertStatus(200);
        foreach ($showInProgress->json('data.questions') as $qItem) {
            $this->assertArrayNotHasKey('explanation', $qItem);
            foreach ($qItem['options'] as $optItem) {
                $this->assertArrayNotHasKey('is_correct', $optItem);
            }
        }

        // 4. Estudiante 2 intenta consultar o finalizar el intento en progreso de Estudiante 1: acceso denegado (403)
        $this->withHeaders(['Authorization' => "Bearer {$this->student2Token}"])
            ->getJson("/api/v1/student/exams/{$attemptId}")
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => "Bearer {$this->student2Token}"])
            ->getJson("/api/v1/student/exams/{$attemptId}/results")
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => "Bearer {$this->student2Token}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/finish")
            ->assertStatus(403);

        // Responder y finalizar como Estudiante 1
        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/answers", [
                'question_id'        => $this->q1Id,
                'selected_option_id' => $this->q1Options['B'],
                'time_spent_seconds' => 25,
            ])->assertStatus(200);

        $finishRes = $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/finish");
        $finishRes->assertStatus(200)
            ->assertJsonPath('data.attempt.status', 'completed');

        // 3 & 5. Con el intento finalizado, Estudiante 1 puede consultar sus resultados y ver las respuestas correctas
        $completedResults = $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->getJson("/api/v1/student/exams/{$attemptId}/results");
        $completedResults->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attempt.status', 'completed')
            ->assertJsonPath('data.attempt.correct_answers', 1)
            ->assertJsonPath('data.questions_detail.0.correct_option_letter', 'B');

        // Y Estudiante 2 sigue teniendo acceso denegado (403) a los resultados finalizados de Estudiante 1
        $this->withHeaders(['Authorization' => "Bearer {$this->student2Token}"])
            ->getJson("/api/v1/student/exams/{$attemptId}/results")
            ->assertStatus(403);
    }

    /**
     * B. RESPUESTAS:
     * 1. Sin token: 401.
     * 2. Respuesta de pregunta asignada: funciona.
     * 3. Pregunta que no pertenece al intento: rechazada (422).
     * 4. Opción que no pertenece a la pregunta: rechazada (422).
     * 5. Intento de otro estudiante: rechazado (403).
     * 6. student_id manipulado desde el frontend: rechazado (403) y no altera datos ajenos.
     */
    public function test_b_answer_submission_security_and_validation_rules(): void
    {
        // Iniciar simulacro para Estudiante 1 (asigna preguntas de materia 1: q1 y q2)
        $startRes = $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson('/api/v1/student/exams/start', [
                'subject_id'       => 1,
                'mode'             => 'area',
                'questions_count'  => 10,
                'duration_minutes' => 20,
            ])->assertStatus(201);
        $attemptId = $startRes->json('data.attempt.id');

        // 1. Sin token: 401 tanto en simulacro como en práctica libre
        $this->flushHeaders();
        $this->postJson("/api/v1/student/exams/{$attemptId}/answers", [
            'question_id'            => $this->q1Id,
            'selected_option_letter' => 'B',
        ])->assertStatus(401);

        $unauthPractice = $this->postJson("/api/v1/student/questions/{$this->q1Id}/answer", [
            'selected_option_letter' => 'B',
        ]);
        $unauthPractice->assertStatus(401);
        $this->assertStringNotContainsString('correct_option_letter', $unauthPractice->getContent());

        // 2. Respuesta de pregunta asignada con opción válida: funciona en simulacro y en práctica
        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/answers", [
                'question_id'        => $this->q1Id,
                'selected_option_id' => $this->q1Options['B'],
                'time_spent_seconds' => 15,
            ])->assertStatus(200)
            ->assertJsonPath('data.selected_option_letter', 'B');

        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/questions/{$this->q1Id}/answer", [
                'selected_option_id' => $this->q1Options['B'],
                'time_spent_seconds' => 15,
            ])->assertStatus(200)
            ->assertJsonPath('data.student_id', '101')
            ->assertJsonPath('data.is_correct', true);

        // 3. Pregunta que no pertenece al intento: rechazada con 422
        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/answers", [
                'question_id'            => $this->qUnassignedId,
                'selected_option_letter' => 'A',
            ])->assertStatus(422);

        // 4. Opción que no pertenece a la pregunta (enviar opción de q2 para responder q1): rechazada con 422
        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/answers", [
                'question_id'        => $this->q1Id,
                'selected_option_id' => $this->q2Options['C'],
            ])->assertStatus(422);

        // Incluso si envía selected_option_letter junto con un selected_option_id de otra pregunta: rechazada con 422
        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/answers", [
                'question_id'            => $this->q1Id,
                'selected_option_id'     => $this->q2Options['B'],
                'selected_option_letter' => 'B',
            ])->assertStatus(422);

        // En práctica libre: enviar selected_option_id de q2 al responder q1 también se rechaza con 422
        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/questions/{$this->q1Id}/answer", [
                'selected_option_id' => $this->q2Options['C'],
            ])->assertStatus(422);

        // 5. Intento de otro estudiante: Estudiante 2 intenta guardar respuesta en el intento de Estudiante 1 -> 403
        $this->withHeaders(['Authorization' => "Bearer {$this->student2Token}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/answers", [
                'question_id'            => $this->q1Id,
                'selected_option_letter' => 'A',
            ])->assertStatus(403);

        // 6. student_id manipulado desde el frontend: rechazado con 403 y no escribe registros para otro estudiante
        $this->withHeaders(['Authorization' => "Bearer {$this->student2Token}"])
            ->postJson("/api/v1/student/questions/{$this->q2Id}/answer", [
                'student_id'             => '101',
                'selected_option_letter' => 'A',
            ])->assertStatus(403);

        $this->assertSame(0, StudentAnswer::where('student_id', '101')->where('question_id', $this->q2Id)->count());

        // Finalizar el intento de Estudiante 1 y comprobar que no se pueden modificar respuestas tras finalizar (403)
        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/finish")
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/exams/{$attemptId}/answers", [
                'question_id'            => $this->q2Id,
                'selected_option_letter' => 'C',
            ])->assertStatus(403);
    }

    /**
     * C. PROGRESO Y RUTAS PRIVADAS:
     * 1. Estudiante autenticado consulta su propio progreso.
     * 2. Estudiante 2 intenta consultar el progreso de estudiante 1: acceso denegado (403).
     * 3. Modificar student_id no permite consultar información ajena.
     * 4. Sin token: 401 en rutas privadas.
     */
    public function test_c_student_progress_and_private_routes_require_token_and_prevent_id_spoofing(): void
    {
        // 4. Sin token: 401 en todas las rutas privadas de estudiante
        $this->getJson('/api/v1/student/progress')->assertStatus(401);
        $this->getJson('/api/v1/student/progress?student_id=101')->assertStatus(401);
        $this->getJson('/api/v1/student/exams/active')->assertStatus(401);
        $this->getJson('/api/v1/student/exams/history')->assertStatus(401);
        $this->getJson('/api/v1/student/profile')->assertStatus(401);
        $this->putJson('/api/v1/student/profile', ['full_name' => 'Intruso'])->assertStatus(401);
        $this->postJson('/api/v1/student/profile/password', [])->assertStatus(401);
        $this->getJson('/api/v1/student/notifications')->assertStatus(401);
        $this->getJson('/api/v1/student/ranking')->assertStatus(401);
        $this->getJson('/api/v1/student/ranking/me')->assertStatus(401);

        // Registrar respuestas para Estudiante 1 (2 respuestas correctas)
        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/questions/{$this->q1Id}/answer", [
                'selected_option_letter' => 'B',
                'time_spent_seconds'     => 20,
            ])->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->postJson("/api/v1/student/questions/{$this->q2Id}/answer", [
                'selected_option_letter' => 'C',
                'time_spent_seconds'     => 30,
            ])->assertStatus(200);

        // 1. Estudiante 1 consulta su propio progreso (con y sin su propio student_id)
        $prog1 = $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->getJson('/api/v1/student/progress');
        $prog1->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student_id', '101')
            ->assertJsonPath('data.summary.total_answered', 2)
            ->assertJsonPath('data.summary.correct_count', 2);

        $this->withHeaders(['Authorization' => "Bearer {$this->student1Token}"])
            ->getJson('/api/v1/student/progress?student_id=101')
            ->assertStatus(200)
            ->assertJsonPath('data.student_id', '101');

        // Estudiante 2 (sin respuestas) consulta su propio progreso -> 0 respuestas
        $prog2 = $this->withHeaders(['Authorization' => "Bearer {$this->student2Token}"])
            ->getJson('/api/v1/student/progress');
        $prog2->assertStatus(200)
            ->assertJsonPath('data.student_id', '202')
            ->assertJsonPath('data.summary.total_answered', 0);

        // 2 & 3. Estudiante 2 intenta consultar el progreso o historial de Estudiante 1 enviando ?student_id=101 -> 403 Forbidden
        $spoofProgress = $this->withHeaders(['Authorization' => "Bearer {$this->student2Token}"])
            ->getJson('/api/v1/student/progress?student_id=101');
        $spoofProgress->assertStatus(403)
            ->assertJsonPath('success', false);

        $spoofHistory = $this->withHeaders(['Authorization' => "Bearer {$this->student2Token}"])
            ->getJson('/api/v1/student/exams/history?student_id=101');
        $spoofHistory->assertStatus(403)
            ->assertJsonPath('success', false);

        $spoofActive = $this->withHeaders(['Authorization' => "Bearer {$this->student2Token}"])
            ->getJson('/api/v1/student/exams/active?student_id=101');
        $spoofActive->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
