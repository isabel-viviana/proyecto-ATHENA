<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_item_id')->constrained('store_items')->cascadeOnDelete();
            $table->unsignedBigInteger('coins_spent')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('store_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
