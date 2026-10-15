<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stimuli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->longText('content_text')->nullable();
            $table->enum('media_type', ['none', 'image', 'chart', 'table', 'mixed'])->default('none');
            $table->string('media_url', 500)->nullable();
            $table->string('media_caption', 255)->nullable();
            $table->string('source_pdf', 255)->nullable();
            $table->unsignedSmallInteger('source_page')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stimuli');
    }
};
