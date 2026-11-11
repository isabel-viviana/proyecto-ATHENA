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
        Schema::connection('question_bank')->create('iqpe_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')
                ->constrained('iqpe_document_jobs')
                ->cascadeOnDelete();
            $table->foreignUuid('question_id')
                ->constrained('questions')
                ->cascadeOnDelete();
            
            $table->boolean('has_official_key')->default(false);
            $table->char('official_key_letter', 1)->nullable();

            // Resoluciones independientes R1 y R2
            $table->foreignUuid('resolution_1_id')->nullable()
                ->constrained('iqpe_question_resolutions')
                ->nullOnDelete();
            $table->char('resolution_1_letter', 1)->nullable();

            $table->foreignUuid('resolution_2_id')->nullable()
                ->constrained('iqpe_question_resolutions')
                ->nullOnDelete();
            $table->char('resolution_2_letter', 1)->nullable();

            // Resolución R3 (desempate o arbitraje si no hay clave oficial)
            $table->foreignUuid('resolution_3_id')->nullable()
                ->constrained('iqpe_question_resolutions')
                ->nullOnDelete();
            $table->char('resolution_3_letter', 1)->nullable();

            // Veredicto del motor IQPE
            $table->string('verdict', 50); // consensus_with_official, consensus_no_official, official_discrepancy, ai_disagreement, inconclusive, visual_risk
            $table->string('recommended_status', 30)->default('requires_review'); // ready_for_approval, requires_review
            $table->boolean('requires_admin_review')->default(true);
            $table->boolean('is_approved_by_admin')->default(false);
            $table->decimal('confidence_index', 4, 3)->default(0.000); // 0.000 a 1.000
            $table->text('discrepancy_details')->nullable();
            $table->timestampsTz();

            $table->unique(['job_id', 'question_id']);
            $table->index(['question_id', 'verdict']);
            $table->index(['recommended_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('question_bank')->dropIfExists('iqpe_verifications');
    }
};

