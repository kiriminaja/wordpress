import { createHash } from 'node:crypto';
import {
  existsSync,
  lstatSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  realpathSync,
  renameSync,
  rmSync,
  writeFileSync,
} from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const schema = 1;
const ignoredDirectories = new Set([
  '.git',
  'node_modules',
  'vendor',
  'build',
  'coverage',
  '.svelte-kit',
  '.vite',
  '.paratest.cache',
  '.phpunit.cache',
  'playwright-report',
  'test-results',
  '.idea',
  '.vscode',
]);
const generatedDirectories = new Set(['assets/buyer/dist', 'assets/admin/dist']);
const digest = (value) => createHash('sha256').update(value).digest('hex');
const slash = (value) => value.split(path.sep).join('/');

// Deliberately conservative: Tailwind's automatic source discovery can read PHP,
// templates, canonical JS/CSS, tests, and other project files, not just imports.
// Never descend into dependencies or generated output. File bytes, not mtimes,
// determine identity, including added/removed files.
export function sourceInventory(root) {
  const records = [];
  function visit(relative) {
    const absolute = path.join(root, relative);
    const stat = lstatSync(absolute);
    if (stat.isSymbolicLink()) throw new Error(`Uncacheable source symlink: ${relative}`);
    if (stat.isDirectory()) {
      if (ignoredDirectories.has(path.basename(relative)) || generatedDirectories.has(relative)) {
        return;
      }
      for (const name of readdirSync(absolute).sort()) {
        if (name === '.DS_Store' || name === 'debug.log' || /\.(zip|tsbuildinfo)$/.test(name)) {
          continue;
        }
        visit(relative ? `${relative}/${name}` : name);
      }
    } else if (stat.isFile()) {
      records.push([relative, digest(readFileSync(absolute))]);
    } else {
      throw new Error(`Uncacheable source file: ${relative}`);
    }
  }
  visit('');
  return records;
}

function resolvePackage(root, from, name) {
  // Resolve package.json without relying on package exports; many packages hide it.
  let current = from;
  while (true) {
    const candidate = path.join(current, 'node_modules', name, 'package.json');
    if (existsSync(candidate)) return realpathSync(candidate);
    const parent = path.dirname(current);
    if (parent === current || (current === root && from.startsWith(root))) return null;
    current = parent;
  }
}

export function dependencyInventory(root) {
  const records = [];
  const visited = new Set();
  const project = JSON.parse(readFileSync(path.join(root, 'package.json'), 'utf8'));
  function visit(name, from, optional = false) {
    const manifest = resolvePackage(root, from, name);
    if (!manifest) {
      if (!optional) throw new Error(`Required frontend dependency is missing: ${name}`);
      records.push([slash(path.relative(root, from)), name, 'absent-optional']);
      return;
    }
    if (visited.has(manifest)) return;
    visited.add(manifest);
    const bytes = readFileSync(manifest);
    const metadata = JSON.parse(bytes.toString('utf8'));
    if (!metadata.name || !metadata.version) throw new Error(`Invalid dependency: ${manifest}`);
    records.push([slash(path.relative(root, manifest)), digest(bytes)]);
    const optionalDependencies = metadata.optionalDependencies ?? {};
    const dependencies = { ...metadata.dependencies, ...optionalDependencies };
    for (const child of Object.keys(dependencies).sort()) {
      visit(child, path.dirname(manifest), Object.hasOwn(optionalDependencies, child));
    }
    for (const peer of Object.keys(metadata.peerDependencies ?? {}).sort()) {
      visit(peer, path.dirname(manifest), metadata.peerDependenciesMeta?.[peer]?.optional === true);
    }
  }
  for (const name of Object.keys({ ...project.dependencies, ...project.devDependencies }).sort()) {
    visit(name, root);
  }
  for (const name of Object.keys(project.optionalDependencies ?? {}).sort()) {
    visit(name, root, true);
  }
  return records.sort((left, right) => JSON.stringify(left).localeCompare(JSON.stringify(right)));
}

export function frontendInputKey(root, context = {}, environment = process.env) {
  // Arbitrary configuration may consume environment variables. Hash values without
  // persisting secrets. Only invocation bookkeeping and the force switch are omitted.
  const env = Object.keys(environment)
    .filter((name) => !['_', 'SHLVL', 'KIRIOF_BUILD_FORCE'].includes(name))
    .sort()
    .map((name) => [name, environment[name]]);
  return digest(
    JSON.stringify({
      schema,
      context,
      runtime: [
        process.execPath,
        process.version,
        process.versions,
        process.platform,
        process.arch,
      ],
      env,
      sources: sourceInventory(root),
      dependencies: dependencyInventory(root),
    }),
  );
}

export function outputInventory(root, directories) {
  const records = [];
  function visit(relative) {
    const absolute = path.join(root, relative);
    const stat = lstatSync(absolute);
    if (stat.isDirectory()) {
      records.push([relative, 'directory']);
      for (const name of readdirSync(absolute).sort()) visit(`${relative}/${name}`);
    } else if (stat.isFile()) {
      records.push([relative, digest(readFileSync(absolute))]);
    } else {
      throw new Error(`Uncacheable generated file: ${relative}`);
    }
  }
  for (const directory of directories) visit(directory);
  return records;
}

export const forceRequested = (args = process.argv.slice(2), env = process.env) =>
  args.includes('--force') || env.KIRIOF_BUILD_FORCE === '1';

/** A cache stores successful receipts, never artifacts to restore or timestamps. */
export function runCachedFrontend({
  root,
  name,
  context = {},
  outputDirectories,
  requiredOutputs = [],
  force = false,
  run,
  log = (message) => process.stdout.write(`${message}\n`),
}) {
  if (!/^[a-z][a-z0-9-]*$/.test(name)) throw new Error('Invalid frontend cache name');
  const cacheDirectory = path.join(root, 'node_modules/.cache/kiriof');
  mkdirSync(cacheDirectory, { recursive: true });
  const receipt = path.join(cacheDirectory, `${name}.json`);
  const lock = path.join(cacheDirectory, `${name}.lock`);
  // Do not race another orchestrator's clean/build/receipt publication. An
  // interrupted process can leave a lock; explicitly remove it after checking
  // no build is running. Never guess that a live build's lock is stale.
  try {
    mkdirSync(lock);
  } catch (error) {
    if (error.code === 'EEXIST') {
      throw new Error(`Frontend ${name} is locked; if no build is running, remove ${lock}`);
    }
    throw error;
  }
  let temporary;
  try {
    let key;
    try {
      key = frontendInputKey(root, context);
    } catch (error) {
      log(`[${name}] cache unavailable: ${error.message}`);
    }
    if (!force && key) {
      try {
        const saved = JSON.parse(readFileSync(receipt, 'utf8'));
        const outputs = outputInventory(root, outputDirectories);
        if (
          saved.schema === schema &&
          saved.key === key &&
          requiredOutputs.every((file) =>
            outputs.some(([name, hash]) => name === file && hash !== 'directory'),
          ) &&
          JSON.stringify(saved.outputs) === JSON.stringify(outputs)
        ) {
          log(`[${name}] unchanged inputs and verified outputs; cache hit`);
          return { cached: true };
        }
      } catch {
        // Missing, malformed or unreadable receipts/outputs are cache misses.
      }
    }
    rmSync(receipt, { force: true });
    const status = run();
    if (status !== 0) return { cached: false, status: status ?? 1 };
    if (key) {
      try {
        const outputs = outputInventory(root, outputDirectories);
        for (const file of requiredOutputs) {
          if (!outputs.some(([name, hash]) => name === file && hash !== 'directory')) {
            throw new Error(`Expected generated output is missing: ${file}`);
          }
        }
        // An edit during a build must not certify a mixed-input output set.
        if (frontendInputKey(root, context) !== key) {
          log(`[${name}] inputs changed during execution; result not cached`);
          return { cached: false, status: 0 };
        }
        temporary = `${receipt}.${process.pid}.tmp`;
        writeFileSync(temporary, JSON.stringify({ schema, key, outputs }));
        renameSync(temporary, receipt);
      } catch (error) {
        log(`[${name}] result not cached: ${error.message}`);
      }
    }
    return { cached: false, status: 0 };
  } finally {
    if (temporary) rmSync(temporary, { force: true });
    rmSync(lock, { recursive: true, force: true });
  }
}

// Optional validation wrapper, intentionally not wired into Makefile/package.json:
// node scripts/frontend-cache.mjs [--force] -- bun run frontend:check
// Successful validation is tied to the complete generated buyer inventory too.
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const args = process.argv.slice(2);
  const separator = args.indexOf('--');
  const command = args.slice(separator + 1);
  if (
    separator < 0 ||
    !command.length ||
    args.slice(0, separator).some((arg) => arg !== '--force')
  ) {
    throw new Error('Usage: node scripts/frontend-cache.mjs [--force] -- <command> [args...]');
  }
  const root = fileURLToPath(new URL('../', import.meta.url));
  const result = runCachedFrontend({
    root,
    name: 'frontend-validation',
    context: { command },
    outputDirectories: ['assets/buyer/dist'],
    force: forceRequested(args.slice(0, separator)),
    run() {
      const result = spawnSync(command[0], command.slice(1), { cwd: root, stdio: 'inherit' });
      if (result.error) throw result.error;
      return result.status ?? 1;
    },
  });
  process.exitCode = result.status ?? 0;
}
