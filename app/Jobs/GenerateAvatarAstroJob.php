<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Astro\Images\ImageProvider;
use App\Services\AvatarAstro\ArchetypeAndTraits;
use App\Services\AvatarAstro\AvatarAstroPromptBuilder;
use App\Services\AvatarAstro\AvatarSpecBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAvatarAstroJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId)
    {
    }

    public function handle(): void
    {
        // Feature removed.
    }
}
