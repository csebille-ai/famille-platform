<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            }

            if (!Schema::hasColumn('events', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('events', 'location')) {
                $table->string('location', 80)->nullable();
            }

            if (!Schema::hasColumn('events', 'start_at')) {
                $table->dateTime('start_at')->nullable()->index();
            }
            if (!Schema::hasColumn('events', 'end_at')) {
                $table->dateTime('end_at')->nullable();
            }

            if (!Schema::hasColumn('events', 'all_day')) {
                $table->boolean('all_day')->default(false)->index();
            }
            if (!Schema::hasColumn('events', 'timezone')) {
                $table->string('timezone', 64)->nullable();
            }

            if (!Schema::hasColumn('events', 'visibility')) {
                $table->string('visibility', 16)->default('family')->index();
            }
            if (!Schema::hasColumn('events', 'is_important')) {
                $table->boolean('is_important')->default(false)->index();
            }
            if (!Schema::hasColumn('events', 'category')) {
                $table->string('category', 16)->nullable()->index();
            }
            if (!Schema::hasColumn('events', 'color_tag')) {
                $table->string('color_tag', 16)->nullable();
            }

            if (!Schema::hasColumn('events', 'notify')) {
                $table->boolean('notify')->default(true)->index();
            }
            if (!Schema::hasColumn('events', 'reminder_minutes')) {
                $table->integer('reminder_minutes')->nullable();
            }
            if (!Schema::hasColumn('events', 'reminder_at')) {
                $table->dateTime('reminder_at')->nullable()->index();
            }
            if (!Schema::hasColumn('events', 'reminded_at')) {
                $table->dateTime('reminded_at')->nullable()->index();
            }

            if (!Schema::hasColumn('events', 'status')) {
                $table->string('status', 16)->default('active')->index();
            }
        });

        // Backfill from legacy columns if present.
        if (Schema::hasColumn('events', 'starts_on') && Schema::hasColumn('events', 'start_at')) {
            $rows = DB::table('events')
                ->whereNull('start_at')
                ->whereNotNull('starts_on')
                ->select(['id', 'starts_on'])
                ->limit(2000)
                ->get();

            foreach ($rows as $r) {
                try {
                    $startAt = (string) $r->starts_on . ' 00:00:00';
                    DB::table('events')->where('id', $r->id)->update([
                        'start_at' => $startAt,
                        'all_day' => true,
                        'visibility' => 'family',
                        'status' => 'active',
                    ]);
                } catch (Throwable) {
                    // ignore
                }
            }
        }

        // Add FK if possible.
        try {
            Schema::table('events', function (Blueprint $table) {
                if (Schema::hasColumn('events', 'created_by_user_id')) {
                    // Avoid duplicate constraint creation.
                    $table->foreign('created_by_user_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            });
        } catch (Throwable) {
            // If DB doesn't support it or it already exists, ignore.
        }

        // Drop legacy columns if present.
        try {
            Schema::table('events', function (Blueprint $table) {
                if (Schema::hasColumn('events', 'starts_on')) {
                    $table->dropColumn('starts_on');
                }
                if (Schema::hasColumn('events', 'type')) {
                    $table->dropColumn('type');
                }
            });
        } catch (Throwable) {
            // ignore (some DBs require doctrine/dbal for drop/alter)
        }
    }

    public function down(): void
    {
        // No down migration: this is an irreversible expansion/normalization.
    }
};
