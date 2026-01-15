<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_routes_are_protected_by_auth(): void
    {
        $this->get(route('chat.index'))
            ->assertRedirect(route('login'));

        $this->post(route('chat.store'), ['body' => 'Hello'])
            ->assertRedirect(route('login'));
    }

    public function test_user_can_post_a_chat_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post(route('chat.store'), [
            'body' => 'Hello chat',
        ]);

        $response->assertRedirect(route('chat.index'));

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $user->id,
            'body' => 'Hello chat',
        ]);

        $message = ChatMessage::query()->firstOrFail();

        $resp = $this->get(route('chat.index'))
            ->assertOk()
            ->assertSee($message->body);

        $content = (string) $resp->getContent();
        $this->assertTrue(
            str_contains($content, $user->name) || str_contains($content, e($user->name)),
            'Expected chat page to contain the user name (raw or HTML-escaped).'
        );
    }

    public function test_validation_requires_body(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->from(route('chat.index'))
            ->post(route('chat.store'), [
                'body' => '',
            ])
            ->assertRedirect(route('chat.index'))
            ->assertSessionHasErrors(['body']);
    }
}
