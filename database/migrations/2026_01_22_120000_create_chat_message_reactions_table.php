<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            $table->unique(['chat_message_id', 'user_id', 'emoji']);
            $table->index(['chat_message_id']);
            $table->index(['chat_message_id', 'emoji']);
        });

        // Migrate existing data if the legacy table exists.
        if (Schema::hasTable('message_reactions')) {
            $rows = DB::table('message_reactions')
                ->select(['message_id', 'user_id', 'emoji', 'created_at', 'updated_at'])
                ->get();

            if ($rows->count() > 0) {
                $payload = [];
                foreach ($rows as $r) {
                    $payload[] = [
                        'chat_message_id' => (int) ($r->message_id ?? 0),
                        'user_id' => (int) ($r->user_id ?? 0),
                        'emoji' => (string) ($r->emoji ?? ''),
                        'created_at' => $r->created_at,
                        'updated_at' => $r->updated_at,
                    ];
                }

                DB::table('chat_message_reactions')->insertOrIgnore($payload);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_reactions');
    }
};
