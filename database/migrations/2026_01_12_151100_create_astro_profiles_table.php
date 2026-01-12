<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('astro_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('western_sign', 32)->nullable();
            $table->string('western_element', 16)->nullable();

            $table->string('chinese_animal', 32)->nullable();
            $table->string('chinese_element', 16)->nullable();
            $table->string('chinese_yin_yang', 8)->nullable();

            $table->unsignedSmallInteger('life_path')->nullable();

            $table->string('ascendant_sign', 32)->nullable();

            // Optional: if a real natal chart provider is configured.
            $table->json('natal')->nullable();

            // Fun mix / RPG-like output
            $table->string('archetype', 64)->nullable();
            $table->json('talents')->nullable();
            $table->string('weakness', 128)->nullable();
            $table->text('signature')->nullable();

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('astro_profiles');
    }
};
