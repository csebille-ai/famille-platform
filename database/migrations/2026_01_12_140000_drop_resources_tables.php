<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If this project ever had the Resources feature enabled, these tables may still exist in prod.
        // Drop in FK-safe order.
        if (Schema::hasTable('resource_files')) {
            Schema::drop('resource_files');
        }

        if (Schema::hasTable('resources')) {
            Schema::drop('resources');
        }

        if (Schema::hasTable('ressources')) {
            Schema::drop('ressources');
        }
    }

    public function down(): void
    {
        // Intentionally empty: feature removed.
    }
};
