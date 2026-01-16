<?php

namespace App\Services\Astro\Natal;

use Illuminate\Support\Facades\Http;

class NatalChartEngineClient
{
    /**
     * @param array{utc?:string,date?:string,time?:string,timezone?:string,lat:float,lng:float} $payload
     * @return array<string,mixed>
     */
    public function chart(array $payload): array
    {
        $baseUrl = rtrim((string) config('astro.engine_url'), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('astro.engine_url is not configured');
        }

        $timeout = (int) config('astro.timeout_seconds', 3);
        $verify = (bool) config('astro.verify_ssl', true);

        $res = Http::timeout(max(1, $timeout))
            ->retry(1, 150)
            ->withOptions(['verify' => $verify])
            ->acceptJson()
            ->asJson()
            ->post($baseUrl . '/chart', $payload);

        if (!$res->successful()) {
            $body = (string) $res->body();
            throw new \RuntimeException('astro-engine /chart failed: HTTP ' . $res->status() . ' ' . $body);
        }

        $data = $res->json();

        if (!is_array($data)) {
            throw new \RuntimeException('astro-engine /chart invalid payload');
        }

        return $data;
    }
}
