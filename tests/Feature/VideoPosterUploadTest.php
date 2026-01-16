<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoPosterUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_poster_without_ffmpeg(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $video = Video::create([
            'title' => 'Test',
            'category' => 'docs',
            'video_path' => 'private/cloud/2026/01/test.mp4',
            'storage_disk' => 'local',
            'created_by' => $user->id,
        ]);

        $poster = UploadedFile::fake()->create('poster.jpg', 10, 'image/jpeg');

        $res = $this->post(route('videos.poster.store', $video), [
            'poster' => $poster,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

        $res->assertOk();

        $video->refresh();
        $this->assertNotEmpty($video->poster_path);
        $this->assertStringStartsWith('videos/posters/' . $video->id . '.', (string) $video->poster_path);

        Storage::disk('local')->assertExists($video->poster_path);
    }
}
