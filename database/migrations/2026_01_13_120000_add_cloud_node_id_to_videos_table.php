<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (!Schema::hasColumn('videos', 'cloud_node_id')) {
                $table->foreignId('cloud_node_id')->nullable()->after('id')->constrained('cloud_nodes')->nullOnDelete();
                $table->index('cloud_node_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (Schema::hasColumn('videos', 'cloud_node_id')) {
                $table->dropForeign(['cloud_node_id']);
                $table->dropIndex(['cloud_node_id']);
                $table->dropColumn('cloud_node_id');
            }
        });
    }
};
