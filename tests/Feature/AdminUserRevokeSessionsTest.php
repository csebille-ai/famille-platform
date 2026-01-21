<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserRevokeSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_revoke_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'member']);

        $this->actingAs($admin);

        $this->post(route('admin.users.revokeSessions', $target))
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_member_cannot_revoke_sessions(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $target = User::factory()->create(['role' => 'member']);

        $this->actingAs($member);

        $this->post(route('admin.users.revokeSessions', $target))
            ->assertForbidden();
    }
}
