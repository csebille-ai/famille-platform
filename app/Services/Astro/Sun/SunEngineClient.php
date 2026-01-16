<?php

namespace App\Services\Astro\Sun;

use Illuminate\Support\Facades\Http;

class SunEngineClient
{
    /**
     * @param array{utc?:string,date?:string,time?:string,timezone?:string} $payload
     * @return array{sun_lon:float,sun_sign:string,sun_deg_in_sign:float,utc?:string}
     */
    public function sun(array $payload): array
    {
        $baseUrl = rtrim((string) config('astro.engine_url'), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('astro.engine_url is not configured');
        }

        $verify = (bool) config('astro.verify_ssl', true);

        $res = Http::timeout(6)
            ->retry(1, 150)
            ->withOptions(['verify' => $verify])
            ->acceptJson()
            ->asJson()
            ->post($baseUrl . '/sun', $payload);

        if (!$res->successful()) {
            $body = (string) $res->body();
            throw new \RuntimeException('astro-engine /sun failed: HTTP ' . $res->status() . ' ' . $body);
        }

        $data = (array) $res->json();

        $lon = $data['sun_lon'] ?? null;
        $sign = $data['sun_sign'] ?? null;
        $deg = $data['sun_deg_in_sign'] ?? null;

        if (!is_numeric($lon) || !is_string($sign) || !is_numeric($deg)) {
            throw new \RuntimeException('astro-engine /sun invalid payload');
        }

        return [
            'utc' => isset($data['utc']) && is_string($data['utc']) ? $data['utc'] : null,
            'sun_lon' => (float) $lon,
            'sun_sign' => trim($sign),
            'sun_deg_in_sign' => (float) $deg,
        ];
    }
}
