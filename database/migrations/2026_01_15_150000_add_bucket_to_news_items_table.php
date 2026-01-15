<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->string('bucket', 16)->nullable()->index()->after('tag');
            $table->string('sub_category', 32)->nullable()->after('bucket');
        });

        // Quick backfill so the UI filter doesn't hide all existing items.
        try {
            DB::table('news_items')->whereNull('bucket')->update(['bucket' => 'infos']);

            DB::table('news_items')
                ->where('tag', 'sport')
                ->update(['bucket' => 'sport', 'sub_category' => null]);

            DB::table('news_items')
                ->where('tag', 'habitat')
                ->update(['bucket' => 'infos', 'sub_category' => 'habitat']);

            DB::table('news_items')
                ->where('tag', 'charente-maritime')
                ->update(['bucket' => 'infos', 'sub_category' => 'institutions']);

            DB::table('news_items')
                ->where('tag', 'ile-de-re')
                ->update(['bucket' => 'infos']);
        } catch (\Throwable $e) {
            // Best-effort; ignore if running in a context where DB is unavailable.
        }
    }

    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->dropColumn(['bucket', 'sub_category']);
        });
    }
};
