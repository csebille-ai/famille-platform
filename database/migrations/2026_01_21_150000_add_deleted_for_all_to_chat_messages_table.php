<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->timestamp('deleted_for_all_at')->nullable()->after('body');
            $table->foreignId('deleted_for_all_by_user_id')
                ->nullable()
                ->after('deleted_for_all_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['deleted_for_all_at']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['deleted_for_all_at']);
            $table->dropConstrainedForeignId('deleted_for_all_by_user_id');
            $table->dropColumn('deleted_for_all_at');
        });
    }
};
