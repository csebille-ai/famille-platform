<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sliding_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sliding_puzzle_id')->constrained('sliding_puzzles')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->unsignedTinyInteger('grid_size');
            $table->unsignedInteger('moves_count')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['sliding_puzzle_id', 'grid_size', 'finished_at']);
            $table->index(['sliding_puzzle_id', 'grid_size', 'duration_ms']);
            $table->index(['sliding_puzzle_id', 'grid_size', 'moves_count']);
            $table->index(['user_id', 'sliding_puzzle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sliding_attempts');
    }
};
