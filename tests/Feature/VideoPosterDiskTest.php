<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoPosterDiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_poster_serves_from_local_disk_when_configured(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $posterPath = 'videos/posters/1.jpg';
        Storage::disk('local')->put($posterPath, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00\x60\x00\x60\x00\x00\xFF\xD9");

        $video = Video::create([
            'title' => 'Test',
            'category' => 'docs',
            'video_path' => 'private/cloud/2026/01/test.mp4',
            'storage_disk' => 'local',
            'poster_path' => $posterPath,
            'created_by' => $user->id,
        ]);

        $res = $this->get(route('videos.poster', $video));
        $res->assertOk();

        $cacheControl = (string) $res->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=604800', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=86400', $cacheControl);
    }
}
