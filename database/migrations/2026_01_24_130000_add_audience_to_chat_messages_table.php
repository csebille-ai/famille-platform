<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'audience_type')) {
                $table->string('audience_type', 16)->default('all')->index();
            }
            if (!Schema::hasColumn('chat_messages', 'audience_user_ids')) {
                $table->json('audience_user_ids')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'audience_user_ids')) {
                $table->dropColumn('audience_user_ids');
            }
            if (Schema::hasColumn('chat_messages', 'audience_type')) {
                $table->dropIndex(['audience_type']);
                $table->dropColumn('audience_type');
            }
        });
    }
};
