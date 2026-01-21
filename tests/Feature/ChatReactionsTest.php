<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatReactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reactions_routes_are_protected_by_auth(): void
    {
        $user = User::factory()->create();

        $msg = ChatMessage::query()->create([
            'user_id' => $user->id,
            'body' => 'Hi',
        ]);

        $this->postJson(route('chat.messages.reactions.toggle', ['message' => $msg->id]), ['emoji' => '👍'])
            ->assertUnauthorized();

        $this->getJson(route('chat.messages.reactions.index', ['message' => $msg->id]))
            ->assertUnauthorized();
    }

    public function test_user_can_toggle_reaction_on_a_message(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();

        $msg = ChatMessage::query()->create([
            'user_id' => $author->id,
            'body' => 'Hello',
        ]);

        $this->actingAs($reactor);

        $resp1 = $this->postJson(route('chat.messages.reactions.toggle', ['message' => $msg->id]), [
            'emoji' => '👍',
        ])->assertOk();

        $resp1->assertJsonPath('message_id', $msg->id);
        $resp1->assertJsonPath('reaction_summary.0.emoji', '👍');
        $resp1->assertJsonPath('reaction_summary.0.count', 1);
        $resp1->assertJsonPath('reaction_summary.0.reacted_by_me', true);

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $msg->id,
            'user_id' => $reactor->id,
            'emoji' => '👍',
        ]);

        $resp2 = $this->postJson(route('chat.messages.reactions.toggle', ['message' => $msg->id]), [
            'emoji' => '👍',
        ])->assertOk();

        $resp2->assertJsonPath('message_id', $msg->id);
        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $msg->id,
            'user_id' => $reactor->id,
            'emoji' => '👍',
        ]);
    }

    public function test_two_users_react_counts_and_reacted_by_me_are_correct(): void
    {
        $author = User::factory()->create();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $msg = ChatMessage::query()->create([
            'user_id' => $author->id,
            'body' => 'Hello',
        ]);

        $this->actingAs($u1)
            ->postJson(route('chat.messages.reactions.toggle', ['message' => $msg->id]), ['emoji' => '❤️'])
            ->assertOk();

        $this->actingAs($u2)
            ->postJson(route('chat.messages.reactions.toggle', ['message' => $msg->id]), ['emoji' => '❤️'])
            ->assertOk()
            ->assertJsonFragment([
                'emoji' => '❤️',
                'count' => 2,
                'reacted_by_me' => true,
            ]);

        $this->actingAs($u1)
            ->postJson(route('chat.messages.reactions.toggle', ['message' => $msg->id]), ['emoji' => '❤️'])
            ->assertOk()
            ->assertJsonFragment([
                'emoji' => '❤️',
                'count' => 1,
                'reacted_by_me' => false,
            ]);

        $this->actingAs($u2)
            ->getJson(route('chat.messages.reactions.index', ['message' => $msg->id]))
            ->assertOk()
            ->assertJsonStructure([
                'emoji_groups' => [
                    '❤️' => [
                        ['id', 'name', 'avatar_url'],
                    ],
                ],
            ]);
    }
}
