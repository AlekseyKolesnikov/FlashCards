<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Named study_* because Laravel's "sessions" table holds login sessions.
     */
    public function up(): void
    {
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_lang_id')->constrained('languages');
            $table->foreignId('to_lang_id')->constrained('languages');
            // Numbered 1, 2, 3... per learner and direction.
            $table->unsignedInteger('seq');
            $table->enum('status', ['open', 'finished'])->default('open');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'from_lang_id', 'to_lang_id', 'seq']);
        });

        Schema::create('study_session_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            // Queue order: the next card is the lowest position that is not done.
            $table->unsignedInteger('position');
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->boolean('done')->default(false);

            $table->unique(['study_session_id', 'card_id']);
            $table->index(['study_session_id', 'done', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_session_cards');
        Schema::dropIfExists('study_sessions');
    }
};
