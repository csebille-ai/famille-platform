<?php

namespace Tests\Unit;

use App\Services\Ephemeris\EphemerisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EphemerisServiceTest extends TestCase
{
    public function test_proverb_ends_with_allowed_rhyme_word_or_token_fallback(): void
    {
        Cache::flush();

        $svc = app(EphemerisService::class);
        $date = Carbon::create(2026, 1, 25, 12, 0, 0, config('ephemeris.timezone', 'Europe/Paris'));

        $e = $svc->forDate($date);

        $this->assertIsArray($e);
        $this->assertArrayHasKey('saint_name', $e);
        $this->assertArrayHasKey('proverb_text', $e);

        $saintName = (string) $e['saint_name'];
        $proverb = (string) $e['proverb_text'];

        $this->assertNotSame('', trim($saintName));
        $this->assertNotSame('', trim($proverb));

        $last = preg_replace('/[^\p{L}]+/u', '', (string) preg_replace('/.*\s+/u', '', trim((string) preg_replace('/[\p{P}\p{S}]+$/u', '', $proverb))));
        $last = trim((string) $last);

        $this->assertNotSame('', $last);

        $token = trim((string) preg_replace('/.*\s+/u', '', str_replace(['-', '’', "'"], ' ', preg_replace('/\bsaints?\b\s*/iu', '', preg_replace('/\bsaintes?\b\s*/iu', '', $saintName) ?? '') ?? '')));
        $token = preg_replace('/[^\p{L}]+/u', '', (string) $token);
        $token = trim((string) $token);

        $key = (string) (config('ephemeris.rhyme_keys')[mb_strtolower($token, 'UTF-8')] ?? '');
        $bank = (array) (config('ephemeris.rhyme_banks')[$key] ?? []);
        $bankLower = array_map(static fn ($w) => mb_strtolower((string) $w, 'UTF-8'), $bank);

        $lastLower = mb_strtolower($last, 'UTF-8');

        // Either strict bank match, or fallback repeats the token.
        $this->assertTrue(in_array($lastLower, $bankLower, true) || $lastLower === mb_strtolower($token, 'UTF-8'));
    }
}
