<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained('playlists')->cascadeOnDelete();

            $table->string('spotify_track_id');
            $table->string('label')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['playlist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_items');
    }
};
