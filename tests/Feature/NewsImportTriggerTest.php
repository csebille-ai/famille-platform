<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NewsImportTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_trigger_news_import_as_html_and_get_redirect(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Artisan::shouldReceive('call')->once()->with('news:import-rss')->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn("Done. Total: 3");

        $this->actingAs($admin);

        $this->from(route('admin.overview'))
            ->post(route('news.import'))
            ->assertRedirect(route('admin.overview'))
            ->assertSessionHas('status');
    }

    public function test_admin_can_trigger_news_import_as_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Artisan::shouldReceive('call')->once()->with('news:import-rss')->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('OK');

        $this->actingAs($admin);

        $this->postJson(route('news.import'))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_member_cannot_trigger_news_import(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member);

        $this->post(route('news.import'))->assertForbidden();
        $this->postJson(route('news.import'))->assertForbidden();
    }
}
