<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('google_event_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            $table->string('google_calendar_id', 255);
            $table->string('google_event_id', 255);
            $table->timestamp('last_pushed_at')->nullable()->index();

            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
            $table->index(['google_calendar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_event_links');
    }
};
