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

    public function test_conversation_filter_shows_only_messages_shared_with_selected_user(): void
    {
        $me = User::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        // Me -> Alice (subset)
        $this->actingAs($me);
        $this->postJson(route('chat.store'), [
            'body' => 'To Alice',
            'audience_user_ids' => [$alice->id],
        ])->assertOk();

        // Me -> Bob (subset)
        $this->postJson(route('chat.store'), [
            'body' => 'To Bob',
            'audience_user_ids' => [$bob->id],
        ])->assertOk();

        // Public message (all)
        $this->postJson(route('chat.store'), [
            'body' => 'Public',
            'audience_user_ids' => [],
        ])->assertOk();

        // Another user's public message should NOT appear in the conversation view.
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->postJson(route('chat.store'), [
            'body' => 'Other public',
            'audience_user_ids' => [],
        ])->assertOk();

        // Alice -> Me (subset)
        $this->actingAs($alice);
        $this->postJson(route('chat.store'), [
            'body' => 'From Alice',
            'audience_user_ids' => [$me->id],
        ])->assertOk();

        // Bob -> Me (subset)
        $this->actingAs($bob);
        $this->postJson(route('chat.store'), [
            'body' => 'From Bob',
            'audience_user_ids' => [$me->id],
        ])->assertOk();

        // Conversation view (Me <-> Alice): should show only targeted messages shared with Alice.
        $this->actingAs($me);

        $this->get(route('chat.index', ['with_user_id' => $alice->id]))
            ->assertOk()
            ->assertSee('To Alice')
            ->assertSee('From Alice')
            ->assertDontSee('To Bob')
            ->assertDontSee('From Bob')
            ->assertSee('Public')
            ->assertDontSee('Other public');

        $poll = $this->getJson(route('chat.poll', ['since_id' => 0, 'with_user_id' => $alice->id]));
        $poll->assertOk();
        $bodies = collect($poll->json('messages'))
            ->pluck('body')
            ->map(fn ($v) => (string) $v)
            ->all();

        $this->assertContains('To Alice', $bodies);
        $this->assertContains('From Alice', $bodies);
        $this->assertNotContains('To Bob', $bodies);
        $this->assertNotContains('From Bob', $bodies);
        $this->assertContains('Public', $bodies);
        $this->assertNotContains('Other public', $bodies);
    }
}
