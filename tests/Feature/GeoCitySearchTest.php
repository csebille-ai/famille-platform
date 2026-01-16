<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoCitySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_geo_city_search_requires_auth(): void
    {
        $this->get('/api/geo/cities?q=Lille')
            ->assertRedirect();
    }

    public function test_geo_city_search_returns_normalized_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        Http::fake([
            'api-adresse.data.gouv.fr/*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'label' => '17000 La Rochelle',
                            'city' => 'La Rochelle',
                            'postcode' => '17000',
                        ],
                        'geometry' => [
                            'coordinates' => [-1.151139, 46.160329],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $resp = $this->getJson('/api/geo/cities?q=La%20Rochelle');

        $resp->assertOk();
        $resp->assertJsonStructure([
            'items' => [
                ['label', 'city', 'postcode', 'latitude', 'longitude'],
            ],
        ]);

        $items = $resp->json('items');
        $this->assertIsArray($items);
        $this->assertSame('17000 La Rochelle', $items[0]['label']);
        $this->assertSame('17000', $items[0]['postcode']);
    }

    public function test_geo_city_search_returns_empty_for_too_short_query(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->getJson('/api/geo/cities?q=a')
            ->assertOk()
            ->assertJson(['items' => []]);
    }
}
