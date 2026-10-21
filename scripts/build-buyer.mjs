import { readdirSync, rmSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { forceRequested, runCachedFrontend } from './frontend-cache.mjs';

const root = fileURLToPath(new URL('../', import.meta.url));
const entries = readdirSync(path.join(root, 'src/buyer/entries'))
  .filter((file) => /^[a-z][a-z0-9-]*\.ts$/.test(file) && !file.endsWith('.d.ts'))
  .map((file) => file.slice(0, -3))
  .sort((left, right) =>
    left === 'state' ? -1 : right === 'state' ? 1 : left.localeCompare(right),
  );
if (!entries.includes('state')) throw new Error('The shared buyer state entry is required.');

if (process.argv.slice(2).some((argument) => argument !== '--force')) {
  throw new Error('Usage: node scripts/build-buyer.mjs [--force]');
}

const result = runCachedFrontend({
  root,
  name: 'buyer-build',
  context: { entries },
  outputDirectories: ['assets/buyer/dist'],
  requiredOutputs: entries.map((entry) => `assets/buyer/dist/kiriminaja-buyer-${entry}.js`),
  force: forceRequested(),
  run() {
    // Only remove generated output once, never canonical CSS, images or legacy bridges.
    rmSync(path.join(root, 'assets/buyer/dist'), { recursive: true, force: true });
    const vite = path.join(root, 'node_modules/vite/bin/vite.js');
    for (const entry of entries) {
      const result = spawnSync(
        process.execPath,
        [vite, 'build', '--config', 'vite.buyer.config.ts', '--mode', entry],
        { cwd: root, stdio: 'inherit' },
      );
      if (result.error) throw result.error;
      if (result.status !== 0) return result.status ?? 1;
    }
    return 0;
  },
});
process.exitCode = result.status ?? 0;
