<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_configs', function (Blueprint $table) {
            $table->foreignId('topic_id')->nullable()->after('user_id')->constrained('topics')->nullOnDelete();
            $table->unsignedSmallInteger('questions_count')->default(25)->after('difficulty');

            $table->index('topic_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_configs', function (Blueprint $table) {
            $table->dropForeign(['topic_id']);
            $table->dropIndex(['topic_id']);
            $table->dropColumn(['topic_id', 'questions_count']);
        });
    }
};
