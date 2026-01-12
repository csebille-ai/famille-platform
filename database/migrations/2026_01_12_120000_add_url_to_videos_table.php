<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('videos')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            if (!Schema::hasColumn('videos', 'url')) {
                // Public URL (used for remote storage such as R2).
                $table->string('url')->nullable()->after('storage_disk');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('videos')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            if (Schema::hasColumn('videos', 'url')) {
                $table->dropColumn('url');
            }
        });
    }
};
