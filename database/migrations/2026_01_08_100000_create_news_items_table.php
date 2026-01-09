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
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();

            $table->char('url_hash', 64)->unique();
            $table->text('url');

            $table->string('title', 500);
            $table->text('excerpt')->nullable();
            $table->text('image_url')->nullable();
            $table->string('source', 191)->nullable();
            $table->string('tag', 32)->nullable()->index();

            $table->timestamp('published_at')->index();
            $table->timestamp('fetched_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
