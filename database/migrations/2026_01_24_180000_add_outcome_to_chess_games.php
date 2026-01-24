<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chess_games', function (Blueprint $table) {
            $table->char('winner_team', 1)->nullable()->after('turn'); // w|b
            $table->string('ended_reason', 24)->nullable()->after('winner_team'); // resign|...
            $table->timestamp('ended_at')->nullable()->after('ended_reason');
        });
    }

    public function down(): void
    {
        Schema::table('chess_games', function (Blueprint $table) {
            $table->dropColumn(['winner_team', 'ended_reason', 'ended_at']);
        });
    }
};
