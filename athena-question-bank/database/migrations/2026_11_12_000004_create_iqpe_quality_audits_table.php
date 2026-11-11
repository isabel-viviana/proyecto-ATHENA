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
        Schema::connection('question_bank')->create('iqpe_quality_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')
                ->constrained('iqpe_document_jobs')
                ->cascadeOnDelete();
            $table->foreignUuid('question_id')
                ->constrained('questions')
                ->cascadeOnDelete();
            
            $table->decimal('statement_integrity_score', 4, 3)->default(1.000);
            $table->decimal('options_integrity_score', 4, 3)->default(1.000);
            $table->string('media_integrity_status', 40)->default('none'); // none, confirmed_valid, missing_graphic_option, unverified_media, visual_ambiguity
            $table->boolean('duplicate_risk_flag')->default(false);
            $table->uuid('duplicate_candidate_id')->nullable();
            $table->jsonb('audit_warnings')->default('[]');
            $table->boolean('passed_quality_check')->default(false);
            $table->timestampsTz();

            $table->unique(['job_id', 'question_id']);
            $table->index(['question_id', 'passed_quality_check']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('question_bank')->dropIfExists('iqpe_quality_audits');
    }
};

