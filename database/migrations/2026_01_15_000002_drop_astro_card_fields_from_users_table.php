<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'astro_card_style',
            'astro_card_status',
            'astro_card_image_url',
            'astro_card_icon_url',
            'astro_card_prompt',
            'astro_card_seed',
            'astro_card_generated_at',
            'astro_card_error',
            'avatar_use_astro_icon',
        ];

        $drop = [];
        foreach ($columns as $col) {
            if (Schema::hasColumn('users', $col)) {
                $drop[] = $col;
            }
        }

        if ($drop !== []) {
            Schema::table('users', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }
    }

    public function down(): void
    {
        // Best-effort rollback: re-add the legacy columns if they were dropped.
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'astro_card_style')) {
                $table->string('astro_card_style')->default('tarot_modern')->nullable();
            }
            if (!Schema::hasColumn('users', 'astro_card_status')) {
                $table->string('astro_card_status')->nullable();
            }
            if (!Schema::hasColumn('users', 'astro_card_image_url')) {
                $table->text('astro_card_image_url')->nullable();
            }
            if (!Schema::hasColumn('users', 'astro_card_icon_url')) {
                $table->text('astro_card_icon_url')->nullable();
            }
            if (!Schema::hasColumn('users', 'astro_card_prompt')) {
                $table->text('astro_card_prompt')->nullable();
            }
            if (!Schema::hasColumn('users', 'astro_card_seed')) {
                $table->string('astro_card_seed')->nullable();
            }
            if (!Schema::hasColumn('users', 'astro_card_generated_at')) {
                $table->dateTime('astro_card_generated_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'astro_card_error')) {
                $table->text('astro_card_error')->nullable();
            }
            if (!Schema::hasColumn('users', 'avatar_use_astro_icon')) {
                $table->boolean('avatar_use_astro_icon')->default(false);
            }
        });
    }
};
