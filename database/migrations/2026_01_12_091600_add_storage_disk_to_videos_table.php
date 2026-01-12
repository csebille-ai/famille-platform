<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (!Schema::hasColumn('videos', 'storage_disk')) {
                // Existing uploads are stored on the "public" local disk.
                $table->string('storage_disk')->default('public')->after('video_path');
                $table->index('storage_disk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (Schema::hasColumn('videos', 'storage_disk')) {
                $table->dropIndex(['storage_disk']);
                $table->dropColumn('storage_disk');
            }
        });
    }
};
