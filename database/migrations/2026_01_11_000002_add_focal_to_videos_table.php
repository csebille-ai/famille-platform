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
            if (!Schema::hasColumn('videos', 'focal_x')) {
                $table->decimal('focal_x', 5, 4)->nullable()->after('duration_seconds');
            }
            if (!Schema::hasColumn('videos', 'focal_y')) {
                $table->decimal('focal_y', 5, 4)->nullable()->after('focal_x');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('videos')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            if (Schema::hasColumn('videos', 'focal_y')) {
                $table->dropColumn('focal_y');
            }
            if (Schema::hasColumn('videos', 'focal_x')) {
                $table->dropColumn('focal_x');
            }
        });
    }
};
