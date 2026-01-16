/* eslint-disable no-console */

process.on('unhandledRejection', (reason) => {
  console.error('[astro-engine] unhandledRejection', reason);
  process.exitCode = 1;
});

process.on('uncaughtException', (err) => {
  console.error('[astro-engine] uncaughtException', err);
  process.exit(1);
});

(async () => {
  try {
    // Delegate to the normal ESM entrypoint.
    await import('./index.js');
  } catch (err) {
    console.error('[astro-engine] failed to boot', err);
    process.exit(1);
  }
})();
