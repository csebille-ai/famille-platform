<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('cloud_nodes', 'storage_disk')) {
                $table->string('storage_disk')->default('local')->after('stored_path');
                $table->index('storage_disk');
            }
            if (!Schema::hasColumn('cloud_nodes', 'public_url')) {
                $table->string('public_url')->nullable()->after('storage_disk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cloud_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('cloud_nodes', 'public_url')) {
                $table->dropColumn('public_url');
            }
            if (Schema::hasColumn('cloud_nodes', 'storage_disk')) {
                $table->dropIndex(['storage_disk']);
                $table->dropColumn('storage_disk');
            }
        });
    }
};
