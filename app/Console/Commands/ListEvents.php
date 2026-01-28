<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class ListEvents extends Command
{
    protected $signature = 'events:list {--limit=20}';

    protected $description = 'List events with their dates and visibility';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $events = Event::query()
            ->orderBy('start_at', 'desc')
            ->limit($limit)
            ->get();

        if ($events->isEmpty()) {
            $this->warn('No events found.');
            return 0;
        }

        $this->info("=== Events (showing {$events->count()}) ===\n");

        $headers = ['ID', 'Title', 'Start', 'End', 'All Day', 'TZ', 'Visibility', 'Status'];
        $rows = [];

        foreach ($events as $event) {
            $rows[] = [
                $event->id,
                $event->title,
                $event->start_at?->format('Y-m-d H:i') ?? 'NULL',
                $event->end_at?->format('Y-m-d H:i') ?? 'NULL',
                $event->all_day ? 'YES' : 'NO',
                $event->timezone ?? 'NULL',
                $event->visibility ?? 'family',
                $event->status ?? 'active',
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
