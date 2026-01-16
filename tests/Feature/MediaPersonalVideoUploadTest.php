<?php

namespace Tests\Feature;

use App\Models\CloudNode;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaPersonalVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_video_from_media_creates_personal_video_and_redirects_back_to_media(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        // Ensure root folder exists.
        $this->get(route('cloud.index'))->assertOk();

        $root = CloudNode::query()
            ->whereNull('parent_id')
            ->where('type', 'folder')
            ->where('name', '/')
            ->firstOrFail();

        $upload = UploadedFile::fake()->create('perso.mp4', 100, 'video/mp4');

        $res = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post(route('cloud.files.store'), [
            'parent_id' => $root->id,
            'return' => '/media?tab=videos',
            'file' => $upload,
        ]);

        $res->assertStatus(201);
        $res->assertJson([
            'redirect_url' => route('media.index', ['tab' => 'videos']),
        ]);

        $node = CloudNode::query()->where('type', 'file')->firstOrFail();

        $video = Video::query()->where('cloud_node_id', $node->id)->firstOrFail();
        $this->assertSame('docs', $video->category);
        $this->assertSame($node->stored_path, $video->video_path);
    }
}
