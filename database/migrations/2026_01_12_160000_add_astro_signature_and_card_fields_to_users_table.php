<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('astro_signature_json')->nullable()->after('birth_longitude');

            $table->string('astro_card_style')->default('tarot_modern')->after('astro_signature_json');
            $table->string('astro_card_status')->nullable()->after('astro_card_style');
            $table->text('astro_card_image_url')->nullable()->after('astro_card_status');
            $table->text('astro_card_prompt')->nullable()->after('astro_card_image_url');
            $table->string('astro_card_seed')->nullable()->after('astro_card_prompt');
            $table->dateTime('astro_card_generated_at')->nullable()->after('astro_card_seed');
            $table->text('astro_card_error')->nullable()->after('astro_card_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'astro_signature_json',
                'astro_card_style',
                'astro_card_status',
                'astro_card_image_url',
                'astro_card_prompt',
                'astro_card_seed',
                'astro_card_generated_at',
                'astro_card_error',
            ]);
        });
    }
};
