<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'question_bank';

    public function up(): void
    {
        if (!Schema::connection('question_bank')->hasTable('student_answers')) {
            Schema::connection('question_bank')->create('student_answers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('student_id')->nullable()->index();
                $table->uuid('question_id')->index();
                $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
                $table->uuid('selected_option_id')->nullable()->index();
                $table->foreign('selected_option_id')->references('id')->on('question_options')->nullOnDelete();
                $table->string('selected_option_letter', 2)->nullable();
                $table->boolean('is_correct');
                $table->string('practice_mode', 50)->default('practice');
                $table->integer('time_spent_seconds')->default(0);
                $table->timestamp('answered_at')->useCurrent();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('question_bank')->dropIfExists('student_answers');
    }
};

