<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('birth_time')->nullable()->after('date_of_birth');
            $table->string('birth_place', 255)->nullable()->after('birth_time');
            $table->string('birth_timezone', 64)->nullable()->after('birth_place');
            $table->decimal('birth_latitude', 10, 7)->nullable()->after('birth_timezone');
            $table->decimal('birth_longitude', 10, 7)->nullable()->after('birth_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'birth_time',
                'birth_place',
                'birth_timezone',
                'birth_latitude',
                'birth_longitude',
            ]);
        });
    }
};
