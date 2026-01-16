<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardianships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('child_person_id')->constrained('people')->cascadeOnDelete();

            $table->boolean('can_edit')->default(true);
            $table->boolean('notify')->default(true);

            $table->timestamps();

            $table->unique(['guardian_user_id', 'child_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardianships');
    }
};
