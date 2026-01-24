<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTargetedMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_subset_message_is_visible_only_to_sender_and_recipients(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($sender);

        $resp = $this->postJson(route('chat.store'), [
            'body' => 'Hello subset',
            'audience_user_ids' => [$recipient->id],
        ]);

        $resp->assertOk();

        $message = ChatMessage::query()->latest('id')->firstOrFail();
        $this->assertSame('subset', $message->audience_type);
        $this->assertSame([(int) $recipient->id], array_values($message->audience_user_ids ?? []));

        // Sender sees it.
        $this->actingAs($sender);
        $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Hello subset');

        // Recipient sees it.
        $this->actingAs($recipient);
        $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Hello subset');

        // Other does not.
        $this->actingAs($other);
        $this->get(route('chat.index'))
            ->assertOk()
            ->assertDontSee('Hello subset');

        $poll = $this->getJson(route('chat.poll', ['since_id' => 0]));
        $poll->assertOk();
        $ids = collect($poll->json('messages'))->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->assertFalse(in_array((int) $message->id, $ids, true));
    }

    public function test_admin_can_see_subset_message(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($sender);
        $this->postJson(route('chat.store'), [
            'body' => 'Admin can see',
            'audience_user_ids' => [$recipient->id],
        ])->assertOk();

        $this->actingAs($admin);
        $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee('Admin can see');
    }
}
