<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cloud_nodes')) {
            return;
        }

        Schema::table('cloud_nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('cloud_nodes', 'focal_x')) {
                $table->decimal('focal_x', 5, 4)->nullable()->after('size');
            }
            if (!Schema::hasColumn('cloud_nodes', 'focal_y')) {
                $table->decimal('focal_y', 5, 4)->nullable()->after('focal_x');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cloud_nodes')) {
            return;
        }

        Schema::table('cloud_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('cloud_nodes', 'focal_y')) {
                $table->dropColumn('focal_y');
            }
            if (Schema::hasColumn('cloud_nodes', 'focal_x')) {
                $table->dropColumn('focal_x');
            }
        });
    }
};
