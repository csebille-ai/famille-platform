<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // We want audit logs to keep the original node_id even after a node is purged.
        // The initial migration added a FK with nullOnDelete(), which sets node_id = NULL
        // when the referenced cloud_nodes row is deleted.

        if (!Schema::hasTable('cloud_audit_logs')) {
            return;
        }

        Schema::create('cloud_audit_logs_tmp', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->unsignedBigInteger('node_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['node_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });

        DB::table('cloud_audit_logs_tmp')->insertUsing(
            ['id', 'action', 'node_id', 'actor_id', 'meta', 'created_at', 'updated_at'],
            DB::table('cloud_audit_logs')->select(
                'id',
                'action',
                'node_id',
                'actor_id',
                'meta',
                'created_at',
                'updated_at'
            )
        );

        Schema::drop('cloud_audit_logs');
        Schema::rename('cloud_audit_logs_tmp', 'cloud_audit_logs');
    }

    public function down(): void
    {
        // Best-effort rollback to the original FK behavior.
        if (!Schema::hasTable('cloud_audit_logs')) {
            return;
        }

        Schema::create('cloud_audit_logs_tmp', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->foreignId('node_id')->nullable()->constrained('cloud_nodes')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['node_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });

        DB::table('cloud_audit_logs_tmp')->insertUsing(
            ['id', 'action', 'node_id', 'actor_id', 'meta', 'created_at', 'updated_at'],
            DB::table('cloud_audit_logs')->select(
                'id',
                'action',
                'node_id',
                'actor_id',
                'meta',
                'created_at',
                'updated_at'
            )
        );

        Schema::drop('cloud_audit_logs');
        Schema::rename('cloud_audit_logs_tmp', 'cloud_audit_logs');
    }
};
