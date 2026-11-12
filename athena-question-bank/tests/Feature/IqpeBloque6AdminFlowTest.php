<?php

namespace Tests\Feature;

use App\Models\IqpeAdminDecision;
use App\Models\IqpeDocumentJob;
use App\Models\IqpeQualityAudit;
use App\Models\IqpeQuestionResolution;
use App\Models\IqpeVerification;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SourceDocument;
use App\Models\Stimulus;
use App\Models\StudentAnswer;
use App\Services\StudentTokenService;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\UsesInMemoryQuestionBankSchema;
use Tests\TestCase;

class IqpeBloque6AdminFlowTest extends TestCase
{
    use UsesInMemoryQuestionBankSchema;

    private string $adminToken;
    private string $studentToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryQuestionBank();

        config([
            'services.athena.admin_email'    => 'admin@athena.edu.co',
            'services.athena.admin_password' => 'admin123456',
        ]);

        $this->adminToken = StudentTokenService::generateToken([
            'id'    => 'admin-b6-1',
            'name'  => 'Coordinador Académico B6',
            'email' => 'admin@athena.edu.co',
            'role'  => 'superadmin',
        ]);

        $this->studentToken = StudentTokenService::generateToken([
            'sub'   => 'student-b6-1',
            'id'    => 'student-b6-1',
            'name'  => 'Estudiante B6',
            'email' => 'student@athena.edu.co',
            'role'  => 'student',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createStandardOptions(Question $q, ?string $correctLetter = 'B', int $count = 4): void
    {
        $letters = array_slice(['A', 'B', 'C', 'D'], 0, $count);
        foreach ($letters as $letter) {
            QuestionOption::create([
                'id'            => (string) Str::uuid(),
                'question_id'   => $q->id,
                'option_letter' => $letter,
                'option_text'   => "Alternativa pedagógica {$letter} para el ítem {$q->order_in_group}",
                'is_correct'    => $correctLetter !== null ? ($letter === $correctLetter) : null,
            ]);
        }
    }

    public function test_1_2_3_admin_login_authorized_access_and_student_rejection(): void
    {
        // 1. Login administrativo con credenciales inválidas falla (401)
        $this->postJson('/api/v1/admin/auth/login', [
            'email'    => 'admin@athena.edu.co',
            'password' => 'clave-incorrecta',
        ])->assertStatus(401);

        // Login administrativo válido emite token con rol admin
        $loginRes = $this->postJson('/api/v1/admin/auth/login', [
            'email'    => 'admin@athena.edu.co',
            'password' => 'admin123456',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('admin.role', 'superadmin');

        $token = $loginRes->json('token');
        $this->assertNotEmpty($token);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/admin/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('admin.email', 'admin@athena.edu.co');

        // 2. Acceso autorizado a rutas IQPE administrativas
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/admin/iqpe/review-queue')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Protección anti-IDOR / UUID malformado retorna 422 en lugar de 500 SQL
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/admin/questions/not-a-valid-uuid/iqpe-detail')
            ->assertStatus(422);

        // 3. Acceso denegado sin token (401) y con token de estudiante (403)
        $fakeUuid = (string) Str::uuid();
        $this->flushHeaders();
        $this->getJson('/api/v1/admin/iqpe/review-queue')->assertStatus(401);
        $this->postJson("/api/v1/admin/questions/{$fakeUuid}/approve")->assertStatus(401);
        $this->postJson("/api/v1/admin/questions/{$fakeUuid}/publish")->assertStatus(401);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->getJson('/api/v1/admin/iqpe/review-queue')
            ->assertStatus(403);
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->getJson("/api/v1/admin/questions/{$fakeUuid}/iqpe-detail")
            ->assertStatus(403);
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->postJson("/api/v1/admin/questions/{$fakeUuid}/approve")
            ->assertStatus(403);
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->postJson("/api/v1/admin/questions/{$fakeUuid}/publish")
            ->assertStatus(403);
    }

    public function test_4_5_6_review_queue_filters_r1_r2_r3_official_key_audit_and_duplicates(): void
    {
        $doc = SourceDocument::create([
            'id'             => (string) Str::uuid(),
            'title'          => 'Cuadernillo Bloque 6 Mixto',
            'storage_path'   => 'documents/2026/b6-mixto.pdf',
            'year'           => 2026,
            'exam_type'      => 'Saber 11',
            'has_answer_key' => true,
            'status'         => 'extracted',
        ]);

        $job = IqpeDocumentJob::create([
            'id'                 => (string) Str::uuid(),
            'source_document_id' => $doc->id,
            'run_number'         => 1,
            'status'             => 'completed',
        ]);

        $publishedRef = Question::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 1,
            'source_document_id' => $doc->id,
            'order_in_group'     => 1,
            'statement'          => 'Pregunta ya publicada de referencia sobre comprensión literal del texto.',
            'key_status'         => 'verified',
            'status'             => 'published',
        ]);
        $this->createStandardOptions($publishedRef, 'C');

        $stimulus = Stimulus::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 1,
            'source_document_id' => $doc->id,
            'title'              => 'Texto de Lectura Crítica B6',
            'content_text'       => 'Fragmento argumentativo completo sobre ciudadanía digital y ética pública.',
            'media_type'         => 'none',
        ]);

        // Q1: Lista para aprobación (con clave oficial B, R1=B, R2=B, calidad limpia)
        $qReady = Question::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 1,
            'stimulus_id'        => $stimulus->id,
            'source_document_id' => $doc->id,
            'order_in_group'     => 2,
            'statement'          => 'Según el fragmento argumentativo, ¿cuál es la tesis central del autor respecto a la ciudadanía digital?',
            'key_status'         => 'verified',
            'status'             => 'draft',
        ]);
        $this->createStandardOptions($qReady, 'B');

        $r1Ready = IqpeQuestionResolution::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qReady->id, 'round_number' => 1,
            'ai_model' => 'gemini-3-flash-preview', 'prompt_tokens' => 310, 'completion_tokens' => 85, 'latency_ms' => 720,
            'solved_option_letter' => 'B', 'confidence_score' => 0.96, 'solution_explanation' => 'R1 fundamenta B.', 'status' => 'success',
        ]);
        $r2Ready = IqpeQuestionResolution::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qReady->id, 'round_number' => 2,
            'ai_model' => 'gemini-3-flash-preview', 'prompt_tokens' => 305, 'completion_tokens' => 90, 'latency_ms' => 690,
            'solved_option_letter' => 'B', 'confidence_score' => 0.95, 'solution_explanation' => 'R2 fundamenta B.', 'status' => 'success',
        ]);
        IqpeVerification::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qReady->id,
            'has_official_key' => true, 'official_key_letter' => 'B',
            'resolution_1_id' => $r1Ready->id, 'resolution_1_letter' => 'B',
            'resolution_2_id' => $r2Ready->id, 'resolution_2_letter' => 'B',
            'verdict' => 'verified_candidate', 'recommended_status' => 'verified_candidate',
            'requires_admin_review' => false, 'confidence_index' => 0.955,
        ]);
        IqpeQualityAudit::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qReady->id,
            'statement_integrity_score' => 1.0, 'options_integrity_score' => 1.0, 'media_integrity_status' => 'none',
            'duplicate_risk_flag' => false, 'passed_quality_check' => true,
            'audit_warnings' => ['overall_classification' => 'ready_for_approval', 'highest_severity' => 'none', 'findings_count' => 0, 'findings' => [], 'duplicate_assessment' => ['classification' => 'independent', 'is_duplicate_risk' => false, 'involves_published_question' => false, 'matches' => []]],
        ]);

        // Q2: Sin clave oficial -> R1=A, R2=A, R3=A + posible duplicado contra $publishedRef
        $qNoKeyDup = Question::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 1,
            'source_document_id' => $doc->id,
            'order_in_group'     => 3,
            'statement'          => 'Pregunta muy similar a la pregunta publicada de referencia sobre comprensión literal.',
            'key_status'         => 'unverified',
            'status'             => 'draft',
        ]);
        $this->createStandardOptions($qNoKeyDup, null);

        $r1NoKey = IqpeQuestionResolution::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qNoKeyDup->id, 'round_number' => 1,
            'ai_model' => 'gemini-3-flash-preview', 'prompt_tokens' => 210, 'completion_tokens' => 70, 'latency_ms' => 540,
            'solved_option_letter' => 'A', 'confidence_score' => 0.91, 'solution_explanation' => 'R1 elige A.', 'status' => 'success',
        ]);
        $r2NoKey = IqpeQuestionResolution::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qNoKeyDup->id, 'round_number' => 2,
            'ai_model' => 'gemini-3-flash-preview', 'prompt_tokens' => 215, 'completion_tokens' => 74, 'latency_ms' => 560,
            'solved_option_letter' => 'A', 'confidence_score' => 0.92, 'solution_explanation' => 'R2 elige A.', 'status' => 'success',
        ]);
        $r3NoKey = IqpeQuestionResolution::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qNoKeyDup->id, 'round_number' => 3,
            'ai_model' => 'gemini-3.1-flash-lite', 'prompt_tokens' => 225, 'completion_tokens' => 88, 'latency_ms' => 610,
            'solved_option_letter' => 'A', 'confidence_score' => 0.93, 'solution_explanation' => 'R3 independiente confirma A.', 'visual_interpretation' => 'Diagrama textual claro.', 'status' => 'success',
        ]);
        IqpeVerification::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qNoKeyDup->id,
            'has_official_key' => false, 'official_key_letter' => null,
            'resolution_1_id' => $r1NoKey->id, 'resolution_1_letter' => 'A',
            'resolution_2_id' => $r2NoKey->id, 'resolution_2_letter' => 'A',
            'resolution_3_id' => $r3NoKey->id, 'resolution_3_letter' => 'A',
            'verdict' => 'verified_candidate_no_official_key', 'recommended_status' => 'requires_admin_review',
            'requires_admin_review' => true, 'confidence_index' => 0.92,
            'discrepancy_details' => 'Sin clave oficial: R1=R2=R3=A, requiere aprobación humana.',
        ]);
        IqpeQualityAudit::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qNoKeyDup->id,
            'statement_integrity_score' => 1.0, 'options_integrity_score' => 1.0, 'media_integrity_status' => 'none',
            'duplicate_risk_flag' => true, 'duplicate_candidate_id' => $publishedRef->id, 'passed_quality_check' => false,
            'audit_warnings' => [
                'overall_classification' => 'requires_review',
                'highest_severity'       => 'high',
                'findings_count'         => 1,
                'findings'               => [[
                    'type' => 'probable_duplicate', 'severity' => 'high',
                    'description' => 'Posible duplicado contra pregunta ya publicada.',
                    'evidence' => ['candidate_question_id' => $publishedRef->id, 'similarity_score' => 0.89],
                    'recommendation' => 'Comparar antes de aprobar.',
                    'review_status' => 'pending_review',
                ]],
                'duplicate_assessment'   => [
                    'classification' => 'probable_duplicate', 'is_duplicate_risk' => true, 'involves_published_question' => true,
                    'primary_candidate_id' => $publishedRef->id,
                    'matches' => [['candidate_question_id' => $publishedRef->id, 'classification' => 'probable_duplicate', 'similarity_score' => 0.89, 'involves_published' => true]],
                ],
            ],
        ]);

        // Q3: Discrepancia entre R1/R2 + problema crítico (2 opciones)
        $qCritical = Question::create([
            'id'                 => (string) Str::uuid(),
            'subject_id'         => 1,
            'source_document_id' => $doc->id,
            'order_in_group'     => 4,
            'statement'          => 'Pregunta con problema crítico de opciones incompletas y desacuerdo entre resoluciones.',
            'key_status'         => 'unverified',
            'status'             => 'draft',
        ]);
        $this->createStandardOptions($qCritical, null, 2);

        $r1Crit = IqpeQuestionResolution::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qCritical->id, 'round_number' => 1,
            'ai_model' => 'gemini-3-flash-preview', 'solved_option_letter' => 'A', 'confidence_score' => 0.6, 'solution_explanation' => 'R1=A', 'status' => 'success',
        ]);
        $r2Crit = IqpeQuestionResolution::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qCritical->id, 'round_number' => 2,
            'ai_model' => 'gemini-3-flash-preview', 'solved_option_letter' => 'B', 'confidence_score' => 0.5, 'solution_explanation' => 'R2=B', 'status' => 'success',
        ]);
        IqpeVerification::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qCritical->id,
            'has_official_key' => false, 'resolution_1_id' => $r1Crit->id, 'resolution_1_letter' => 'A',
            'resolution_2_id' => $r2Crit->id, 'resolution_2_letter' => 'B',
            'verdict' => 'ai_disagreement', 'recommended_status' => 'requires_admin_review',
            'requires_admin_review' => true, 'discrepancy_details' => 'R1=A vs R2=B',
        ]);
        IqpeQualityAudit::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qCritical->id,
            'statement_integrity_score' => 1.0, 'options_integrity_score' => 0.2, 'media_integrity_status' => 'none',
            'duplicate_risk_flag' => false, 'passed_quality_check' => false,
            'audit_warnings' => [
                'overall_classification' => 'blocked_critical',
                'highest_severity'       => 'critical',
                'findings_count'         => 1,
                'findings'               => [['type' => 'incomplete_options', 'severity' => 'critical', 'description' => 'Solo 2 opciones registradas.', 'evidence' => ['options_count' => 2], 'recommendation' => 'Agregar C y D.', 'review_status' => 'pending_review']],
                'duplicate_assessment'   => ['classification' => 'independent', 'is_duplicate_risk' => false, 'involves_published_question' => false, 'matches' => []],
            ],
        ]);

        // 4. Consultar bandeja y validar todos los filtros
        $queueAll = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->getJson('/api/v1/admin/iqpe/review-queue')
            ->assertStatus(200);

        $counts = $queueAll->json('filter_counts');
        $this->assertSame(4, $counts['all']);
        $this->assertSame(1, $counts['ready_for_approval']);
        $this->assertSame(2, $counts['requires_review']);
        $this->assertSame(2, $counts['with_official_key']); // $publishedRef + $qReady
        $this->assertSame(2, $counts['without_official_key']); // $qNoKeyDup + $qCritical
        $this->assertSame(2, $counts['discrepancies']); // $qNoKeyDup (requires_admin_review) + $qCritical (ai_disagreement)
        $this->assertSame(2, $counts['quality_issues']); // $qNoKeyDup + $qCritical
        $this->assertSame(1, $counts['duplicates']); // $qNoKeyDup
        $this->assertSame(1, $counts['published']); // $publishedRef

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->getJson('/api/v1/admin/iqpe/review-queue?filter=ready_for_approval')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $qReady->id);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->getJson('/api/v1/admin/iqpe/review-queue?filter=duplicates')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $qNoKeyDup->id);

        // 5 & 6. Detalle completo: R1, R2, R3, clave oficial (solo admin), auditoría y duplicado comparativo
        $detail = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->getJson("/api/v1/admin/questions/{$qNoKeyDup->id}/iqpe-detail")
            ->assertStatus(200)
            ->assertJsonPath('data.resolutions.r1.solved_option_letter', 'A')
            ->assertJsonPath('data.resolutions.r2.solved_option_letter', 'A')
            ->assertJsonPath('data.resolutions.r3.solved_option_letter', 'A')
            ->assertJsonPath('data.resolutions.r3.ai_model', 'gemini-3.1-flash-lite')
            ->assertJsonPath('data.resolutions.r3.latency_ms', 610)
            ->assertJsonPath('data.resolutions.r3.total_tokens', 313)
            ->assertJsonPath('data.official_key.has_official_key_in_source', false)
            ->assertJsonPath('data.duplicates.classification', 'probable_duplicate')
            ->assertJsonPath('data.duplicates.involves_published_question', true)
            ->assertJsonPath('data.duplicates.matches.0.candidate_question.id', $publishedRef->id)
            ->assertJsonPath('data.duplicates.matches.0.candidate_question.status', 'published');

        $this->assertNotEmpty($detail->json('data.quality_audit.findings'));
        $this->assertNotNull(Question::find($publishedRef->id));
    }

    public function test_7_8_9_10_11_approval_blocking_correction_rejection_and_independent_publishing(): void
    {
        $doc = SourceDocument::create([
            'id' => (string) Str::uuid(), 'title' => 'Cuadernillo Workflow B6',
            'storage_path' => 'documents/2026/b6-wf.pdf', 'year' => 2026,
            'exam_type' => 'Saber 11', 'has_answer_key' => true, 'status' => 'extracted',
        ]);
        $job = IqpeDocumentJob::create([
            'id' => (string) Str::uuid(), 'source_document_id' => $doc->id, 'run_number' => 1, 'status' => 'completed',
        ]);

        // Pregunta A: Sana con clave oficial B e imagen válida en Storage
        $qValid = Question::create([
            'id' => (string) Str::uuid(), 'subject_id' => 2, 'source_document_id' => $doc->id, 'order_in_group' => 1,
            'statement' => 'Un tren recorre 180 km en 3 horas a velocidad constante. ¿Cuál es su rapidez media?',
            'question_image_path' => 'extracted/b6/tren.png',
            'key_status' => 'verified', 'status' => 'draft',
        ]);
        $this->createStandardOptions($qValid, 'B');

        // Pregunta B: Problema crítico (solo 2 opciones y auditoría blocked_critical)
        $qBlocked = Question::create([
            'id' => (string) Str::uuid(), 'subject_id' => 2, 'source_document_id' => $doc->id, 'order_in_group' => 2,
            'statement' => 'Pregunta con problema crítico de opciones incompletas pendiente de corrección.',
            'key_status' => 'unverified', 'status' => 'draft',
        ]);
        $this->createStandardOptions($qBlocked, null, 2);

        IqpeQualityAudit::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qBlocked->id,
            'statement_integrity_score' => 1.0, 'options_integrity_score' => 0.2, 'media_integrity_status' => 'none',
            'duplicate_risk_flag' => false, 'passed_quality_check' => false,
            'audit_warnings' => [
                'overall_classification' => 'blocked_critical',
                'highest_severity'       => 'critical',
                'findings_count'         => 1,
                'findings'               => [['type' => 'incomplete_options', 'severity' => 'critical', 'description' => 'Faltan opciones C y D.', 'evidence' => ['options_count' => 2], 'recommendation' => 'Completar 4 opciones.', 'review_status' => 'pending_review']],
                'duplicate_assessment'   => ['classification' => 'independent', 'is_duplicate_risk' => false, 'involves_published_question' => false, 'matches' => []],
            ],
        ]);

        // Pregunta C: Para rechazo y devolución a revisión
        $qRejectable = Question::create([
            'id' => (string) Str::uuid(), 'subject_id' => 2, 'source_document_id' => $doc->id, 'order_in_group' => 3,
            'statement' => 'Pregunta candidata a rechazo administrativo con motivo documentado.',
            'key_status' => 'verified', 'status' => 'draft',
        ]);
        $this->createStandardOptions($qRejectable, 'A');
        $resRejectable = IqpeQuestionResolution::create([
            'id' => (string) Str::uuid(), 'job_id' => $job->id, 'question_id' => $qRejectable->id, 'round_number' => 1,
            'ai_model' => 'gemini-3-flash-preview', 'solved_option_letter' => 'A', 'confidence_score' => 0.9, 'solution_explanation' => 'R1', 'status' => 'success',
        ]);

        // Registrar mock de Storage antes de instanciar la ruta /publish en el contenedor
        $storage = Mockery::mock(SupabaseStorageService::class);
        $storage->shouldReceive('exists')->with('icfes-media', 'extracted/b6/tren.png')->twice()->andReturnValues([false, true]);
        $this->app->instance(SupabaseStorageService::class, $storage);

        // 11. Bloqueo de publicación antes de aprobar (draft -> publish bloqueado)
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qValid->id}/publish")
            ->assertStatus(422);

        // 7. Aprobación explícita de pregunta válida -> pasa a 'review' (NO publica automáticamente!)
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qValid->id}/approve", ['notes' => 'Verificada contra cuadernillo oficial'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'review');

        // Aprobación duplicada bloqueada (409)
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qValid->id}/approve")
            ->assertStatus(409);

        // 8. Bloqueo de aprobación por problema crítico
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qBlocked->id}/approve")
            ->assertStatus(422);

        // Intentar alterar una clave oficial verificada silenciosamente (sin motivo) debe bloquearse (422)
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->putJson("/api/v1/admin/questions/{$qValid->id}", ['key_status' => 'verified', 'correct_option' => 'C'])
            ->assertStatus(422);

        // Corregir $qBlocked completando las 4 opciones y asignando clave manualmente con motivo registrado
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->putJson("/api/v1/admin/questions/{$qBlocked->id}", [
                'key_status'        => 'verified',
                'correct_option'    => 'C',
                'manual_key_reason' => 'Clave asignada manualmente tras revisión docente (no existía en PDF).',
                'options'           => [
                    ['option_letter' => 'A', 'option_text' => '10 metros'],
                    ['option_letter' => 'B', 'option_text' => '20 metros'],
                    ['option_letter' => 'C', 'option_text' => '30 metros'],
                    ['option_letter' => 'D', 'option_text' => '40 metros'],
                ],
            ])->assertStatus(200);

        $manualDecision = IqpeAdminDecision::where('question_id', $qBlocked->id)->where('action', 'manual_key_set')->first();
        $this->assertNotNull($manualDecision);
        $this->assertTrue($manualDecision->manual_key_assigned);

        // Al haberse corregido el problema crítico, la re-auditoría automática permite aprobar $qBlocked
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qBlocked->id}/approve", ['notes' => 'Aprobada tras corregir opciones C y D'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'review');

        // 9. Rechazo con motivo obligatorio
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qRejectable->id}/reject", ['reason' => ''])
            ->assertStatus(422);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qRejectable->id}/reject", ['reason' => 'Pregunta desactualizada del currículo oficial'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'archived');

        // Conservar resoluciones IA y trazabilidad de rechazo sin eliminar pregunta
        $this->assertNotNull(Question::find($qRejectable->id));
        $this->assertNotNull(IqpeQuestionResolution::find($resRejectable->id));
        $this->assertSame('rejected', IqpeAdminDecision::where('question_id', $qRejectable->id)->latest('created_at')->first()->action);

        // Devolver a revisión sin perder historial
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qRejectable->id}/return-to-review", ['reason' => 'Se reabre para revisión de comité'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'draft');
        $this->assertNotNull(IqpeQuestionResolution::find($resRejectable->id));

        // 10 & 11. Publicación independiente tras verificar recurso visual en Storage
        // Si el recurso visual no existe en Storage, la publicación se bloquea (422)
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qValid->id}/publish")
            ->assertStatus(422);

        // Cuando el recurso existe y todo es íntegro, publica exitosamente ('review' -> 'published')
        $pubResp = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qValid->id}/publish", ['notes' => 'Publicación oficial Bloque 6']);
        $this->assertSame(200, $pubResp->status(), $pubResp->getContent());
        $pubResp->assertJsonPath('data.status', 'published');

        $pubDecision = IqpeAdminDecision::where('question_id', $qValid->id)->where('action', 'published')->first();
        $this->assertNotNull($pubDecision);
        $this->assertSame('review', $pubDecision->previous_status);
        $this->assertSame('published', $pubDecision->new_status);
        $this->assertSame('admin-b6-1', $pubDecision->admin_id);
    }

    public function test_12_13_14_student_apis_never_leak_keys_work_properly_and_preserve_49_published_questions(): void
    {
        $baseDoc = SourceDocument::create([
            'id' => (string) Str::uuid(), 'title' => 'Cuadernillo Base 49 Publicadas',
            'storage_path' => 'documents/2026/base49.pdf', 'year' => 2026,
            'exam_type' => 'Saber 11', 'has_answer_key' => true, 'status' => 'extracted',
        ]);

        // Sembrar exactamente las 49 preguntas publicadas y 10 respuestas estudiantiles previas
        $firstPublished = null;
        for ($i = 1; $i <= 49; $i++) {
            $q = Question::create([
                'id'                 => (string) Str::uuid(),
                'subject_id'         => 1,
                'source_document_id' => $baseDoc->id,
                'order_in_group'     => $i,
                'statement'          => "Pregunta oficial publicada #{$i} de Lectura Crítica.",
                'explanation'        => "Explicación confidencial #{$i}",
                'key_status'         => 'verified',
                'status'             => 'published',
            ]);
            $this->createStandardOptions($q, 'B');
            if ($i === 1) {
                $firstPublished = $q;
            }
        }

        $answerIds = [];
        for ($j = 1; $j <= 10; $j++) {
            $optB = $firstPublished->options()->where('option_letter', 'B')->first();
            $ansId = (string) Str::uuid();
            StudentAnswer::create([
                'id'                     => $ansId,
                'student_id'             => 'student-hist-' . $j,
                'question_id'            => $firstPublished->id,
                'selected_option_id'     => $optB->id,
                'selected_option_letter' => 'B',
                'is_correct'             => true,
                'practice_mode'          => 'libre',
                'time_spent_seconds'     => 25,
                'answered_at'            => now(),
            ]);
            $answerIds[] = $ansId;
        }

        // Además: 1 pregunta aprobada ('review') con IQPE R1/R2 y 1 borrador ('draft') que NO deben aparecer en API estudiantil
        $qApprovedNotPublished = Question::create([
            'id' => (string) Str::uuid(), 'subject_id' => 1, 'source_document_id' => $baseDoc->id,
            'order_in_group' => 50, 'statement' => 'Pregunta aprobada pero aún NO publicada para estudiantes.',
            'explanation' => 'Explicación secreta', 'key_status' => 'verified', 'status' => 'review',
        ]);
        $this->createStandardOptions($qApprovedNotPublished, 'C');

        // 12. APIs estudiantiles NO exponen is_correct, explanation ni resoluciones IQPE antes de responder
        $studentList = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->getJson('/api/v1/student/questions?per_page=100')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 49);

        foreach ($studentList->json('data') as $qItem) {
            $this->assertArrayNotHasKey('explanation', $qItem);
            $this->assertArrayNotHasKey('iqpe_verifications', $qItem);
            $this->assertArrayNotHasKey('iqpe_resolutions', $qItem);
            foreach ($qItem['options'] as $opt) {
                $this->assertArrayNotHasKey('is_correct', $opt);
            }
        }

        // La pregunta en 'review' (aprobada pero sin publicar) está bloqueada en API estudiantil (404)
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->getJson("/api/v1/student/questions/{$qApprovedNotPublished->id}")
            ->assertStatus(404);

        // 13. Práctica y Simulacro siguen funcionando sin cambios de contrato
        $showFirst = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->getJson("/api/v1/student/questions/{$firstPublished->id}")
            ->assertStatus(200);
        $this->assertArrayNotHasKey('is_correct', $showFirst->json('data.options.0'));

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->postJson("/api/v1/student/questions/{$firstPublished->id}/answer", [
                'selected_option_letter' => 'B',
                'practice_mode'          => 'libre',
                'time_spent_seconds'     => 18,
            ])->assertStatus(200)
            ->assertJsonPath('data.is_correct', true);

        $examStart = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->postJson('/api/v1/student/exams/start', [
                'subject_id'      => 1,
                'questions_count' => 10,
                'mode'            => 'area',
            ])->assertStatus(201);

        $attemptId = $examStart->json('data.attempt.id');
        foreach ($examStart->json('data.questions') as $examQ) {
            foreach ($examQ['options'] as $opt) {
                $this->assertArrayNotHasKey('is_correct', $opt);
            }
        }

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->postJson("/api/v1/student/exams/{$attemptId}/finish")
            ->assertStatus(200)
            ->assertJsonPath('data.attempt.status', 'completed');

        // 14. Las 49 preguntas publicadas originales y las 10 respuestas históricas permanecen intactas
        $this->assertSame(49, Question::where('status', 'published')->count());
        $this->assertSame(10, StudentAnswer::whereIn('id', $answerIds)->count());

        // Publicar la #50 (aprobada) la hace visible al estudiante inmediatamente (49 + 1 = 50)
        $this->app->instance(SupabaseStorageService::class, Mockery::mock(SupabaseStorageService::class));
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->postJson("/api/v1/admin/questions/{$qApprovedNotPublished->id}/publish")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken])
            ->getJson("/api/v1/student/questions/{$qApprovedNotPublished->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $qApprovedNotPublished->id);

        $this->assertSame(50, Question::where('status', 'published')->count());
        $this->assertSame(10, StudentAnswer::whereIn('id', $answerIds)->count());
    }
}
