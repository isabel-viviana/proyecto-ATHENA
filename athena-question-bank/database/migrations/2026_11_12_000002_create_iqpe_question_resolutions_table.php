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
        Schema::connection('question_bank')->create('iqpe_question_resolutions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')
                ->constrained('iqpe_document_jobs')
                ->cascadeOnDelete();
            $table->foreignUuid('question_id')
                ->constrained('questions')
                ->cascadeOnDelete();
            $table->smallInteger('round_number'); // 1 = R1 independiente, 2 = R2 independiente, 3 = R3 arbitraje/desempate
            $table->string('ai_model', 100);
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->char('solved_option_letter', 1)->nullable(); // A, B, C, D
            $table->decimal('confidence_score', 4, 3)->nullable(); // 0.000 a 1.000
            $table->text('solution_explanation'); // Explicación pedagógica paso a paso
            $table->text('visual_interpretation')->nullable(); // Interpretación de figuras/gráficas
            $table->string('status', 30)->default('success'); // success, uncertain, failed, timeout
            $table->jsonb('raw_metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['job_id', 'question_id', 'round_number']);
            $table->index(['question_id', 'round_number']);
            $table->index(['ai_model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('question_bank')->dropIfExists('iqpe_question_resolutions');
    }
};

