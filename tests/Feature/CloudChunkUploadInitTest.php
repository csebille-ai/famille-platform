<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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

    public function test_chunk_upload_init_can_resume_existing_upload_and_returns_received_chunks(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        config(['cloud.max_file_kb' => 1048576]);

        // Start a session.
        $init = $this->postJson(route('cloud.uploads.init'), [
            'name' => 'resume.pdf',
            'size' => 1024 * 1024, // 1MB
            'mime' => 'application/pdf',
        ])->assertCreated()->json();

        $uploadId = (string) ($init['upload_id'] ?? '');
        $this->assertNotSame('', $uploadId);

        // Upload first chunk.
        $chunk = UploadedFile::fake()->createWithContent('chunk.bin', str_repeat('a', 512));
        $this->post(route('cloud.uploads.chunk'), [
            'upload_id' => $uploadId,
            'index' => 0,
            'chunk' => $chunk,
        ])->assertOk();

        // Ask init again with same file + upload_id to resume.
        $resumed = $this->postJson(route('cloud.uploads.init'), [
            'upload_id' => $uploadId,
            'name' => 'resume.pdf',
            'size' => 1024 * 1024,
            'mime' => 'application/pdf',
        ])->assertOk()->json();

        $this->assertSame($uploadId, (string) ($resumed['upload_id'] ?? ''));
        $this->assertTrue(($resumed['resumed'] ?? false) === true);
        $received = $resumed['received'] ?? [];
        $this->assertIsArray($received);
        $this->assertContains(0, $received);

        // Cleanup temp dir (best-effort) so local runs don't accumulate files.
        try {
            $dir = storage_path('app/private/chunk-uploads/' . $uploadId);
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
