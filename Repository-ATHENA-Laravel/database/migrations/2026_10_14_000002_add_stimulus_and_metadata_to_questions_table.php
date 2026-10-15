<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('stimulus_id')->nullable()->after('topic_id')->constrained('stimuli')->nullOnDelete();
            $table->unsignedTinyInteger('order_in_group')->default(1)->after('stimulus_id');
            $table->string('question_image_url', 500)->nullable()->after('statement');
            $table->string('source_pdf', 255)->nullable()->after('source');
            $table->unsignedSmallInteger('source_page')->nullable()->after('source_pdf');
            $table->unsignedSmallInteger('original_question_number')->nullable()->after('source_page');
            $table->enum('status', ['draft', 'review', 'published', 'archived'])->default('published')->after('original_question_number');
            $table->softDeletes();

            $table->index('stimulus_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['stimulus_id']);
            $table->dropIndex(['stimulus_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'stimulus_id',
                'order_in_group',
                'question_image_url',
                'source_pdf',
                'source_page',
                'original_question_number',
                'status',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
