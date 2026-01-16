<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('avatar_path')->nullable();
            $table->boolean('is_child')->default(false);

            $table->timestamps();
        });

        // Backfill existing users into people so adults show up immediately.
        if (Schema::hasTable('users')) {
            $rows = DB::table('users')
                ->select(['id', 'name', 'date_of_birth'])
                ->orderBy('id')
                ->get();

            foreach ($rows as $u) {
                $exists = DB::table('people')->where('user_id', (int) $u->id)->exists();
                if ($exists) {
                    continue;
                }

                $name = trim((string) ($u->name ?? ''));
                $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $first = (string) ($parts[0] ?? '');
                $last = '';
                if (count($parts) > 1) {
                    $last = trim((string) implode(' ', array_slice($parts, 1)));
                }

                if ($first === '') {
                    $first = 'Utilisateur';
                }

                DB::table('people')->insert([
                    'user_id' => (int) $u->id,
                    'first_name' => $first,
                    'last_name' => $last !== '' ? $last : null,
                    'birth_date' => $u->date_of_birth ? (string) $u->date_of_birth : null,
                    'avatar_path' => null,
                    'is_child' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
