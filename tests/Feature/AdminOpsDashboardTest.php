<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOpsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_ops_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        $this->get(route('admin.overview'))->assertOk();
        $this->get(route('admin.activity'))->assertOk();
        $this->get(route('admin.errors'))->assertOk();
    }

    public function test_member_cannot_access_ops_pages(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member);

        $this->get(route('admin.overview'))->assertForbidden();
        $this->get(route('admin.activity'))->assertForbidden();
        $this->get(route('admin.errors'))->assertForbidden();
    }
}
