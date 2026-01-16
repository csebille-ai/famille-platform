# astro-engine

Internal microservice used by the Laravel app to compute Moon position/sign from an instant.

## Endpoints
- `GET /health`
- `POST /moon`
  - `{ "utc": "2026-01-16T12:34:00Z" }`
  - or `{ "date": "YYYY-MM-DD", "time": "HH:MM", "timezone": "Europe/Paris" }`

## Local (Sail)
The service is started via `compose.yaml` as `astro-engine`.

- Internal URL from Laravel containers: `http://astro-engine:3000`
- Host port: `${ASTRO_ENGINE_PORT:-3010}`
