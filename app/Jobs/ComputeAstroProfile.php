<?php

namespace App\Jobs;

use App\Models\AstroProfile;
use App\Models\User;
use App\Services\Astro\Geo\BirthPlaceAutoResolver;
use App\Services\Astro\Moon\MoonSignResolver;
use App\Services\Astro\Natal\NatalChartResolver;
use App\Services\Astro\Sun\SunKemeticResolver;
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

    public function handle(AstroProfileComputer $computer, MoonSignResolver $moonResolver, SunKemeticResolver $sunKemetic, NatalChartResolver $natalResolver): void
    {
        try {
            $user = User::query()->find($this->userId);
            if (!$user) {
                return;
            }

            $user->loadMissing('astroProfile');

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

            // Compute moon sign with minimal requirements (date + time, tz Europe/Paris).
            // Cached via astro_hash on astro_profiles.
            $moon = [
                'moon_sign' => $user->astroProfile?->moon_sign,
                'moon_lon' => $user->astroProfile?->moon_lon,
                'moon_deg_in_sign' => $user->astroProfile?->moon_deg_in_sign,
                'astro_hash' => $user->astroProfile?->astro_hash,
                'astro_computed_at' => $user->astroProfile?->astro_computed_at,
            ];
            try {
                $moon = $moonResolver->resolve($user, $user->astroProfile);
            } catch (Throwable $e) {
                // best-effort; do not block profile computation
                Log::warning('Moon computation failed', [
                    'user_id' => $user->id,
                    'exception' => $e,
                ]);
            }

            // Compute sun longitude and kemetic decan (best effort, cached via kemetic_hash).
            $kemetic = [
                'sun_lon' => $user->astroProfile?->sun_lon,
                'sun_deg_in_sign' => $user->astroProfile?->sun_deg_in_sign,
                'kemetic_decan_index' => $user->astroProfile?->kemetic_decan_index,
                'kemetic_decan_label' => $user->astroProfile?->kemetic_decan_label,
                'kemetic_decan_keyword' => $user->astroProfile?->kemetic_decan_keyword,
                'kemetic_hash' => $user->astroProfile?->kemetic_hash,
                'kemetic_computed_at' => $user->astroProfile?->kemetic_computed_at,
            ];
            try {
                $kemetic = $sunKemetic->resolve($user, $user->astroProfile);
            } catch (Throwable $e) {
                Log::warning('Kemetic decan computation failed', [
                    'user_id' => $user->id,
                    'exception' => $e,
                ]);
            }

            // Natal chart (angles/houses/planets) (best effort, cached via natal_hash).
            $natal = [
                'natal' => $user->astroProfile?->natal,
                'natal_hash' => $user->astroProfile?->natal_hash,
                'natal_computed_at' => $user->astroProfile?->natal_computed_at,
            ];
            try {
                $natal = $natalResolver->resolve($user, $user->astroProfile);
            } catch (Throwable $e) {
                Log::warning('Natal chart computation failed', [
                    'user_id' => $user->id,
                    'exception' => $e,
                ]);
            }

            if ($payload === []) {
                $mix = AstroMixer::mix([]);
                $profile = AstroProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'user_id' => $user->id,
                        'moon_sign' => $moon['moon_sign'],
                        'moon_lon' => $moon['moon_lon'],
                        'moon_deg_in_sign' => $moon['moon_deg_in_sign'],
                        'sun_lon' => $kemetic['sun_lon'],
                        'sun_deg_in_sign' => $kemetic['sun_deg_in_sign'],
                        'kemetic_decan_index' => $kemetic['kemetic_decan_index'],
                        'kemetic_decan_label' => $kemetic['kemetic_decan_label'],
                        'kemetic_decan_keyword' => $kemetic['kemetic_decan_keyword'],
                        'kemetic_hash' => $kemetic['kemetic_hash'],
                        'kemetic_computed_at' => $kemetic['kemetic_computed_at'],
                        'astro_hash' => $moon['astro_hash'],
                        'astro_computed_at' => $moon['astro_computed_at'],
                        'natal' => $natal['natal'],
                        'natal_hash' => $natal['natal_hash'],
                        'natal_computed_at' => $natal['natal_computed_at'],
                        'signature' => $mix['signature'],
                        'archetype' => $mix['archetype'],
                        'talents' => $mix['talents'],
                        'weakness' => $mix['weakness'],
                        'computed_at' => now(),
                    ]
                );

                $signatureJson = [
                    'sun_sign' => null,
                    'moon_sign' => $profile->moon_sign ?? null,
                    'ascendant' => null,
                    'chinese' => [
                        'polarity' => null,
                        'element' => null,
                        'animal' => null,
                    ],
                    'kemetic_decan_index' => $profile->kemetic_decan_index ?? null,
                    'kemetic_decan_label' => $profile->kemetic_decan_label ?? null,
                    'kemetic_decan_keyword' => $profile->kemetic_decan_keyword ?? null,
                    'archetype' => (string) ($profile->archetype ?? ''),
                    'talents' => (array) ($profile->talents ?? []),
                    'vigilance' => (string) ($profile->weakness ?? ''),
                ];

                User::withoutEvents(function () use ($user, $signatureJson) {
                    $user->forceFill([
                        'astro_signature_json' => $signatureJson,
                    ])->save();
                });
                return;
            }

            $baseForMix = array_merge($payload, [
                'moon_sign' => $moon['moon_sign'],
                'kemetic_decan_index' => $kemetic['kemetic_decan_index'],
            ]);
            $mix = AstroMixer::mix($baseForMix);

            $profile = AstroProfile::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($payload, $mix, [
                    'user_id' => $user->id,
                    'moon_sign' => $moon['moon_sign'],
                    'moon_lon' => $moon['moon_lon'],
                    'moon_deg_in_sign' => $moon['moon_deg_in_sign'],
                    'sun_lon' => $kemetic['sun_lon'],
                    'sun_deg_in_sign' => $kemetic['sun_deg_in_sign'],
                    'kemetic_decan_index' => $kemetic['kemetic_decan_index'],
                    'kemetic_decan_label' => $kemetic['kemetic_decan_label'],
                    'kemetic_decan_keyword' => $kemetic['kemetic_decan_keyword'],
                    'kemetic_hash' => $kemetic['kemetic_hash'],
                    'kemetic_computed_at' => $kemetic['kemetic_computed_at'],
                    'astro_hash' => $moon['astro_hash'],
                    'astro_computed_at' => $moon['astro_computed_at'],
                    'natal' => $natal['natal'],
                    'natal_hash' => $natal['natal_hash'],
                    'natal_computed_at' => $natal['natal_computed_at'],
                    'computed_at' => now(),
                ])
            );

            $signatureJson = [
                'sun_sign' => $profile->western_sign ?? null,
                'moon_sign' => $profile->moon_sign ?? null,
                'ascendant' => $profile->ascendant_sign ?? null,
                'chinese' => [
                    'polarity' => $profile->chinese_yin_yang ?? null,
                    'element' => $profile->chinese_element ?? null,
                    'animal' => $profile->chinese_animal ?? null,
                ],
                'kemetic_decan_index' => $profile->kemetic_decan_index ?? null,
                'kemetic_decan_label' => $profile->kemetic_decan_label ?? null,
                'kemetic_decan_keyword' => $profile->kemetic_decan_keyword ?? null,
                'archetype' => $profile->archetype ?? null,
                'talents' => (array) ($profile->talents ?? []),
                'vigilance' => $profile->weakness ?? null,
            ];

            User::withoutEvents(function () use ($user, $signatureJson) {
                $user->forceFill([
                    'astro_signature_json' => $signatureJson,
                ])->save();
            });
        } catch (Throwable $e) {
            Log::error('ComputeAstroProfile failed', [
                'user_id' => $this->userId,
                'exception' => $e,
            ]);
        }
    }
}
