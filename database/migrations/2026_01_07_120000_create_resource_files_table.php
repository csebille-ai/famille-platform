<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->string('path');
            $table->string('name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Backfill legacy single-file resources into the new table.
        $legacy = DB::table('resources')
            ->whereNotNull('attachment_path')
            ->select(['id', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'created_by', 'created_at', 'updated_at'])
            ->get();

        if ($legacy->isNotEmpty()) {
            DB::table('resource_files')->insert(
                $legacy->map(fn ($r) => [
                    'resource_id' => $r->id,
                    'path' => $r->attachment_path,
                    'name' => $r->attachment_name ?: 'resource',
                    'mime' => $r->attachment_mime,
                    'size' => $r->attachment_size,
                    'created_by' => $r->created_by,
                    'created_at' => $r->created_at,
                    'updated_at' => $r->updated_at,
                ])->all()
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_files');
    }
};
