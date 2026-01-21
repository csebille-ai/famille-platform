<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_message_for_me(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();

        $msg = ChatMessage::query()->create([
            'user_id' => $author->id,
            'body' => 'Hello delete-me',
        ]);

        $this->actingAs($viewer);

        $this->deleteJson(route('chat.messages.destroy.me', ['message' => $msg->id]))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message_id' => $msg->id,
                'scope' => 'me',
            ]);

        $this->get(route('chat.index'))
            ->assertOk()
            ->assertDontSeeText('Hello delete-me');
    }

    public function test_author_can_delete_message_for_everyone(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();

        $msg = ChatMessage::query()->create([
            'user_id' => $author->id,
            'body' => 'Hello delete-all',
        ]);

        $this->actingAs($author);

        $this->deleteJson(route('chat.messages.destroy', ['message' => $msg->id]))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message_id' => $msg->id,
                'scope' => 'all',
                'deleted_for_all' => true,
            ]);

        $this->actingAs($other);

        $poll = $this->getJson(route('chat.poll', ['since_id' => 0]))
            ->assertOk()
            ->json();

        $messages = $poll['messages'] ?? [];
        $found = null;
        foreach ($messages as $m) {
            if ((int) ($m['id'] ?? 0) === (int) $msg->id) {
                $found = $m;
                break;
            }
        }

        $this->assertIsArray($found);
        $this->assertTrue((bool) ($found['is_deleted'] ?? false));
        $this->assertSame('', (string) ($found['body'] ?? ''));

        $this->get(route('chat.index'))
            ->assertOk()
            ->assertSeeText('Message supprimé')
            ->assertDontSeeText('Hello delete-all');
    }
}
