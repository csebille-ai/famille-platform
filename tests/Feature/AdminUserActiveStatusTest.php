<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserActiveStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_user_active_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'member', 'is_active' => true]);

        $this->actingAs($admin);

        $this->patch(route('admin.users.active', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_active' => false,
        ]);

        $this->patch(route('admin.users.active', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin);

        $this->from(route('admin.users.index'))
            ->patch(route('admin.users.active', $admin))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['user']);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
        ]);
    }

    public function test_member_cannot_toggle_user_active_status(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $target = User::factory()->create(['role' => 'member', 'is_active' => true]);

        $this->actingAs($member);

        $this->patch(route('admin.users.active', $target))->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_active' => true,
        ]);
    }
}
