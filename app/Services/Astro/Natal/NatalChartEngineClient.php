<?php

namespace App\Services\Astro\Natal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $paths = ['/chart', '/api/chart', '/v1/chart'];
        $lastError = null;

        foreach ($paths as $path) {
            $url = $baseUrl . $path;

            $res = Http::timeout(max(1, $timeout))
                ->retry(1, 150)
                ->withOptions(['verify' => $verify])
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            if ($res->successful()) {
                $data = $res->json();

                if (!is_array($data)) {
                    throw new \RuntimeException('astro-engine chart invalid payload (expected JSON object)');
                }

                if ($path !== '/chart') {
                    Log::info('astro-engine chart used non-default path', [
                        'url' => $url,
                        'base_url' => $baseUrl,
                        'verify_ssl' => $verify,
                    ]);
                }

                return $data;
            }

            $contentType = (string) ($res->header('Content-Type') ?? '');
            $rawBody = (string) $res->body();
            $body = trim(substr($rawBody, 0, 600));
            $lastError = sprintf('POST %s -> HTTP %d (%s) %s', $url, $res->status(), $contentType, $body);

            // If the endpoint isn't found, try the next known prefix.
            if ($res->status() === 404) {
                continue;
            }

            break;
        }

        Log::warning('astro-engine chart call failed', [
            'base_url' => $baseUrl,
            'verify_ssl' => $verify,
            'timeout_seconds' => $timeout,
            'error' => $lastError,
        ]);

        throw new \RuntimeException('astro-engine chart failed: ' . ($lastError ?? 'unknown error'));
    }
}
