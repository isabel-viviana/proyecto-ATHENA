<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retira de forma segura e idempotente las tablas y columnas de gamificación
     * (conservando Ranking y Racha de estudio en users.streak_days) y las tablas
     * SQL legacy de chat IA (reemplazadas por MongoDB).
     */
    public function up(): void
    {
        // 1. Eliminar tablas dependientes primero (respetando llaves foráneas)
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('store_items');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');

        // 2. Eliminar columnas de gamificación en users (CONSERVANDO users.streak_days)
        if (Schema::hasTable('users')) {
            $columnsToDrop = [];
            foreach (['level', 'xp', 'coins'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (! empty($columnsToDrop)) {
                Schema::table('users', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
        }

        // 3. Eliminar columnas de gamificación en exam_attempts
        if (Schema::hasTable('exam_attempts')) {
            $attemptColsToDrop = [];
            foreach (['xp_earned', 'coins_earned'] as $col) {
                if (Schema::hasColumn('exam_attempts', $col)) {
                    $attemptColsToDrop[] = $col;
                }
            }

            if (! empty($attemptColsToDrop)) {
                Schema::table('exam_attempts', function (Blueprint $table) use ($attemptColsToDrop) {
                    $table->dropColumn($attemptColsToDrop);
                });
            }
        }
    }

    /**
     * Restaura las tablas y columnas eliminadas en caso de rollback.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'level')) {
                    $table->unsignedInteger('level')->default(1);
                }
                if (! Schema::hasColumn('users', 'xp')) {
                    $table->unsignedBigInteger('xp')->default(0);
                }
                if (! Schema::hasColumn('users', 'coins')) {
                    $table->unsignedBigInteger('coins')->default(0);
                }
            });
        }

        if (Schema::hasTable('exam_attempts')) {
            Schema::table('exam_attempts', function (Blueprint $table) {
                if (! Schema::hasColumn('exam_attempts', 'xp_earned')) {
                    $table->unsignedBigInteger('xp_earned')->default(0);
                }
                if (! Schema::hasColumn('exam_attempts', 'coins_earned')) {
                    $table->unsignedBigInteger('coins_earned')->default(0);
                }
            });
        }

        if (! Schema::hasTable('achievements')) {
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

        if (! Schema::hasTable('user_achievements')) {
            Schema::create('user_achievements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('achievement_id')->constrained('achievements')->cascadeOnDelete();
                $table->timestamp('unlocked_at')->nullable();
                $table->unsignedInteger('progress')->default(0);
                $table->timestamps();
                $table->unique(['user_id', 'achievement_id']);
                $table->index('user_id');
                $table->index('achievement_id');
            });
        }

        if (! Schema::hasTable('store_items')) {
            Schema::create('store_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->enum('category', ['avatar', 'theme', 'effect', 'booster']);
                $table->unsignedBigInteger('price_coins')->default(0);
                $table->string('image_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('category');
                $table->index('is_active');
            });
        }

        if (! Schema::hasTable('purchases')) {
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

        if (! Schema::hasTable('inventories')) {
            Schema::create('inventories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('store_item_id')->constrained('store_items')->cascadeOnDelete();
                $table->boolean('equipped')->default(false);
                $table->timestamp('acquired_at')->useCurrent();
                $table->timestamps();
                $table->unique(['user_id', 'store_item_id']);
                $table->index('user_id');
                $table->index('store_item_id');
            });
        }

        if (! Schema::hasTable('ai_chat_sessions')) {
            Schema::create('ai_chat_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('title')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('ai_chat_messages')) {
            Schema::create('ai_chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('ai_chat_sessions')->cascadeOnDelete();
                $table->enum('sender_type', ['user', 'ai']);
                $table->text('message');
                $table->timestamp('sent_at')->useCurrent();
                $table->timestamps();
                $table->index('session_id');
            });
        }
    }
};
