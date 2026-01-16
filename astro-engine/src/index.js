import express from 'express';
import { DateTime } from 'luxon';
import Astronomy from 'astronomy-engine';

import fs from 'fs';
import path from 'path';

function logLine(...parts) {
  const line = `[${new Date().toISOString()}] ${parts.map(String).join(' ')}\n`;

  try {
    fs.appendFileSync(path.join(process.cwd(), '.astro-engine.log'), line, 'utf8');
  } catch {
    // ignore
  }
}

process.on('unhandledRejection', (reason) => {
  // eslint-disable-next-line no-console
  console.error('[astro-engine] unhandledRejection', reason);
  logLine('[astro-engine] unhandledRejection', reason && reason.stack ? reason.stack : String(reason));
  process.exitCode = 1;
});

process.on('uncaughtException', (err) => {
  // eslint-disable-next-line no-console
  console.error('[astro-engine] uncaughtException', err);
  logLine('[astro-engine] uncaughtException', err && err.stack ? err.stack : String(err));
  process.exit(1);
});

const app = express();
app.use(express.json({ limit: '64kb' }));

// Some hosting panels probe the app at "/" to verify it's up.
// Provide a tiny HTML response to make that check pass.
app.get('/', (req, res) => {
  res
    .status(200)
    .type('html')
    .send('astro-engine: ok (try /health)');
});

function signFromLongitude(lon) {
  // Normalize into [0, 360)
  const normalized = ((lon % 360) + 360) % 360;
  const index = Math.floor(normalized / 30);
  const signs = [
    'Bélier',
    'Taureau',
    'Gémeaux',
    'Cancer',
    'Lion',
    'Vierge',
    'Balance',
    'Scorpion',
    'Sagittaire',
    'Capricorne',
    'Verseau',
    'Poissons',
  ];

  const sign = signs[index] ?? '';
  const degInSign = normalized - index * 30;
  return { sign, degInSign, normalized };
}

app.get('/health', (req, res) => {
  res.json({ ok: true });
});

// POST /moon
// Body can be:
// - { utc: '2026-01-16T12:34:00Z' }
// - or { date: 'YYYY-MM-DD', time: 'HH:MM', timezone: 'Europe/Paris' }
app.post('/moon', (req, res) => {
  try {
    const body = req.body ?? {};

    let utc;
    if (typeof body.utc === 'string' && body.utc.trim() !== '') {
      utc = DateTime.fromISO(body.utc, { zone: 'utc' });
    } else {
      const date = typeof body.date === 'string' ? body.date.trim() : '';
      const time = typeof body.time === 'string' ? body.time.trim() : '';
      const timezone = typeof body.timezone === 'string' && body.timezone.trim() !== '' ? body.timezone.trim() : 'Europe/Paris';

      if (!date || !time) {
        return res.status(422).json({ error: 'date and time are required' });
      }

      const local = DateTime.fromISO(`${date}T${time}`, { zone: timezone });
      if (!local.isValid) {
        return res.status(422).json({ error: 'invalid local datetime', details: local.invalidExplanation });
      }
      utc = local.toUTC();
    }

    if (!utc.isValid) {
      return res.status(422).json({ error: 'invalid utc datetime', details: utc.invalidExplanation });
    }

    // astronomy-engine uses a custom Time type. Passing a JS Date is supported.
    const timeObj = Astronomy.MakeTime(utc.toJSDate());

    // Ecliptic longitude of the geocentric Moon (degrees).
    const ecl = Astronomy.EclipticGeoMoon(timeObj);
    const lon = Number(ecl.elon);

    if (!Number.isFinite(lon)) {
      return res.status(500).json({ error: 'moon longitude computation failed' });
    }

    const { sign, degInSign, normalized } = signFromLongitude(lon);

    res.json({
      utc: utc.toISO({ suppressMilliseconds: true }),
      moon_lon: normalized,
      moon_sign: sign,
      moon_deg_in_sign: degInSign,
    });
  } catch (e) {
    // eslint-disable-next-line no-console
    console.error('[astro-engine] /moon error', e);
    logLine('[astro-engine] /moon error', e && e.stack ? e.stack : String(e));
    res.status(500).json({ error: 'internal_error' });
  }
});

const port = Number(process.env.PORT || 3000);
const host = process.env.IP || '0.0.0.0';

const server = app.listen(port, host, () => {
  // eslint-disable-next-line no-console
  console.log(`astro-engine listening on ${host}:${port}`);
  // eslint-disable-next-line no-console
  console.log(`healthcheck: http://${host}:${port}/health`);

  logLine('[astro-engine] listening', `${host}:${port}`);
});

server.on('error', (err) => {
  // eslint-disable-next-line no-console
  console.error('astro-engine server error', err);
  logLine('[astro-engine] server error', err && err.stack ? err.stack : String(err));
  process.exitCode = 1;
});
