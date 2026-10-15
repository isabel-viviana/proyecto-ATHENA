<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_config_id')->nullable()->constrained('exam_configs')->nullOnDelete();
            $table->enum('mode', ['quick', 'full', 'adaptive', 'practice']);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('incorrect_answers')->default(0);
            $table->unsignedInteger('omitted_answers')->default(0);
            $table->unsignedBigInteger('xp_earned')->default(0);
            $table->unsignedBigInteger('coins_earned')->default(0);
            $table->enum('status', ['in_progress', 'completed', 'cancelled']);
            $table->timestamps();

            $table->index('user_id');
            $table->index('exam_config_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
