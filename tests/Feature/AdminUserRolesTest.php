<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_users_page_and_update_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'member']);

        $this->actingAs($admin);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($target->email);

        $this->patch(route('admin.users.role', $target), ['role' => 'editor'])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => 'editor',
        ]);
    }

    public function test_member_cannot_access_or_update_roles(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $target = User::factory()->create(['role' => 'member']);

        $this->actingAs($member);

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->patch(route('admin.users.role', $target), ['role' => 'admin'])->assertForbidden();
    }

    public function test_role_validation_rejects_invalid_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'member']);

        $this->actingAs($admin);

        $this->from(route('admin.users.index'))
            ->patch(route('admin.users.role', $target), ['role' => 'superadmin'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['role']);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => 'member',
        ]);
    }

    public function test_admin_cannot_change_own_role_away_from_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        $this->from(route('admin.users.index'))
            ->patch(route('admin.users.role', $admin), ['role' => 'member'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['role']);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
        ]);
    }
}
