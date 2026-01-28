<?php

namespace App\Console\Commands;

use App\Models\GoogleAccount;
use App\Models\GoogleCalendar;
use App\Models\GoogleEventLink;
use App\Models\User;
use Illuminate\Console\Command;

class GoogleCalendarDiagnostic extends Command
{
    protected $signature = 'google:diagnostic {user_id=1 : User ID to diagnose}';

    protected $description = 'Show Google Calendar sync diagnostic info (calendar ID, links count, direct URL)';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $user = User::query()->find($userId);

        if (!$user) {
            $this->error("User {$userId} not found.");
            return 1;
        }

        $this->info("=== Google Calendar Diagnostic for User #{$userId} ({$user->name}) ===\n");

        $account = GoogleAccount::query()->where('user_id', $userId)->first();
        if (!$account) {
            $this->warn("❌ No GoogleAccount found.");
            return 1;
        }

        $this->info("✅ GoogleAccount exists:");
        $this->line("   - Connected: " . ($account->isConnected() ? 'YES' : 'NO'));
        $this->line("   - Revoked: " . ($account->revoked_at ? 'YES (' . $account->revoked_at . ')' : 'NO'));
        $this->line("   - Token expires: " . ($account->expires_at ?? 'unknown'));

        $calendar = GoogleCalendar::query()->where('user_id', $userId)->first();
        if (!$calendar) {
            $this->warn("\n❌ No GoogleCalendar row found.");
            return 1;
        }

        $this->info("\n✅ GoogleCalendar row exists:");
        $this->line("   - Summary: " . ($calendar->summary ?? 'N/A'));
        $this->line("   - Timezone: " . ($calendar->timezone ?? 'N/A'));
        $this->line("   - Sync enabled: " . ($calendar->is_enabled ? 'YES' : 'NO'));
        $this->line("   - Google Calendar ID: " . ($calendar->google_calendar_id ?? '(not created yet)'));

        if (!$calendar->google_calendar_id) {
            $this->warn("\n⚠️  Calendar not yet created in Google. Trigger a resync or create an event to auto-create it.");
            return 1;
        }

        $calId = (string) $calendar->google_calendar_id;
        $linksCount = GoogleEventLink::query()->where('user_id', $userId)->count();

        $this->info("\n✅ Google Calendar ID: {$calId}");
        $this->info("✅ Event links pushed: {$linksCount}");

        $this->newLine();
        $this->comment("📅 Direct link to view this calendar in Google Calendar UI:");
        $this->line("   https://calendar.google.com/calendar/u/0/r/settings/calendar/{$calId}");

        $this->newLine();
        $this->comment("🔍 To check if the calendar is visible:");
        $this->line("   1. Open: https://calendar.google.com/");
        $this->line("   2. On the left sidebar, look for '{$calendar->summary}'");
        $this->line("   3. If hidden, click the checkbox next to it to show events.");

        $this->newLine();
        $this->comment("🐛 If calendar doesn't appear in the list:");
        $this->line("   - The OAuth connection may be for a different Google account");
        $this->line("   - Or the calendar was manually deleted in Google");
        $this->line("   - Solution: Disconnect & reconnect Google Calendar in Profile");

        return 0;
    }
}
