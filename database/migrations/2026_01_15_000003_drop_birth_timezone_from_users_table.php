<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'birth_timezone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('birth_timezone');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'birth_timezone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('birth_timezone', 64)->nullable()->after('birth_place');
        });
    }
};
