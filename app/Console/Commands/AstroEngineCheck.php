<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AstroEngineCheck extends Command
{
    protected $signature = 'astro:engine-check {--url=} {--verify-ssl=} {--timeout=}';

    protected $description = 'Diagnostics: checks astro-engine /health and /chart endpoints.';

    public function handle(): int
    {
        $baseUrl = rtrim((string) ($this->option('url') ?: config('astro.engine_url')), '/');
        if ($baseUrl === '') {
            $this->error('astro.engine_url is not configured');
            return self::FAILURE;
        }

        $timeout = $this->option('timeout') !== null
            ? (int) $this->option('timeout')
            : (int) config('astro.timeout_seconds', 3);

        $verify = $this->option('verify-ssl') !== null
            ? filter_var((string) $this->option('verify-ssl'), FILTER_VALIDATE_BOOLEAN)
            : (bool) config('astro.verify_ssl', true);

        $this->line('astro-engine base_url: ' . $baseUrl);
        $this->line('verify_ssl: ' . ($verify ? 'true' : 'false'));
        $this->line('timeout_seconds: ' . max(1, $timeout));

        $client = Http::timeout(max(1, $timeout))
            ->withOptions(['verify' => $verify])
            ->acceptJson();

        $this->newLine();
        $this->line('GET /health');
        $health = $client->get($baseUrl . '/health');
        $this->line('status: ' . $health->status());
        $this->line('content-type: ' . (string) ($health->header('Content-Type') ?? ''));
        $this->line('body: ' . trim(substr((string) $health->body(), 0, 600)));

        $payload = [
            'utc' => now()->utc()->toIso8601String(),
            'lat' => 48.8566,
            'lng' => 2.3522,
        ];

        foreach (['/chart', '/api/chart', '/v1/chart'] as $path) {
            $this->newLine();
            $this->line('POST ' . $path);

            $res = $client->asJson()->post($baseUrl . $path, $payload);
            $this->line('status: ' . $res->status());
            $this->line('content-type: ' . (string) ($res->header('Content-Type') ?? ''));
            $this->line('body: ' . trim(substr((string) $res->body(), 0, 600)));

            if ($res->successful()) {
                $this->info('OK');
                break;
            }
        }

        return self::SUCCESS;
    }
}
