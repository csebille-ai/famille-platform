<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astro_profiles', function (Blueprint $table) {
            $table->float('sun_lon')->nullable()->after('moon_deg_in_sign');
            $table->float('sun_deg_in_sign')->nullable()->after('sun_lon');

            $table->unsignedSmallInteger('kemetic_decan_index')->nullable()->after('sun_deg_in_sign');
            $table->string('kemetic_decan_label')->nullable()->after('kemetic_decan_index');
            $table->string('kemetic_decan_keyword')->nullable()->after('kemetic_decan_label');

            $table->string('kemetic_hash', 64)->nullable()->after('kemetic_decan_keyword');
            $table->timestamp('kemetic_computed_at')->nullable()->after('kemetic_hash');
        });
    }

    public function down(): void
    {
        Schema::table('astro_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'sun_lon',
                'sun_deg_in_sign',
                'kemetic_decan_index',
                'kemetic_decan_label',
                'kemetic_decan_keyword',
                'kemetic_hash',
                'kemetic_computed_at',
            ]);
        });
    }
};
