<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('avatar_image_url')->nullable()->after('astro_signature_json');
            $table->json('avatar_spec_json')->nullable()->after('avatar_image_url');
            $table->string('avatar_archetype_title')->nullable()->after('avatar_spec_json');
            $table->json('avatar_traits_canon')->nullable()->after('avatar_archetype_title');
            $table->json('avatar_traits_surannes')->nullable()->after('avatar_traits_canon');
            $table->string('avatar_version')->nullable()->after('avatar_traits_surannes');
            $table->dateTime('avatar_updated_at')->nullable()->after('avatar_version');

            // Async generation status (UI feedback)
            $table->string('avatar_astro_status')->nullable()->after('avatar_updated_at');
            $table->text('avatar_astro_error')->nullable()->after('avatar_astro_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_image_url',
                'avatar_spec_json',
                'avatar_archetype_title',
                'avatar_traits_canon',
                'avatar_traits_surannes',
                'avatar_version',
                'avatar_updated_at',
                'avatar_astro_status',
                'avatar_astro_error',
            ]);
        });
    }
};
