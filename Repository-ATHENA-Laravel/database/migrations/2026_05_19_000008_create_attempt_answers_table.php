<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('question_options')->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->boolean('explanation_viewed')->default(false);
            $table->timestamps();

            $table->index('exam_attempt_id');
            $table->index('question_id');
            $table->index('selected_option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
    }
};
