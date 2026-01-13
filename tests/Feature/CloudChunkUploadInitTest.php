<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloudChunkUploadInitTest extends TestCase
{
    use RefreshDatabase;

    public function test_chunk_upload_init_allows_large_files_within_max_file_limit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        // Allow up to 1GB files.
        config(['cloud.max_file_kb' => 1048576]);

        $this->postJson(route('cloud.uploads.init'), [
            'name' => 'big.mp4',
            'size' => 400 * 1024 * 1024, // 400MB in bytes
            'mime' => 'video/mp4',
        ])->assertCreated();
    }

    public function test_chunk_upload_init_rejects_files_above_max_file_limit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        // Cap at 100MB.
        config(['cloud.max_file_kb' => 102400]);

        $this->postJson(route('cloud.uploads.init'), [
            'name' => 'too-big.mp4',
            'size' => 101 * 1024 * 1024, // just above 100MB
            'mime' => 'video/mp4',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['size']);
    }
}
