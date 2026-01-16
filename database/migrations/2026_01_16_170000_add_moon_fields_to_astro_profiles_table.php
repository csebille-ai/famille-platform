<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astro_profiles', function (Blueprint $table) {
            $table->string('moon_sign', 32)->nullable()->after('ascendant_sign');
            $table->decimal('moon_lon', 8, 4)->nullable()->after('moon_sign');
            $table->decimal('moon_deg_in_sign', 6, 3)->nullable()->after('moon_lon');

            // Hash of inputs used for moon computation: date|time|timezone.
            $table->string('astro_hash', 64)->nullable()->after('moon_deg_in_sign');

            // Timestamp of the moon computation (separate from generic computed_at).
            $table->timestamp('astro_computed_at')->nullable()->after('astro_hash');
        });
    }

    public function down(): void
    {
        Schema::table('astro_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'moon_sign',
                'moon_lon',
                'moon_deg_in_sign',
                'astro_hash',
                'astro_computed_at',
            ]);
        });
    }
};
