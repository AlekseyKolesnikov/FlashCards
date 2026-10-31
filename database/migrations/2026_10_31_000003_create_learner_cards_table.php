<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A learner's progress for one card in one direction (SPECIFICATION.md §8.1).
        Schema::create('learner_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_lang_id')->constrained('languages');
            $table->unsignedSmallInteger('retry')->default(1);
            // Number of the session in which retry last reached 0; NULL while active.
            $table->unsignedInteger('learned_session')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'card_id', 'from_lang_id']);
            $table->index(['user_id', 'from_lang_id', 'retry']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learner_cards');
    }
};
