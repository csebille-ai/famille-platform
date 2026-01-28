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
        Schema::create('quiz_best_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('best_score')->default(0);
            $table->foreignId('best_attempt_id')->nullable()->constrained('quiz_attempts')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['quiz_id', 'user_id']);
            $table->index(['quiz_id', 'best_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_best_scores');
    }
};
