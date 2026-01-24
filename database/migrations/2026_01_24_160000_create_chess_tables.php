<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chess_games', function (Blueprint $table) {
            $table->id();
            $table->string('status', 16)->default('active'); // active|finished
            $table->text('current_fen');
            $table->char('turn', 1)->default('w'); // w|b
            $table->longText('pgn')->nullable();
            $table->timestamp('last_move_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('chess_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chess_game_id')->constrained('chess_games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('team', 12)->default('spectator'); // w|b|spectator
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['chess_game_id', 'user_id']);
            $table->index(['chess_game_id', 'team']);
        });

        Schema::create('chess_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chess_game_id')->constrained('chess_games')->cascadeOnDelete();
            $table->unsignedInteger('move_number');
            $table->string('uci', 16);
            $table->string('san', 32);
            $table->text('fen_before');
            $table->text('fen_after');
            $table->foreignId('played_by_user_id')->constrained('users');
            $table->char('played_by_team', 1); // w|b
            $table->timestamps();

            $table->index(['chess_game_id', 'move_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chess_moves');
        Schema::dropIfExists('chess_team_members');
        Schema::dropIfExists('chess_games');
    }
};
