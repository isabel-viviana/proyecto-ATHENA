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
        Schema::connection('question_bank')->create('iqpe_execution_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('job_id')
                ->constrained('iqpe_document_jobs')
                ->cascadeOnDelete();
            $table->uuid('question_id')->nullable();
            $table->string('stage', 50); // pdf_ingestion, image_extraction, question_extraction, solver_r1, solver_r2, solver_r3, arbitration, quality_audit
            $table->string('level', 20)->default('info'); // info, warning, error
            $table->text('message');
            $table->jsonb('context')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['job_id', 'stage']);
            $table->index(['job_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('question_bank')->dropIfExists('iqpe_execution_logs');
    }
};

