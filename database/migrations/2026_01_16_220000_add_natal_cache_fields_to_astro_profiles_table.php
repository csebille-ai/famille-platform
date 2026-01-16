<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astro_profiles', function (Blueprint $table) {
            $table->string('natal_hash', 64)->nullable()->index();
            $table->timestamp('natal_computed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('astro_profiles', function (Blueprint $table) {
            $table->dropColumn(['natal_hash', 'natal_computed_at']);
        });
    }
};
