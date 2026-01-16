<?php

namespace App\Services\Astro\Moon;

use Illuminate\Support\Facades\Http;

class MoonEngineClient
{
    /**
     * @param array{utc:string} $payload
     * @return array{moon_lon:float,moon_sign:string,moon_deg_in_sign:float,utc?:string}
     */
    public function moonForUtc(array $payload): array
    {
        $baseUrl = rtrim((string) config('astro.engine_url'), '/');
        $timeout = (int) config('astro.timeout_seconds', 3);

        $http = Http::timeout($timeout)
            ->acceptJson()
            ->asJson();

        if (!((bool) config('astro.verify_ssl', true))) {
            $http = $http->withoutVerifying();
        }

        $resp = $http->post($baseUrl . '/moon', $payload);

        if (!$resp->successful()) {
            $details = '';
            try {
                $json = $resp->json();
                if (is_array($json)) {
                    $msg = is_string($json['message'] ?? null) ? (string) $json['message'] : '';
                    $err = is_string($json['error'] ?? null) ? (string) $json['error'] : '';
                    $details = trim($err . ' ' . $msg);
                } else {
                    $details = trim((string) $resp->body());
                }
            } catch (\Throwable) {
                $details = '';
            }

            if ($details !== '') {
                $details = mb_substr($details, 0, 400);
                throw new \RuntimeException('Astro engine error: HTTP ' . $resp->status() . ' (' . $details . ')');
            }

            throw new \RuntimeException('Astro engine error: HTTP ' . $resp->status());
        }

        $data = $resp->json();
        if (!is_array($data)) {
            throw new \RuntimeException('Astro engine error: invalid JSON');
        }

        $lon = $data['moon_lon'] ?? null;
        $sign = $data['moon_sign'] ?? null;
        $deg = $data['moon_deg_in_sign'] ?? null;

        if (!is_numeric($lon) || !is_string($sign) || $sign === '' || !is_numeric($deg)) {
            throw new \RuntimeException('Astro engine error: missing fields');
        }

        return [
            'moon_lon' => (float) $lon,
            'moon_sign' => (string) $sign,
            'moon_deg_in_sign' => (float) $deg,
            'utc' => is_string($data['utc'] ?? null) ? (string) $data['utc'] : null,
        ];
    }
}
