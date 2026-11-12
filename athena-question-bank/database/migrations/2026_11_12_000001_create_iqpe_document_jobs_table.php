<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('question_bank')->create('iqpe_document_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_document_id')
                ->constrained('source_documents')
                ->cascadeOnDelete();
            $table->integer('run_number')->default(1);
            $table->string('status', 40)->default('queued'); // queued, extracting, solving_round_1, solving_round_2, arbitrating, auditing, completed, failed
            $table->integer('total_questions_detected')->default(0);
            $table->integer('total_questions_verified')->default(0);
            $table->integer('total_questions_discrepancy')->default(0);
            $table->jsonb('config')->nullable(); // models, temperature, fallbacks
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('error_log')->nullable();
            $table->timestampsTz();

            $table->unique(['source_document_id', 'run_number']);
            $table->index(['source_document_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('question_bank')->dropIfExists('iqpe_document_jobs');
    }
};

