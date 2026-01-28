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
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('template_key')->unique();
            $table->string('category')->default('Général');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->boolean('is_active')->default(true);
            $table->integer('questions_count')->default(0);
            $table->string('source')->default('Wikidata');
            $table->integer('estimated_duration_minutes')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
            $table->index(['difficulty', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
