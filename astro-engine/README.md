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

## cPanel / Passenger
If you host this service on cPanel via “Setup Node.js App” (Passenger), make sure:

- **Application root** points to the `astro-engine` directory that contains `package.json`.
- **Startup file** is either:
  - `index.js` (ESM)
  - or `app.cjs` (fallback entrypoint, useful if Passenger/ESM is problematic)
- You run **NPM Install** from cPanel UI, then restart the app.

Health check: `GET /health` should return `{ "ok": true }`.

If you cannot access Passenger logs, this app writes a local debug log:
- `astro-engine.log` (and also `.astro-engine.log`) in the application root folder if writable; otherwise in your home directory
