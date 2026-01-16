/* eslint-disable no-console */

const fs = require('fs');
const path = require('path');

function logLine(...parts) {
  const line = `[${new Date().toISOString()}] ${parts.map(String).join(' ')}\n`;

  try {
    fs.appendFileSync(path.join(process.cwd(), '.astro-engine.log'), line, 'utf8');
    return;
  } catch {
    // ignore
  }

  try {
    const home = process.env.HOME || process.env.USERPROFILE;
    if (home) {
      fs.appendFileSync(path.join(home, '.astro-engine.log'), line, 'utf8');
    }
  } catch {
    // ignore
  }
}

process.on('unhandledRejection', (reason) => {
  console.error('[astro-engine] unhandledRejection', reason);
  logLine('[astro-engine] unhandledRejection', reason && reason.stack ? reason.stack : String(reason));
  process.exitCode = 1;
});

process.on('uncaughtException', (err) => {
  console.error('[astro-engine] uncaughtException', err);
  logLine('[astro-engine] uncaughtException', err && err.stack ? err.stack : String(err));
  process.exit(1);
});

logLine('[astro-engine] boot start', `cwd=${process.cwd()}`, `node=${process.version}`);

(async () => {
  try {
    // Delegate to the normal ESM entrypoint.
    await import('./index.js');
    logLine('[astro-engine] boot ok');
  } catch (err) {
    console.error('[astro-engine] failed to boot', err);
    logLine('[astro-engine] failed to boot', err && err.stack ? err.stack : String(err));
    process.exit(1);
  }
})();
