<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sliding_puzzles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();

            // media | avatar | external
            $table->string('image_source_type', 20);
            // For external: URL string; for avatar/media: id stored as string.
            $table->string('image_source_id')->nullable();

            $table->unsignedTinyInteger('grid_size'); // 3 or 4
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'grid_size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sliding_puzzles');
    }
};
