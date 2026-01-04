<?php

namespace App\Console\Commands;

use App\Models\CloudFile;
use App\Models\CloudNode;
use App\Models\User;
use Illuminate\Console\Command;

class MigrateCloudFilesToNodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-cloud-files-to-nodes {--user-id= : User id to attribute the root folder to (required if no users exist)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate CloudFile (V1) records to CloudNode (V2) under a root folder node.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user-id');

        if ($userId !== null) {
            $user = User::query()->find($userId);
        } else {
            $user = User::query()->orderBy('id')->first();
        }

        if (!$user) {
            $this->error('No users found. Create a user or pass --user-id.');
            return self::FAILURE;
        }

        $root = CloudNode::query()->firstOrCreate(
            ['parent_id' => null, 'type' => 'folder', 'name' => '/'],
            ['uploaded_by' => $user->id]
        );

        $this->info('Root folder: #' . $root->id);

        $count = 0;
        CloudFile::query()->orderBy('id')->chunk(200, function ($files) use ($root, &$count) {
            foreach ($files as $file) {
                CloudNode::query()->firstOrCreate(
                    ['type' => 'file', 'stored_path' => $file->stored_path],
                    [
                        'parent_id' => $root->id,
                        'name' => $file->original_name,
                        'mime' => $file->mime,
                        'size' => $file->size,
                        'uploaded_by' => $file->uploaded_by,
                        'created_at' => $file->created_at,
                        'updated_at' => $file->updated_at,
                    ]
                );
                $count++;
            }
        });

        $this->info('Migrated (or already present): ' . $count . ' file(s).');
        return self::SUCCESS;
    }
}
