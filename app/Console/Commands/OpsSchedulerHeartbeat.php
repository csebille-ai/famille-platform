<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class OpsSchedulerHeartbeat extends Command
{
    protected $signature = 'ops:scheduler-heartbeat';

    protected $description = 'Writes a scheduler heartbeat (ops observability).';

    public function handle(): int
    {
        try {
            Cache::put('ops.scheduler.heartbeat_at', now()->toIso8601String(), now()->addDays(7));
        } catch (\Throwable) {
            // ignore
        }

        $this->line('[' . now()->format('Y-m-d H:i:s') . '] scheduler heartbeat');

        return self::SUCCESS;
    }
}
