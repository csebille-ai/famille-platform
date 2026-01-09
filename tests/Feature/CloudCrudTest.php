<?php

namespace Tests\Feature;

use App\Models\CloudNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CloudCrudTest extends TestCase
{
    use RefreshDatabase;

    private function setGlobalQuotaGb(float $gb): void
    {
        config(['cloud.quota_global_gb' => $gb]);
    }

    public function test_cloud_routes_are_protected_by_auth(): void
    {
        $this->get(route('cloud.index'))
            ->assertRedirect(route('login'));

        $this->post(route('cloud.folders.store'), ['parent_id' => 1, 'name' => 'X'])
            ->assertRedirect(route('login'));
    }

    public function test_user_can_create_folder_upload_view_download_rename_move_and_delete(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $this->setGlobalQuotaGb(10);

        // Visiting index creates root folder if missing
        $this->get(route('cloud.index'))->assertOk();

        $root = CloudNode::query()
            ->whereNull('parent_id')
            ->where('type', 'folder')
            ->where('name', '/')
            ->firstOrFail();

        // Create folder
        $this->post(route('cloud.folders.store'), [
            'parent_id' => $root->id,
            'name' => 'Admin',
        ])->assertRedirect(route('cloud.index', ['folder' => $root->id]));

        $this->assertDatabaseHas('cloud_audit_logs', ['action' => 'create_folder']);

        $adminFolder = CloudNode::query()
            ->where('type', 'folder')
            ->where('name', 'Admin')
            ->where('parent_id', $root->id)
            ->firstOrFail();

        $upload = UploadedFile::fake()->create('test.pdf', 120, 'application/pdf');

        // Upload file into folder
        $storeResponse = $this->post(route('cloud.files.store'), [
            'parent_id' => $adminFolder->id,
            'file' => $upload,
        ]);

        $fileNode = CloudNode::query()->where('type', 'file')->firstOrFail();

        $storeResponse->assertRedirect(route('cloud.index', ['folder' => $adminFolder->id]));

        $this->assertDatabaseHas('cloud_audit_logs', ['action' => 'upload_file']);

        Storage::disk('local')->assertExists($fileNode->stored_path);

        // Search within current folder
        $this->get(route('cloud.index', ['folder' => $adminFolder->id, 'q' => 'test']))
            ->assertOk()
            ->assertSee('test.pdf');

        // Preview + View + Download
        $this->get(route('cloud.files.preview', $fileNode))
            ->assertOk()
            ->assertSee('Copy link')
            ->assertSee('cloud\\/files\\/' . $fileNode->id . '\\/' . 'preview', false)
            ->assertSee(route('cloud.files.view', $fileNode), false);
        $this->get(route('cloud.files.view', $fileNode))->assertOk();
        $this->get(route('cloud.files.download', $fileNode))->assertOk();

        // Rename
        $this->patch(route('cloud.nodes.rename', $fileNode), [
            'name' => 'renamed.pdf',
        ])->assertRedirect(route('cloud.index', ['folder' => $adminFolder->id]));

        $this->assertDatabaseHas('cloud_audit_logs', ['action' => 'rename', 'node_id' => $fileNode->id]);

        $this->assertDatabaseHas('cloud_nodes', [
            'id' => $fileNode->id,
            'name' => 'renamed.pdf',
        ]);

        // Create another folder
        $this->post(route('cloud.folders.store'), [
            'parent_id' => $root->id,
            'name' => 'Other',
        ])->assertRedirect(route('cloud.index', ['folder' => $root->id]));

        $otherFolder = CloudNode::query()
            ->where('type', 'folder')
            ->where('name', 'Other')
            ->where('parent_id', $root->id)
            ->firstOrFail();

        // Move file to Other
        $this->post(route('cloud.nodes.move'), [
            'node_id' => $fileNode->id,
            'new_parent_id' => $otherFolder->id,
        ])->assertRedirect(route('cloud.index', ['folder' => $otherFolder->id]));

        $this->assertDatabaseHas('cloud_audit_logs', ['action' => 'move', 'node_id' => $fileNode->id]);

        $this->assertDatabaseHas('cloud_nodes', [
            'id' => $fileNode->id,
            'parent_id' => $otherFolder->id,
        ]);

        // Cannot delete non-empty folder
        $this->post(route('cloud.files.store'), [
            'parent_id' => $adminFolder->id,
            'file' => UploadedFile::fake()->create('x.txt', 1, 'text/plain'),
        ]);

        $textNode = CloudNode::query()->where('type', 'file')->where('parent_id', $adminFolder->id)->firstOrFail();
        $this->get(route('cloud.index', ['folder' => $adminFolder->id]))
            ->assertOk()
            ->assertSee('Copy link')
            ->assertSee(route('cloud.files.download', $textNode));

        $this->get(route('cloud.files.preview', $textNode))
            ->assertRedirect(route('cloud.index', ['folder' => $adminFolder->id]))
            ->assertSessionHas('status');

        $this->delete(route('cloud.nodes.destroy', $adminFolder))
            ->assertRedirect(route('cloud.index', ['folder' => $adminFolder->id]));

        $this->assertDatabaseHas('cloud_nodes', [
            'id' => $adminFolder->id,
            'type' => 'folder',
        ]);

        // Delete file and then delete empty folder
        $adminFile = CloudNode::query()->where('type', 'file')->where('parent_id', $adminFolder->id)->firstOrFail();
        Storage::disk('local')->assertExists($adminFile->stored_path);

        $this->delete(route('cloud.nodes.destroy', $adminFile))
            ->assertRedirect(route('cloud.index', ['folder' => $adminFolder->id]));

        $this->assertDatabaseHas('cloud_audit_logs', ['action' => 'trash', 'node_id' => $adminFile->id]);

        // Soft delete keeps file on disk until purge
        Storage::disk('local')->assertExists($adminFile->stored_path);
        $this->assertSoftDeleted('cloud_nodes', ['id' => $adminFile->id]);

        // Restore from trash
        $this->patch(route('cloud.trash.restore', $adminFile->id))
            ->assertRedirect(route('cloud.trash'));
        $this->assertDatabaseHas('cloud_audit_logs', ['action' => 'restore', 'node_id' => $adminFile->id]);
        $this->assertDatabaseHas('cloud_nodes', ['id' => $adminFile->id, 'deleted_at' => null]);

        // Delete again then purge permanently
        $this->delete(route('cloud.nodes.destroy', $adminFile))
            ->assertRedirect(route('cloud.index', ['folder' => $adminFolder->id]));
        $this->assertSoftDeleted('cloud_nodes', ['id' => $adminFile->id]);

        $this->delete(route('cloud.trash.purge', $adminFile->id))
            ->assertRedirect(route('cloud.trash'));
        $this->assertDatabaseHas('cloud_audit_logs', ['action' => 'purge', 'node_id' => $adminFile->id]);
        Storage::disk('local')->assertMissing($adminFile->stored_path);
        $this->assertDatabaseMissing('cloud_nodes', ['id' => $adminFile->id]);

        $this->delete(route('cloud.nodes.destroy', $adminFolder))
            ->assertRedirect(route('cloud.index', ['folder' => $root->id]));

        $this->assertSoftDeleted('cloud_nodes', ['id' => $adminFolder->id]);
    }

    public function test_validation_requires_file(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $this->setGlobalQuotaGb(10);

        $this->get(route('cloud.index'))->assertOk();
        $root = CloudNode::query()
            ->whereNull('parent_id')
            ->where('type', 'folder')
            ->where('name', '/')
            ->firstOrFail();

        $this->from(route('cloud.index'))
            ->post(route('cloud.files.store'), [
                'parent_id' => $root->id,
            ])
            ->assertRedirect(route('cloud.index'))
            ->assertSessionHasErrors(['file']);
    }

    public function test_member_cannot_upload_move_rename_or_delete(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'member']);
        $this->actingAs($user);

        $this->setGlobalQuotaGb(10);

        $this->get(route('cloud.index'))->assertOk();

        $root = CloudNode::query()
            ->whereNull('parent_id')
            ->where('type', 'folder')
            ->where('name', '/')
            ->firstOrFail();

        $this->post(route('cloud.folders.store'), [
            'parent_id' => $root->id,
            'name' => 'Nope',
        ])->assertForbidden();

        $this->post(route('cloud.files.store'), [
            'parent_id' => $root->id,
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $node = CloudNode::create([
            'parent_id' => $root->id,
            'type' => 'file',
            'name' => 'x.pdf',
            'stored_path' => 'private/cloud/x.pdf',
            'mime' => 'application/pdf',
            'size' => 123,
            'uploaded_by' => $user->id,
        ]);

        $this->patch(route('cloud.nodes.rename', $node), ['name' => 'y.pdf'])->assertForbidden();
        $this->post(route('cloud.nodes.move'), ['node_id' => $node->id, 'new_parent_id' => $root->id])->assertForbidden();
        $this->delete(route('cloud.nodes.destroy', $node))->assertForbidden();
    }

    public function test_quota_usage_is_displayed_and_blocks_upload_when_exceeded(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        // Use a tiny quota so we can hit it in tests.
        $this->setGlobalQuotaGb(0.00001);

        $this->get(route('cloud.index'))
            ->assertOk()
            ->assertSee('Utilisé:', false);

        $root = CloudNode::query()
            ->whereNull('parent_id')
            ->where('type', 'folder')
            ->where('name', '/')
            ->firstOrFail();

        // Seed usage near quota.
        $quotaBytes = (int) round(0.00001 * 1024 * 1024 * 1024);
        CloudNode::create([
            'parent_id' => $root->id,
            'type' => 'file',
            'name' => 'seed.bin',
            'stored_path' => 'private/cloud/seed.bin',
            'mime' => 'application/octet-stream',
            'size' => max(1, $quotaBytes - 1),
            'uploaded_by' => $user->id,
        ]);

        $beforeCount = CloudNode::query()->where('type', 'file')->count();

        $response = $this->post(route('cloud.files.store'), [
            'parent_id' => $root->id,
            'file' => UploadedFile::fake()->create('big.bin', 50, 'application/octet-stream'),
        ]);

        $response->assertRedirect(route('cloud.index', ['folder' => $root->id]));
        $response->assertSessionHas('error');

        $afterCount = CloudNode::query()->where('type', 'file')->count();
        $this->assertSame($beforeCount, $afterCount);
    }

    public function test_audit_page_is_restricted_to_manage_cloud(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $this->actingAs($member);
        $this->get(route('cloud.audit'))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->get(route('cloud.audit'))->assertOk();
    }
}
