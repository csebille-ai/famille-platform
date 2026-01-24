<?php

namespace Tests\Feature;

use App\Models\ChessGame;
use App\Models\User;
use App\Services\WebPush\WebPushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChessGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_games_and_chess_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('games.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('games.chess.index'))
            ->assertOk();

        $game = ChessGame::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('games.chess.show', $game))
            ->assertOk();
    }

    public function test_join_and_move_and_fen_conflict_flow(): void
    {
        $white = User::factory()->create(['name' => 'White']);
        $black = User::factory()->create(['name' => 'Black']);

        $pushCalls = [];
        $this->mock(WebPushNotifier::class, function ($mock) use (&$pushCalls) {
            $mock->shouldReceive('notifyUsers')
                ->twice()
                ->withArgs(function ($userIds, $payload, $options) use (&$pushCalls) {
                    $pushCalls[] = [$userIds, $payload, $options];
                    return true;
                })
                ->andReturnNull();
        });

        // Create the active game by hitting the index.
        $this->actingAs($white)
            ->get(route('games.chess.index'))
            ->assertOk();

        $game = ChessGame::query()->firstOrFail();

        $this->actingAs($white)
            ->postJson(route('games.chess.join', $game), ['team' => 'w'])
            ->assertOk()
            ->assertJsonPath('team', 'w');

        $this->actingAs($black)
            ->postJson(route('games.chess.join', $game), ['team' => 'b'])
            ->assertOk()
            ->assertJsonPath('team', 'b');

        // White can move on the starting position.
        $state = $this->actingAs($white)
            ->getJson(route('games.chess.state', $game))
            ->assertOk()
            ->json();

        $expectedFenStart = (string) data_get($state, 'game.fen');

        $move1 = $this->actingAs($white)
            ->postJson(route('games.chess.move', $game), [
                'uci' => 'e2e4',
                'expected_fen' => $expectedFenStart,
            ])
            ->assertOk()
            ->json();

        $fenAfterE2E4 = (string) data_get($move1, 'new_fen');

        // White cannot play twice in a row.
        $this->actingAs($white)
            ->postJson(route('games.chess.move', $game), [
                'uci' => 'g1f3',
                'expected_fen' => $fenAfterE2E4,
            ])
            ->assertStatus(403);

        // Black plays a reply using the up-to-date FEN.
        $move2 = $this->actingAs($black)
            ->postJson(route('games.chess.move', $game), [
                'uci' => 'e7e5',
                'expected_fen' => $fenAfterE2E4,
            ])
            ->assertOk()
            ->json();

        $fenAfterE7E5 = (string) data_get($move2, 'new_fen');

        // Push notifications should be sent only to the next team after each successful move.
        $this->assertCount(2, $pushCalls);

        // After White's move, it's Black's turn => notify Black team members.
        [$ids1, $payload1, $options1] = $pushCalls[0];
        $this->assertSame([(int) $black->id], array_values($ids1));
        $this->assertSame('Échecs', (string) ($payload1['title'] ?? ''));
        $this->assertSame('À votre tour de jouer', (string) ($payload1['body'] ?? ''));
        $this->assertIsString($payload1['url'] ?? null);
        $this->assertTrue(str_contains((string) $payload1['url'], '/games/chess/' . $game->id));
        $this->assertSame(3600, (int) ($options1['TTL'] ?? 0));

        // After Black's move, it's White's turn => notify White team members.
        [$ids2, $payload2, $options2] = $pushCalls[1];
        $this->assertSame([(int) $white->id], array_values($ids2));
        $this->assertSame('Échecs', (string) ($payload2['title'] ?? ''));
        $this->assertSame('À votre tour de jouer', (string) ($payload2['body'] ?? ''));
        $this->assertIsString($payload2['url'] ?? null);
        $this->assertTrue(str_contains((string) $payload2['url'], '/games/chess/' . $game->id));
        $this->assertSame(3600, (int) ($options2['TTL'] ?? 0));

        // White tries with a stale expected_fen (conflict), but it's now white's turn.
        $this->actingAs($white)
            ->postJson(route('games.chess.move', $game), [
                'uci' => 'g1f3',
                'expected_fen' => $fenAfterE2E4,
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'fen_mismatch')
            ->assertJsonPath('current_fen', $fenAfterE7E5);
    }
}
