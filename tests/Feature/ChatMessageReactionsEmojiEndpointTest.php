<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessageReactionsEmojiEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_users_for_specific_emoji(): void
    {
        $me = User::factory()->create(['name' => 'Zoé']);
        $u1 = User::factory()->create(['name' => 'Alice']);
        $u2 = User::factory()->create(['name' => 'Bob']);

        $message = ChatMessage::query()->create([
            'user_id' => $me->id,
            'body' => 'Hello',
        ]);

        ChatMessageReaction::query()->create([
            'chat_message_id' => $message->id,
            'user_id' => $u2->id,
            'emoji' => '😂',
        ]);
        ChatMessageReaction::query()->create([
            'chat_message_id' => $message->id,
            'user_id' => $u1->id,
            'emoji' => '😂',
        ]);
        ChatMessageReaction::query()->create([
            'chat_message_id' => $message->id,
            'user_id' => $u1->id,
            'emoji' => '👍',
        ]);

        $resp = $this->actingAs($me)->getJson('/chat/messages/'.$message->id.'/reactions/'.rawurlencode('😂'));

        $resp->assertOk();
        $resp->assertJson([
            'emoji' => '😂',
            'count' => 2,
        ]);

        $json = $resp->json();
        $this->assertIsArray($json);
        $this->assertIsArray($json['users'] ?? null);
        $this->assertCount(2, $json['users']);

        // Stable ordering: users.name ASC
        $this->assertSame('Alice', $json['users'][0]['name'] ?? null);
        $this->assertSame('Bob', $json['users'][1]['name'] ?? null);

        // No sensitive fields
        foreach ($json['users'] as $u) {
            $this->assertArrayNotHasKey('email', $u);
        }
    }

    public function test_it_returns_404_for_deleted_message(): void
    {
        $me = User::factory()->create();
        $message = ChatMessage::query()->create([
            'user_id' => $me->id,
            'body' => 'Hello',
            'deleted_for_all_at' => now(),
            'deleted_for_all_by_user_id' => $me->id,
        ]);

        $resp = $this->actingAs($me)->getJson('/chat/messages/'.$message->id.'/reactions/'.rawurlencode('😂'));
        $resp->assertStatus(404);
    }

    public function test_it_rejects_non_whitelisted_emoji(): void
    {
        $me = User::factory()->create();
        $message = ChatMessage::query()->create([
            'user_id' => $me->id,
            'body' => 'Hello',
        ]);

        $resp = $this->actingAs($me)->getJson('/chat/messages/'.$message->id.'/reactions/'.rawurlencode('👀'));
        $resp->assertStatus(422);
    }
}
