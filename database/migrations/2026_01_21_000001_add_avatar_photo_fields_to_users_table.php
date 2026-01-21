<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $avatarPath = 'avatar' . '_path';
        $avatarUpdatedAt = 'avatar' . '_updated' . '_at';

        Schema::table('users', function (Blueprint $table) use ($avatarPath, $avatarUpdatedAt) {
            if (!Schema::hasColumn('users', $avatarPath)) {
                $table->string($avatarPath)->nullable();
            }

            // Keep this timestamp for cache-busting (query param v=...)
            if (!Schema::hasColumn('users', $avatarUpdatedAt)) {
                $table->dateTime($avatarUpdatedAt)->nullable();
            }
        });

        // Drop legacy avatar-related columns if they exist.
        $toDrop = [];
        $legacy = [
            'avatar' . '_image' . '_url',
            'avatar' . '_spec' . '_json',
            'avatar' . '_archetype' . '_title',
            'avatar' . '_traits' . '_canon',
            'avatar' . '_traits' . '_surannes',
            'avatar' . '_version',
            'avatar' . '_' . 'astro' . '_' . 'status',
            'avatar' . '_' . 'astro' . '_' . 'error',
        ];

        foreach ($legacy as $col) {
            if (Schema::hasColumn('users', $col)) {
                $toDrop[] = $col;
            }
        }

        if (!empty($toDrop)) {
            Schema::table('users', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $avatarPath = 'avatar' . '_path';

        if (Schema::hasColumn('users', $avatarPath)) {
            Schema::table('users', function (Blueprint $table) use ($avatarPath) {
                $table->dropColumn([$avatarPath]);
            });
        }
    }
};
