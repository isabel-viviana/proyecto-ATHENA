<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_config_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_config_id')->constrained('exam_configs')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_config_id', 'subject_id']);
            $table->index('exam_config_id');
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_config_subjects');
    }
};
