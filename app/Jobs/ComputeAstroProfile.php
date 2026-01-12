<?php

namespace App\Jobs;

use App\Models\AstroProfile;
use App\Models\User;
use App\Services\Astro\Geo\BirthPlaceAutoResolver;
use App\Services\Astro\AstroMixer;
use App\Services\Astro\AstroProfileComputer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ComputeAstroProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId)
    {
    }

    public function handle(AstroProfileComputer $computer): void
    {
        try {
            $user = User::query()->find($this->userId);
            if (!$user) {
                return;
            }

            // Auto-fill missing geo/timezone data from birth_place (best effort).
            try {
                $resolver = app(BirthPlaceAutoResolver::class);
                $updates = $resolver->resolve($user);
                if ($updates !== []) {
                    User::withoutEvents(function () use ($user, $updates) {
                        $user->forceFill($updates);
                        $user->save();
                    });
                    $user->refresh();
                }
            } catch (Throwable $e) {
                // best-effort; do not block profile computation
            }

            $payload = $computer->compute($user);

            if ($payload === []) {
                $mix = AstroMixer::mix([]);
                AstroProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'user_id' => $user->id,
                        'signature' => $mix['signature'],
                        'archetype' => $mix['archetype'],
                        'talents' => $mix['talents'],
                        'weakness' => $mix['weakness'],
                        'computed_at' => now(),
                    ]
                );
                return;
            }

            AstroProfile::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($payload, [
                    'user_id' => $user->id,
                    'computed_at' => now(),
                ])
            );
        } catch (Throwable $e) {
            Log::error('ComputeAstroProfile failed', [
                'user_id' => $this->userId,
                'exception' => $e,
            ]);
        }
    }
}
