<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait UsesInMemoryQuestionBankSchema
{
    protected function bootInMemoryQuestionBank(): void
    {
        $sqliteConfig = [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
            'foreign_key_constraints' => true,
        ];

        config([
            'database.default'                       => 'sqlite',
            'database.connections.sqlite'            => $sqliteConfig,
            'database.connections.question_bank'     => $sqliteConfig,
            'queue.default'                          => 'database',
            'queue.connections.database.connection'  => 'question_bank',
            'queue.failed.database'                  => 'question_bank',
            'services.gemini.api_key'                => 'test-fake-gemini-key',
            'services.gemini.solver_model'           => 'gemini-3-flash-preview',
            'services.gemini.solver_fallback_model'  => 'gemini-3.1-flash-lite',
            'services.gemini.solver_concurrency'     => 1,
            'services.gemini.solver_cooldown_ms'     => 0,
            'services.gemini.solver_backoff_seconds' => [0, 0, 0],
            'services.gemini.solver_max_retry_wait_seconds' => 0,
        ]);

        DB::purge('sqlite');
        DB::purge('question_bank');

        // Compartir exactamente el mismo PDO en memoria entre 'sqlite' y 'question_bank'
        $sharedPdo = DB::connection('sqlite')->getPdo();
        DB::connection('question_bank')->setPdo($sharedPdo);
        DB::connection('question_bank')->setReadPdo($sharedPdo);

        $schema = Schema::connection('question_bank');

        $schema->create('subjects', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name', 120);
            $table->string('code', 60)->nullable();
        });

        $schema->create('topics', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('subject_id');
            $table->string('name', 180);
        });

        $schema->create('source_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('storage_path', 500);
            $table->integer('year')->nullable();
            $table->string('exam_type', 100)->default('Saber 11');
            $table->integer('total_pages')->nullable();
            $table->boolean('has_answer_key')->default(false);
            $table->string('status', 40)->default('uploaded');
            $table->json('extraction_metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $schema->create('stimuli', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('subject_id')->nullable();
            $table->uuid('source_document_id');
            $table->string('title')->nullable();
            $table->text('content_text')->nullable();
            $table->string('media_type', 30)->default('none');
            $table->string('media_path', 500)->nullable();
            $table->smallInteger('source_page')->nullable();
            $table->text('instructions')->nullable();
            $table->text('visual_transcription')->nullable();
            $table->text('visual_description')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $schema->create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('root_question_id')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_current_version')->default(true);
            $table->integer('subject_id');
            $table->integer('topic_id')->nullable();
            $table->uuid('stimulus_id')->nullable();
            $table->uuid('source_document_id');
            $table->integer('order_in_group')->default(1);
            $table->text('statement');
            $table->string('question_image_path', 500)->nullable();
            $table->smallInteger('source_page')->nullable();
            $table->text('visual_transcription')->nullable();
            $table->text('visual_description')->nullable();
            $table->text('extraction_warnings')->nullable();
            $table->string('difficulty', 30)->default('medium');
            $table->text('explanation')->nullable();
            $table->string('key_status', 30)->default('unverified');
            $table->string('status', 30)->default('draft');
            $table->timestamps();
        });

        $schema->create('question_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_id');
            $table->char('option_letter', 1);
            $table->text('option_text');
            $table->string('option_image_path', 500)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $schema->create('media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('source_document_id');
            $table->string('storage_path', 500)->unique();
            $table->smallInteger('page_number');
            $table->integer('width');
            $table->integer('height');
            $table->string('mime_type', 100);
            $table->char('sha256', 64);
            $table->uuid('stimulus_id')->nullable();
            $table->uuid('question_id')->nullable();
            $table->string('association_status', 20)->default('pending');
            $table->timestamp('associated_at')->nullable();
            $table->text('visual_transcription')->nullable();
            $table->text('visual_description')->nullable();
            $table->float('confidence')->nullable();
            $table->text('warnings')->nullable();
            $table->timestamps();
        });

        $schema->create('exam_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('student_id', 255);
            $table->integer('subject_id')->nullable()->default(1);
            $table->string('title', 255);
            $table->string('mode', 50)->default('area');
            $table->integer('questions_count');
            $table->integer('duration_minutes');
            $table->timestampTz('started_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('finished_at')->nullable();
            $table->string('status', 50)->default('in_progress');
            $table->integer('score')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->integer('incorrect_answers')->default(0);
            $table->integer('omitted_answers')->default(0);
            $table->decimal('accuracy', 8, 2)->default(0.00);
            $table->integer('total_time_seconds')->default(0);
            $table->json('question_ids')->default('[]');
            $table->json('flagged_question_ids')->default('[]');
            $table->timestampsTz();
        });

        $schema->create('exam_attempt_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->integer('order_position');
            $table->uuid('selected_option_id')->nullable();
            $table->string('selected_option_letter', 1)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->integer('time_spent_seconds')->default(0);
            $table->boolean('flagged')->default(false);
            $table->timestampsTz();
        });

        // Ejecutar migraciones reales del motor IQPE, student_answers y colas
        foreach ([
            database_path('migrations/0001_01_01_000002_create_jobs_table.php'),
            database_path('migrations/2026_11_10_000001_create_student_answers_table.php'),
            database_path('migrations/2026_11_12_000001_create_iqpe_document_jobs_table.php'),
            database_path('migrations/2026_11_12_000002_create_iqpe_question_resolutions_table.php'),
            database_path('migrations/2026_11_12_000003_create_iqpe_verifications_table.php'),
            database_path('migrations/2026_11_12_000004_create_iqpe_quality_audits_table.php'),
            database_path('migrations/2026_11_12_000005_create_iqpe_execution_logs_table.php'),
            database_path('migrations/2026_11_13_000001_create_iqpe_admin_decisions_table.php'),
        ] as $migrationFile) {
            $migration = require $migrationFile;
            $migration->up();
        }

        DB::connection('question_bank')->table('subjects')->insert([
            ['id' => 1, 'name' => 'Lectura Crítica', 'code' => 'lectura-critica'],
            ['id' => 2, 'name' => 'Matemáticas', 'code' => 'matematicas'],
            ['id' => 3, 'name' => 'Ciencias Naturales', 'code' => 'ciencias-naturales'],
            ['id' => 4, 'name' => 'Sociales y Ciudadanas', 'code' => 'sociales-ciudadanas'],
            ['id' => 5, 'name' => 'Inglés', 'code' => 'ingles'],
        ]);
    }
}

