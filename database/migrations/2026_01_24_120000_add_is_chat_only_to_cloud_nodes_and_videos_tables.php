<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_nodes', function (Blueprint $table) {
            $table->boolean('is_chat_only')->default(false)->after('uploaded_by')->index();
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('is_chat_only')->default(false)->after('created_by')->index();
        });
    }

    public function down(): void
    {
        Schema::table('cloud_nodes', function (Blueprint $table) {
            $table->dropColumn('is_chat_only');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('is_chat_only');
        });
    }
};
