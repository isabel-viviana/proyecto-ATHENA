<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', ['study', 'exams', 'streak', 'special']);
            $table->unsignedBigInteger('xp_reward')->default(0);
            $table->unsignedBigInteger('coin_reward')->default(0);
            $table->text('unlock_rule');
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
