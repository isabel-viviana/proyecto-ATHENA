<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de trazabilidad inmutable para decisiones administrativas del flujo IQPE:
     * ediciones, asignaciones manuales de clave, aprobaciones, rechazos, devoluciones y publicaciones.
     */
    public function up(): void
    {
        Schema::connection('question_bank')->create('iqpe_admin_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('question_id')
                ->constrained('questions')
                ->cascadeOnDelete();
            $table->foreignUuid('job_id')
                ->nullable()
                ->constrained('iqpe_document_jobs')
                ->nullOnDelete();

            // Acción ejecutada: approved, rejected, returned_to_review, published, edited, manual_key_set, key_overridden
            $table->string('action', 40);
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->string('previous_key_status', 20)->nullable();
            $table->string('new_key_status', 20)->nullable();
            $table->char('previous_correct_letter', 1)->nullable();
            $table->char('new_correct_letter', 1)->nullable();
            $table->boolean('manual_key_assigned')->default(false);

            // Identidad verificada del administrador desde middleware admin.auth
            $table->string('admin_id', 100);
            $table->string('admin_name', 180);
            $table->string('admin_email', 180)->nullable();
            $table->string('admin_role', 50)->nullable();

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('changes_diff')->nullable();
            $table->jsonb('snapshot')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['question_id', 'created_at']);
            $table->index(['job_id', 'action']);
            $table->index(['admin_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('question_bank')->dropIfExists('iqpe_admin_decisions');
    }
};

