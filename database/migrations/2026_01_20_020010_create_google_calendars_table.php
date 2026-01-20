<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('google_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('google_calendar_id', 255)->nullable();
            $table->string('summary')->default(env('GOOGLE_FAMILY_CALENDAR_SUMMARY', 'Famille — Calendrier'));
            $table->string('timezone')->default(env('GOOGLE_DEFAULT_TZ', env('APP_TIMEZONE', 'UTC')));
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendars');
    }
};
