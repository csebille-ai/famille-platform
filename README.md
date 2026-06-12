# Famille Platform

Private family platform built with Laravel 12.

## Quick Pitch (Recruiter View)

This project is a production-oriented family intranet with real product constraints:

- authenticated private space
- real-time chat and reactions
- media library and uploads (local + Cloudflare R2)
- events and calendar sync (Google Calendar)
- tarot + TTS + astro profile features
- games module (chess, sliding puzzle)
- push notifications and scheduled background jobs

It is designed to run on shared hosting (o2switch) with pragmatic tradeoffs for queue, scheduler, and realtime.

## Tech Stack

- Backend: Laravel 12, PHP 8.2+
- Frontend: Blade, Vite, Tailwind, Alpine.js
- Data: MySQL (Eloquent)
- Realtime: Reverb / Echo / Pusher-compatible flow
- Storage: local disk + Cloudflare R2 (S3 compatible)
- Notifications: Web Push (VAPID)

## Core Features

- Family dashboard and activity feed
- Chat threads, reactions, attachment upload
- Family cloud (images, videos, docs)
- Event management + reminders
- Google Calendar OAuth sync
- Daily/local news import (RSS)
- Tarot draw API and TTS endpoint
- Astro profile integration via external astro-engine
- Mini-games hub (chess, sliding puzzles)
- Admin and ops views

## Project Structure

- `app/` business logic, controllers, models, services
- `routes/web.php` web routes + APIs used by frontend
- `routes/console.php` scheduled jobs
- `resources/views/` Blade templates
- `public/` static assets and built assets entrypoint
- `astro-engine/` companion service used for astro calculations
- `docker/` local/dev infra files
- `tests/` test suite

## Local Setup

### Prerequisites

- PHP 8.2+
- Composer
- Node.js + npm
- MySQL (or compatible)

### Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Run in dev

Option A (all-in-one):

```bash
composer run dev
```

Option B (separate terminals):

```bash
php artisan serve
npm run dev
php artisan queue:listen --tries=1
```

## Environment Notes

Use `.env.o2switch.example` as production template.

Important groups:

- app: `APP_ENV`, `APP_URL`, `APP_DEBUG`
- database: `DB_*`
- uploads/storage: `R2_*`, `UPLOAD_*`
- realtime: `BROADCAST_CONNECTION`, `REVERB_*`, `PUSHER_*`
- astro service: `ASTRO_ENGINE_URL`, `ASTRO_ENGINE_VERIFY_SSL`
- web push: `WEBPUSH_SUBJECT`, `WEBPUSH_PUBLIC_KEY`, `WEBPUSH_PRIVATE_KEY`

## Scheduler and Queue (Production Critical)

The app depends on scheduled commands for:

- RSS import
- reminder jobs
- queue worker execution
- scheduler heartbeat

Shared hosting cron (example):

```bash
* * * * * cd ~/apps/famille-platform && php artisan schedule:run >> /dev/null 2>&1
```

See command definitions in `routes/console.php`.

## Realtime on Shared Hosting

Running a persistent websocket process is usually hard on shared hosting.

Production options:

- degrade to non-realtime behavior
- use external realtime provider (Pusher/Ably)
- host realtime service on a VPS

## Build and Deploy

Main deployment reference:

- `docs/ops/DEPLOY_O2SWITCH.md`

This file includes:

- first deploy steps
- update workflow
- composer fallback with local `composer.phar`
- permissions and cache commands
- cron setup and diagnostics

## Cost and External Services

Monitoring and budget guide:

- `docs/ops/EXTERNAL_COSTS.md`

Includes Cloudflare, OpenAI, mail providers, and alerting checklist.

## Additional Ops Docs

- `docs/ops/FOCUS_COMPOSER.md`

## Testing

```bash
php artisan test
```

## Security Notes

- never commit secrets from `.env`
- keep production keys only on server/cpanel env
- validate upload limits and quota paths before enabling large media uploads

## Repository

- GitHub: https://github.com/csebille-ai/famille-platform
