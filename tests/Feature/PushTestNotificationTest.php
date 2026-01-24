<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushTestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_test_requires_auth(): void
    {
        $this->postJson(route('push.test'))
            ->assertStatus(401);
    }

    public function test_push_test_returns_error_when_no_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push.test'))
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'subscription_count' => 0,
            ]);
    }

    public function test_push_test_returns_not_configured_when_missing_vapid(): void
    {
        $user = User::factory()->create();

        PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => 'https://example.test/push/abc',
            'public_key' => 'pk',
            'auth_token' => 'at',
            'content_encoding' => 'aesgcm',
            'user_agent' => 'phpunit',
        ]);

        $this->actingAs($user)
            ->postJson(route('push.test'))
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'configured' => false,
                'subscription_count' => 1,
            ]);
    }
}
