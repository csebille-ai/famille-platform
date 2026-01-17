import express from 'express';
import { DateTime } from 'luxon';
import * as Astronomy from 'astronomy-engine';

import fs from 'fs';
import path from 'path';

function logLine(...parts) {
  const line = `[${new Date().toISOString()}] ${parts.map(String).join(' ')}\n`;

  try {
    fs.appendFileSync(path.join(process.cwd(), '.astro-engine.log'), line, 'utf8');
    fs.appendFileSync(path.join(process.cwd(), 'astro-engine.log'), line, 'utf8');
  } catch {
    // ignore
  }
}

const DEBUG = (() => {
  const v = String(process.env.ASTRO_ENGINE_DEBUG || '').toLowerCase().trim();
  return v === '1' || v === 'true' || v === 'yes';
})();

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

// Log every request/response so we can diagnose Passenger/proxy issues.
app.use((req, res, next) => {
  const startedAt = Date.now();
  res.on('finish', () => {
    const ms = Date.now() - startedAt;
    logLine('[astro-engine] req', req.method, req.originalUrl, String(res.statusCode), `${ms}ms`);
  });
  next();
});

// Some hosting panels probe the app at "/" to verify it's up.
app.get('/', (req, res) => {
  res.status(200).type('html').send('astro-engine: ok (try /health)');
});

function signFromLongitude(lon) {
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

function normalizeDeg(deg) {
  return ((deg % 360) + 360) % 360;
}

function deg2rad(deg) {
  return (deg * Math.PI) / 180;
}

function rad2deg(rad) {
  return (rad * 180) / Math.PI;
}

function computeAngles({ timeObj, latitudeDeg, longitudeDeg }) {
  // Local Sidereal Time (degrees).
  const gmstHours = Number(Astronomy.SiderealTime(timeObj));
  const lstDeg = normalizeDeg(gmstHours * 15 + longitudeDeg);

  // True obliquity of the ecliptic (degrees).
  const tilt = Astronomy.e_tilt(timeObj);
  const epsDeg = Number(tilt?.tobl);

  const theta = deg2rad(lstDeg);
  const phi = deg2rad(latitudeDeg);
  const eps = deg2rad(epsDeg);

  // MC: intersection of meridian with ecliptic.
  const mcRad = Math.atan2(Math.sin(theta) * Math.cos(eps), Math.cos(theta));
  const mcDeg = normalizeDeg(rad2deg(mcRad));

  // Ascendant.
  const ascRad = Math.atan2(
    Math.sin(theta) * Math.cos(eps) - Math.tan(phi) * Math.sin(eps),
    Math.cos(theta)
  );
  const ascDeg = normalizeDeg(rad2deg(ascRad));

  return {
    lst_deg: lstDeg,
    obliquity_deg: epsDeg,
    asc_deg: ascDeg,
    mc_deg: mcDeg,
  };
}

function equalHouses(ascDeg) {
  const cusps = [];
  for (let i = 0; i < 12; i += 1) {
    const cuspLon = normalizeDeg(ascDeg + i * 30);
    cusps.push({ house: i + 1, cusp_lon: cuspLon, ...signFromLongitude(cuspLon) });
  }
  return cusps;
}

function houseForLongitudeEqual(lon, ascDeg) {
  const rel = normalizeDeg(lon - ascDeg);
  return Math.floor(rel / 30) + 1;
}

app.get('/health', (req, res) => {
  res.json({ ok: true });
});

function chartHandler(req, res) {
  try {
    if (DEBUG) {
      logLine('[astro-engine] /chart start');
    }

    const body = req.body ?? {};
    const lat = Number(body.lat);
    const lng = Number(body.lng);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
      return res.status(422).json({ error: 'lat and lng are required' });
    }

    let utc;
    if (typeof body.utc === 'string' && body.utc.trim() !== '') {
      utc = DateTime.fromISO(body.utc, { zone: 'utc' });
    } else {
      const date = typeof body.date === 'string' ? body.date.trim() : '';
      const time = typeof body.time === 'string' ? body.time.trim() : '';
      const timezone =
        typeof body.timezone === 'string' && body.timezone.trim() !== ''
          ? body.timezone.trim()
          : 'Europe/Paris';

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

    const timeObj = Astronomy.MakeTime(utc.toJSDate());

    // Angles & houses.
    const anglesRaw = computeAngles({ timeObj, latitudeDeg: lat, longitudeDeg: lng });
    const houses = equalHouses(anglesRaw.asc_deg);

    const asc = signFromLongitude(anglesRaw.asc_deg);
    const mc = signFromLongitude(anglesRaw.mc_deg);

    // Planets (geocentric ecliptic longitude).
    const planets = [];

    // Sun (special helper).
    const sunPos = Astronomy.SunPosition(timeObj);
    const sunLon = normalizeDeg(Number(sunPos.elon));
    const sun = signFromLongitude(sunLon);
    planets.push({
      key: 'sun',
      name: 'Soleil',
      lon: sunLon,
      sign: sun.sign,
      deg_in_sign: sun.degInSign,
      house: houseForLongitudeEqual(sunLon, anglesRaw.asc_deg),
    });

    // Moon (geocentric helper).
    const moonEcl = Astronomy.EclipticGeoMoon(timeObj);
    const moonLon = normalizeDeg(Number(moonEcl.lon));
    const moon = signFromLongitude(moonLon);
    planets.push({
      key: 'moon',
      name: 'Lune',
      lon: moonLon,
      sign: moon.sign,
      deg_in_sign: moon.degInSign,
      house: houseForLongitudeEqual(moonLon, anglesRaw.asc_deg),
    });

    // Planets.
    for (const [key, bodyName, name] of [
      // Personal planets.
      ['mercury', 'Mercury', 'Mercure'],
      ['venus', 'Venus', 'Vénus'],
      ['mars', 'Mars', 'Mars'],

      // Outer planets.
      ['jupiter', 'Jupiter', 'Jupiter'],
      ['saturn', 'Saturn', 'Saturne'],
      ['uranus', 'Uranus', 'Uranus'],
      ['neptune', 'Neptune', 'Neptune'],
      ['pluto', 'Pluto', 'Pluton'],
    ]) {
      const vec = Astronomy.GeoVector(bodyName, timeObj, true);
      const ecl = Astronomy.Ecliptic(vec);
      const lon = normalizeDeg(Number(ecl.elon));
      const s = signFromLongitude(lon);
      planets.push({
        key,
        name,
        lon,
        sign: s.sign,
        deg_in_sign: s.degInSign,
        house: houseForLongitudeEqual(lon, anglesRaw.asc_deg),
      });
    }

    return res.json({
      utc: utc.toISO({ suppressMilliseconds: true }),
      angles: {
        asc: { lon: anglesRaw.asc_deg, sign: asc.sign, deg_in_sign: asc.degInSign },
        mc: { lon: anglesRaw.mc_deg, sign: mc.sign, deg_in_sign: mc.degInSign },
      },
      houses,
      planets,
    });
  } catch (e) {
    // eslint-disable-next-line no-console
    console.error('[astro-engine] /chart error', e);
    try {
      const payload = (() => {
        try {
          return JSON.stringify(req.body ?? {});
        } catch {
          return '[unserializable]';
        }
      })();
      logLine('[astro-engine] /chart error', e && e.stack ? e.stack : String(e), 'payload=', payload);
    } catch {
      logLine('[astro-engine] /chart error', e && e.stack ? e.stack : String(e));
    }

    if (DEBUG) {
      return res.status(500).json({
        error: 'internal_error',
        message: e && typeof e === 'object' && 'message' in e ? String(e.message) : String(e),
      });
    }

    return res.status(500).json({ error: 'internal_error' });
  }
}

// Stable contract: accept multiple prefixes to survive proxy/base-path configs.
app.post(['/chart', '/api/chart', '/v1/chart'], chartHandler);

// Helpful JSON errors instead of Express default HTML.
app.get(['/chart', '/api/chart', '/v1/chart'], (req, res) => {
  res.status(405).json({ error: 'method not allowed', hint: 'Use POST', allowed: ['POST'] });
});

// POST /sun
// Body can be:
// - { utc: '2026-01-16T12:34:00Z' }
// - or { date: 'YYYY-MM-DD', time?: 'HH:MM', timezone?: 'Europe/Paris' }
// If time is missing, we default to 12:00 to keep it stable.
app.post('/sun', (req, res) => {
  try {
    if (DEBUG) {
      logLine('[astro-engine] /sun start');
    }

    const body = req.body ?? {};

    let utc;
    if (typeof body.utc === 'string' && body.utc.trim() !== '') {
      utc = DateTime.fromISO(body.utc, { zone: 'utc' });
    } else {
      const date = typeof body.date === 'string' ? body.date.trim() : '';
      let time = typeof body.time === 'string' ? body.time.trim() : '';
      const timezone = typeof body.timezone === 'string' && body.timezone.trim() !== '' ? body.timezone.trim() : 'Europe/Paris';

      if (!date) {
        return res.status(422).json({ error: 'date is required' });
      }
      if (!time) {
        time = '12:00';
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

    const timeObj = Astronomy.MakeTime(utc.toJSDate());
    const pos = Astronomy.SunPosition(timeObj);
    const lon = Number(pos.elon);

    if (!Number.isFinite(lon)) {
      return res.status(500).json({ error: 'sun longitude computation failed' });
    }

    const { sign, degInSign, normalized } = signFromLongitude(lon);

    return res.json({
      utc: utc.toISO({ suppressMilliseconds: true }),
      sun_lon: normalized,
      sun_sign: sign,
      sun_deg_in_sign: degInSign,
    });
  } catch (e) {
    // eslint-disable-next-line no-console
    console.error('[astro-engine] /sun error', e);
    try {
      const payload = (() => {
        try {
          return JSON.stringify(req.body ?? {});
        } catch {
          return '[unserializable]';
        }
      })();
      logLine('[astro-engine] /sun error', e && e.stack ? e.stack : String(e), 'payload=', payload);
    } catch {
      logLine('[astro-engine] /sun error', e && e.stack ? e.stack : String(e));
    }

    if (DEBUG) {
      return res.status(500).json({
        error: 'internal_error',
        message: e && typeof e === 'object' && 'message' in e ? String(e.message) : String(e),
      });
    }

    return res.status(500).json({ error: 'internal_error' });
  }
});

// POST /moon
// Body can be:
// - { utc: '2026-01-16T12:34:00Z' }
// - or { date: 'YYYY-MM-DD', time: 'HH:MM', timezone: 'Europe/Paris' }
app.post('/moon', (req, res) => {
  try {
    if (DEBUG) {
      logLine('[astro-engine] /moon start');
    }
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
    const lon = Number(ecl.lon);

    if (!Number.isFinite(lon)) {
      return res.status(500).json({ error: 'moon longitude computation failed' });
    }

    const { sign, degInSign, normalized } = signFromLongitude(lon);

    return res.json({
      utc: utc.toISO({ suppressMilliseconds: true }),
      moon_lon: normalized,
      moon_sign: sign,
      moon_deg_in_sign: degInSign,
    });
  } catch (e) {
    // eslint-disable-next-line no-console
    console.error('[astro-engine] /moon error', e);
    try {
      const payload = (() => {
        try {
          return JSON.stringify(req.body ?? {});
        } catch {
          return '[unserializable]';
        }
      })();
      logLine('[astro-engine] /moon error', e && e.stack ? e.stack : String(e), 'payload=', payload);
    } catch {
      logLine('[astro-engine] /moon error', e && e.stack ? e.stack : String(e));
    }

    if (DEBUG) {
      return res.status(500).json({
        error: 'internal_error',
        message: e && typeof e === 'object' && 'message' in e ? String(e.message) : String(e),
      });
    }

    return res.status(500).json({ error: 'internal_error' });
  }
});

// Express error handler (e.g. invalid JSON body)
// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  // eslint-disable-next-line no-console
  console.error('[astro-engine] middleware error', err);
  logLine('[astro-engine] middleware error', err && err.stack ? err.stack : String(err));

  const status = typeof err?.status === 'number' ? err.status : 500;
  if (DEBUG) {
    return res.status(status).json({
      error: 'request_error',
      message: err && typeof err === 'object' && 'message' in err ? String(err.message) : String(err),
    });
  }

  return res.status(status).json({ error: 'request_error' });
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
