<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('event_external_links');
        Schema::dropIfExists('google_calendar_accounts');
    }

    public function down(): void
    {
        Schema::create('google_calendar_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('google_sub')->nullable();

            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('scope')->nullable();
            $table->string('token_type')->nullable();

            $table->string('calendar_id')->default('primary');

            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('event_external_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 32);
            $table->string('external_event_id', 255);
            $table->string('external_calendar_id', 255)->nullable();

            $table->timestamps();

            $table->unique(['event_id', 'user_id', 'provider']);
            $table->index(['user_id', 'provider']);
        });
    }
};
