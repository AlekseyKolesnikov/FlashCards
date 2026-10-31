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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Unique case-insensitively thanks to the default utf8mb4_unicode_ci collation.
            $table->string('username', 32)->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'learner'])->default('learner');
            // Learner settings; NULL means "use the default from config/flashcards.php".
            $table->unsignedSmallInteger('session_size')->nullable();
            $table->unsignedSmallInteger('postpone_sessions')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
