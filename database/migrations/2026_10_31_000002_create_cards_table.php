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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('complexity')->default(5);

            foreach ([1, 2] as $side) {
                // Languages in use cannot be deleted (restrict).
                $table->foreignId("lang{$side}_id")->constrained('languages');
                $table->string("word{$side}");
                // Normalized word for duplicate checks. Binary collation, because
                // utf8mb4_unicode_ci would treat "café" and "cafe" as equal.
                $table->string("word{$side}_norm")->collation('utf8mb4_bin');
                $table->string("notes{$side}")->nullable();
                $table->text("examples{$side}")->nullable();
            }

            $table->timestamps();

            $table->index(['lang1_id', 'lang2_id', 'complexity']);
            $table->index(['lang1_id', 'word1_norm']);
            $table->index(['lang2_id', 'word2_norm']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
