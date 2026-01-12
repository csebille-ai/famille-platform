<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_assets', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('r2');
            $table->string('key');
            $table->string('public_url')->nullable();
            $table->string('mime');
            $table->unsignedBigInteger('size_bytes');
            $table->string('kind'); // photo|video
            $table->string('context'); // media|chat

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Optional linkage to existing models
            $table->unsignedBigInteger('cloud_node_id')->nullable();
            $table->unsignedBigInteger('video_id')->nullable();
            $table->unsignedBigInteger('chat_message_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider', 'key']);
            $table->index(['context', 'kind']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_assets');
    }
};
