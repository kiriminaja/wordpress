import { createHash, randomUUID } from 'node:crypto';
import {
  lstatSync,
  mkdirSync,
  readFileSync,
  readlinkSync,
  readdirSync,
  realpathSync,
  renameSync,
  rmSync,
  writeFileSync,
} from 'node:fs';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dependencyInventory } from './frontend-cache.mjs';

const schema = 1;
const hash = (value) => createHash('sha256').update(value).digest('hex');
const ignored = new Set([
  '.git',
  'node_modules',
  'vendor',
  'build',
  'coverage',
  '.cache',
  '.svelte-kit',
  '.vite',
  '.paratest',
  '.paratest.cache',
  '.phpunit.cache',
  'playwright-report',
  'test-results',
  '.idea',
  '.vscode',
  'composer-vendor-cache',
]);
const generated = ['assets/admin/dist', 'assets/buyer/dist'];
const bookkeeping = (name) =>
  [
    '_',
    'SHLVL',
    'KIRIOF_TEST_FORCE',
    'KIRIOF_BUILD_FORCE',
    'npm_lifecycle_event',
    'npm_lifecycle_script',
    'npm_command',
  ].includes(name);
export const normalizedEnvironment = (env) =>
  Object.fromEntries(
    Object.keys(env)
      .filter((name) => !bookkeeping(name))
      .sort()
      .map((name) => [name, env[name]]),
  );

// No stat/mtime cache: every admitted input is read on every lookup and again
// before publication. Symlinks and special files fail closed, never disappear.
function inventory(root, relative, records, mode = 'complete', entry) {
  const absolute = path.join(root, relative);
  let stat;
  try {
    stat = entry ?? lstatSync(absolute);
  } catch (error) {
    if (error.code !== 'ENOENT') throw error;
    records.push([relative, 'missing']);
    return;
  }

  if (stat.isSymbolicLink()) throw new Error(`Uncacheable symlink: ${relative}`);
  if (stat.isDirectory()) {
    const name = path.basename(relative);
    if (
      mode === 'source' &&
      (ignored.has(name) ||
        name.startsWith('.e2e') ||
        name.startsWith('.paratest') ||
        generated.includes(relative))
    )
      return;
    if (mode === 'package' && ['node_modules', '.git', '.cache'].includes(name)) return;
    records.push([relative, 'directory']);
    for (const child of readdirSync(absolute, { withFileTypes: true }).sort((left, right) =>
      left.name < right.name ? -1 : left.name > right.name ? 1 : 0,
    )) {
      if (
        mode === 'source' &&
        (child.name === '.DS_Store' || /\.(?:zip|log|tsbuildinfo)$/i.test(child.name))
      )
        continue;
      inventory(root, relative ? `${relative}/${child.name}` : child.name, records, mode, child);
    }
  } else if (stat.isFile()) records.push([relative, hash(readFileSync(absolute))]);
  else throw new Error(`Uncacheable special file: ${relative}`);
}

function launcherInventory(root) {
  const directory = path.join(root, 'node_modules/.bin');
  let entries;
  try {
    entries = readdirSync(directory).sort();
  } catch (error) {
    if (error.code === 'ENOENT') return [['node_modules/.bin', 'missing']];
    throw error;
  }
  return entries.map((name) => {
    const launcher = path.join(directory, name);
    const stat = lstatSync(launcher);
    const target = realpathSync(launcher);
    if (!target.startsWith(`${root}${path.sep}`)) throw new Error(`External launcher: ${name}`);
    if (!lstatSync(target).isFile()) throw new Error(`Invalid launcher: ${name}`);
    return [
      name,
      stat.isSymbolicLink() ? readlinkSync(launcher) : 'file',
      path.relative(root, target),
      hash(readFileSync(target)),
    ];
  });
}

function executable(root, command, env) {
  const candidates = command.includes('/')
    ? [path.resolve(root, command)]
    : (env.PATH ?? '')
        .split(path.delimiter)
        .map((directory) => path.resolve(root, directory, command));
  for (const candidate of candidates) {
    try {
      if (lstatSync(candidate).isFile() || lstatSync(candidate).isSymbolicLink())
        return realpathSync(candidate);
    } catch {
      /* Try next PATH entry. */
    }
  }
  throw new Error(`Executable unavailable: ${command}`);
}
function binaryIdentity(binary, env, root) {
  const result = spawnSync(binary, ['--version'], {
    cwd: root,
    env,
    encoding: 'utf8',
    timeout: 10000,
  });
  if (result.error || result.status !== 0 || !result.stdout.trim())
    throw new Error(`Runtime probe failed: ${binary}`);
  return [binary, hash(readFileSync(binary)), result.stdout, result.stderr];
}
export function defaultRuntimeProbe(root, command, env) {
  const helper = path.join(root, 'scripts/test-cache.php');
  const php = executable(root, 'php', env);
  const result = spawnSync(php, [helper, '--fingerprint-only'], {
    cwd: root,
    env: normalizedEnvironment(env),
    encoding: 'utf8',
    timeout: 10000,
  });
  if (result.error || result.status !== 0)
    throw new Error('Native PHP runtime fingerprint unavailable');
  const fingerprint = JSON.parse(result.stdout);
  if (
    fingerprint.schema !== 1 ||
    !['binary', 'extensions', 'ini', 'locale', 'environment'].every((key) =>
      /^[a-f0-9]{64}$/.test(fingerprint[key]),
    )
  )
    throw new Error('Invalid PHP runtime fingerprint');
  return {
    node: [
      binaryIdentity(process.execPath, env, root),
      process.versions,
      process.platform,
      process.arch,
    ],
    command: binaryIdentity(executable(root, command[0], env), env, root),
    php: fingerprint,
  };
}
export function testInputKey(
  root,
  command,
  { environment = process.env, runtimeProbe = defaultRuntimeProbe } = {},
) {
  root = realpathSync(root);
  const sources = [],
    outputs = [],
    dependencies = [];
  inventory(root, '', sources, 'source');
  for (const directory of [...generated, 'vendor']) inventory(root, directory, outputs);
  const manifests = dependencyInventory(root);
  for (const [manifest] of manifests) {
    if (!manifest.endsWith('/package.json')) continue;
    // dependencyInventory resolves exports-hidden manifests. Reject linked package
    // roots/ancestors as well: resolving a link must not hide its input identity.
    if (path.isAbsolute(manifest) || manifest.split('/').includes('..'))
      throw new Error(`Dependency outside root: ${manifest}`);
    let current = path.dirname(manifest);
    while (current && current !== '.') {
      if (lstatSync(path.join(root, current)).isSymbolicLink())
        throw new Error(`Linked dependency: ${current}`);
      current = path.dirname(current);
    }
    inventory(root, path.dirname(manifest), dependencies, 'package');
  }
  return hash(
    JSON.stringify({
      schema,
      command,
      environment: normalizedEnvironment(environment),
      runtime: runtimeProbe(root, command, environment),
      sources,
      outputs,
      manifests,
      launchers: launcherInventory(root),
      dependencies,
    }),
  );
}
export function uncacheableCommand(command) {
  return command.some(
    (arg) =>
      /^--(?:watch|hot|coverage(?:-.+)?|reporter(?:-.+)?|report(?:-.+)?|output(?:-.+)?|junit(?:-.+)?|update(?:-.+)?|snapshot(?:-.+)?|randomize|seed|rerun-each|retry)(?:=|$)/i.test(
        arg,
      ) ||
      ['-w', '-u'].includes(arg) ||
      /^-[^-]*[uw]/.test(arg),
  );
}
export const forceRequested = (args = [], env = process.env) =>
  args.includes('--force') || env.KIRIOF_TEST_FORCE === '1' || env.KIRIOF_BUILD_FORCE === '1';

async function lockDirectory(lock, waitMs) {
  const deadline = Date.now() + waitMs;
  while (true) {
    try {
      mkdirSync(lock);
      try {
        const identity = spawnSync('ps', ['-p', String(process.pid), '-o', 'lstart='], {
          encoding: 'utf8',
        });
        writeFileSync(
          path.join(lock, 'owner.json'),
          JSON.stringify({
            pid: process.pid,
            identity: identity.stdout?.trim() || 'unknown',
            token: randomUUID(),
          }),
        );
      } catch (error) {
        rmSync(lock, { recursive: true, force: true });
        throw error;
      }
      return;
    } catch (error) {
      if (error.code !== 'EEXIST') throw error;
      // Never infer death from age or PID alone (PID reuse). An interrupted
      // owner's directory needs explicit cleanup; live owners are waited for.
      // This is intentionally fail-closed even when owner metadata is missing.
      if (Date.now() >= deadline) {
        const busy = new Error(
          `Test cache lock busy or abandoned; verify owner before removing ${lock}`,
        );
        busy.exitCode = 75;
        throw busy;
      }
      await new Promise((resolve) =>
        setTimeout(resolve, Math.min(50, Math.max(1, deadline - Date.now()))),
      );
    }
  }
}
function execute(root, command, environment) {
  return new Promise((resolve, reject) => {
    const child = spawn(command[0], command.slice(1), {
      cwd: root,
      env: environment,
      stdio: 'inherit',
    });
    const forward = (signal) => child.kill(signal);
    const interrupt = () => forward('SIGINT'),
      terminate = () => forward('SIGTERM');
    process.on('SIGINT', interrupt);
    process.on('SIGTERM', terminate);
    const cleanup = () => {
      process.off('SIGINT', interrupt);
      process.off('SIGTERM', terminate);
    };
    child.once('error', (error) => {
      cleanup();
      reject(error);
    });
    child.once('exit', (status, signal) => {
      cleanup();
      resolve({ status: status ?? 1, signal });
    });
  });
}

/** General command-array wrapper; injected runners/probes are for offline fixtures. */
export async function runCachedTests(
  root,
  command,
  {
    force = false,
    environment = process.env,
    runtimeProbe = defaultRuntimeProbe,
    run = execute,
    log = (message) => process.stdout.write(`${message}\n`),
    lockWaitMs = 30000,
  } = {},
) {
  if (
    !Array.isArray(command) ||
    !command.length ||
    command.some((arg) => typeof arg !== 'string') ||
    !command[0]
  )
    throw new Error('Expected a nonempty command array');
  root = path.resolve(root);
  const directory = path.join(root, 'node_modules/.cache/kiriof/tests');
  mkdirSync(directory, { recursive: true });
  const receipt = path.join(directory, `${hash(JSON.stringify(command))}.json`),
    lock = `${receipt}.lock`;
  await lockDirectory(lock, lockWaitMs);
  let temporary;
  try {
    const bypass = uncacheableCommand(command);
    let key;
    if (!bypass)
      try {
        key = testInputKey(root, command, { environment, runtimeProbe });
      } catch (error) {
        log(`[tests] cache unavailable: ${error.message}`);
      }
    if (!force && !forceRequested([], environment) && !bypass && key) {
      try {
        const saved = JSON.parse(readFileSync(receipt, 'utf8'));
        if (
          Object.keys(saved).sort().join(',') === 'command,key,schema,status' &&
          saved.schema === schema &&
          saved.status === 0 &&
          saved.key === key &&
          JSON.stringify(saved.command) === JSON.stringify(command)
        ) {
          log(`[tests] cached success: ${command.join(' ')}`);
          return { cached: true, status: 0 };
        }
      } catch {
        /* Corrupt/missing receipts are misses. */
      }
    }
    rmSync(receipt, { force: true });
    const executed = await run(root, command, environment);
    const result = typeof executed === 'number' ? { status: executed } : executed;
    if (result.status !== 0 || result.signal)
      return {
        cached: false,
        status: result.status ?? 1,
        ...(result.signal ? { signal: result.signal } : {}),
      };
    if (key)
      try {
        if (key !== testInputKey(root, command, { environment, runtimeProbe }))
          throw new Error('Inputs changed during execution');
        temporary = `${receipt}.${process.pid}.${randomUUID()}.tmp`;
        writeFileSync(temporary, JSON.stringify({ schema, command, key, status: 0 }), {
          flag: 'wx',
        });
        renameSync(temporary, receipt);
      } catch (error) {
        log(`[tests] result not cached: ${error.message}`);
      }
    return { cached: false, status: 0 };
  } finally {
    if (temporary) rmSync(temporary, { force: true });
    rmSync(lock, { recursive: true, force: true });
  }
}

if (process.argv[1] && realpathSync(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const args = process.argv.slice(2),
    separator = args.indexOf('--');
  if (
    separator < 0 ||
    separator === args.length - 1 ||
    args.slice(0, separator).some((arg) => arg !== '--force')
  ) {
    process.stderr.write('Usage: node scripts/test-cache.mjs [--force] -- <command> [args...]\n');
    process.exitCode = 2;
  } else {
    try {
      const result = await runCachedTests(
        fileURLToPath(new URL('../', import.meta.url)),
        args.slice(separator + 1),
        { force: forceRequested(args.slice(0, separator)) },
      );
      if (result.signal) process.kill(process.pid, result.signal);
      else process.exitCode = result.status;
    } catch (error) {
      process.stderr.write(`[tests] ${error.message}\n`);
      process.exitCode = error.exitCode ?? 1;
    }
  }
}
