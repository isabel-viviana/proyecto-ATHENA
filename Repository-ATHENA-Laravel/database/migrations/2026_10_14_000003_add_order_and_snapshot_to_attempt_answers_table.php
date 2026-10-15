<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->unsignedSmallInteger('order_position')->default(1)->after('question_id');
            $table->foreignId('snapshot_correct_option_id')->nullable()->after('selected_option_id')->constrained('question_options')->nullOnDelete();

            $table->index(['exam_attempt_id', 'order_position']);
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropForeign(['snapshot_correct_option_id']);
            $table->dropIndex(['exam_attempt_id', 'order_position']);
            $table->dropColumn(['order_position', 'snapshot_correct_option_id']);
        });
    }
};
