<?php

declare(strict_types=1);

/**
 * Build and cache the production Composer vendor tree.
 *
 * Usage: php package-production-vendor.php <source-root> <destination-vendor> <cache-root>
 */

if ($argc !== 4) {
    fwrite(STDERR, "Usage: package-production-vendor.php <source-root> <destination-vendor> <cache-root>\n");
    exit(2);
}

[$script, $sourceRoot, $destination, $cacheRoot] = $argv;
$sourceRoot = realpath($sourceRoot);
if ($sourceRoot === false || !is_file($sourceRoot . '/composer.json')) {
    fwrite(STDERR, "Composer source root is invalid.\n");
    exit(1);
}

if (!is_file($sourceRoot . "/composer.lock")) {
    fwrite(STDERR, "A composer.lock is required for reproducible packaging.\n");
    exit(1);
}

$composer = getenv('KIRIOF_COMPOSER') ?: 'composer';
$versionOutput = [];
exec(escapeshellarg($composer) . ' --version 2>&1', $versionOutput, $versionStatus);
if ($versionStatus !== 0) {
    fwrite(STDERR, "Unable to identify the Composer runtime.\n");
    exit(1);
}
$composerVersion = trim(implode("\n", $versionOutput));
$extensions = [];
foreach (get_loaded_extensions() as $extension) {
    $extensions[$extension] = phpversion($extension);
}
ksort($extensions);
$inputs = [
    'composer_json' => hash_file('sha256', $sourceRoot . '/composer.json'),
    'composer_lock' => is_file($sourceRoot . '/composer.lock') ? hash_file('sha256', $sourceRoot . '/composer.lock') : null,
    'php_binary' => PHP_BINARY,
    'php_binary_sha256' => hash_file('sha256', PHP_BINARY),
    'php_version' => PHP_VERSION,
    'php_ini' => ini_get_all(null, false),
    'platform' => PHP_OS_FAMILY . '/' . php_uname('m'),
    'extensions' => $extensions,
    'composer' => $composer,
    'composer_version' => $composerVersion,
    'helper' => hash_file('sha256', __FILE__),
];
$key = hash('sha256', json_encode($inputs, JSON_THROW_ON_ERROR));
$cache = rtrim($cacheRoot, '/\\') . '/' . $key;

if (!is_dir($cacheRoot) && !mkdir($cacheRoot, 0777, true) && !is_dir($cacheRoot)) {
    throw new RuntimeException("Unable to create production cache root.");
}
$lock = fopen($cacheRoot . "/.lock", "c");
if ($lock === false || !flock($lock, LOCK_EX)) {
    throw new RuntimeException("Unable to lock production cache.");
}

$valid = static function (string $vendor): bool {
    if (!is_file($vendor . "/autoload.php") || !is_file($vendor . "/kiriminaja/kiriminaja-php/src/Base/Api/Api.php")) {
        return false;
    }
    $probe = 'require $argv[1]; exit(class_exists("KiriminAja\\Base\\Api\\Api") ? 0 : 1);';
    exec(escapeshellarg(PHP_BINARY) . " -r " . escapeshellarg($probe) . " " . escapeshellarg($vendor . "/autoload.php") . " 2>&1", $output, $status);
    return $status === 0;
};

// Validate every cached payload file, not just the autoloader/SDK sentinel.
// Reject links so a cache cannot silently depend on files outside its tree.
$inventory = static function (string $vendor): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendor, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isLink() || !$file->isFile()) {
            throw new RuntimeException('Unexpected link or special file in production vendor tree.');
        }
        $relative = substr($file->getPathname(), strlen($vendor) + 1);
        $hash = hash_file('sha256', $file->getPathname());
        if ($hash === false) {
            throw new RuntimeException('Unable to hash production vendor file.');
        }
        $files[$relative] = $hash;
    }
    ksort($files);
    return $files;
};
$cacheValid = static function () use ($cache, $inputs, $inventory, $valid): bool {
    try {
        if (!is_file($cache . '/integrity.json') || !is_file($cache . '/inputs.json')) {
            return false;
        }
        $recordedInputs = json_decode(file_get_contents($cache . '/inputs.json'), true, 512, JSON_THROW_ON_ERROR);
        $recordedFiles = json_decode(file_get_contents($cache . '/integrity.json'), true, 512, JSON_THROW_ON_ERROR);
        return $recordedInputs === $inputs
            && $recordedFiles === $inventory($cache . '/vendor')
            && $valid($cache . '/vendor');
    } catch (Throwable $error) {
        return false;
    }
};

if (!$cacheValid()) {
    $temporary = $cacheRoot . '/.tmp-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700, true)) {
        fwrite(STDERR, "Unable to create temporary Composer directory.\n");
        exit(1);
    }

    $staging = null;
    try {
        copy($sourceRoot . '/composer.json', $temporary . '/composer.json');
        if (is_file($sourceRoot . '/composer.lock')) {
            copy($sourceRoot . '/composer.lock', $temporary . '/composer.lock');
        }

        $run = static function (array $arguments) use ($composer, $temporary): void {
            $command = array_merge([$composer], $arguments, ['--working-dir=' . $temporary]);
            $escaped = implode(' ', array_map('escapeshellarg', $command));
            passthru($escaped, $status);
            if ($status !== 0) {
                throw new RuntimeException('Composer production install failed.');
            }
        };

        // Dist is faster and reproducible for normal dependencies. The SDK is
        // deliberately installed from source because its archive can omit src/.
        $manifest = json_decode(file_get_contents($temporary . "/composer.json"), true, 512, JSON_THROW_ON_ERROR);
        $manifest["config"]["preferred-install"] = ["kiriminaja/kiriminaja-php" => "source", "*" => "dist"];
        file_put_contents($temporary . "/composer.json", json_encode($manifest, JSON_THROW_ON_ERROR));
        $run(['install', '--no-dev', '--optimize-autoloader', '--no-interaction']);

        if (!$valid($temporary . '/vendor')) {
            throw new RuntimeException('Composer produced an incomplete production vendor tree.');
        }

        // Trim once, before caching/copying. In particular, never copy the SDK
        // Git checkout into every package just to immediately delete it again.
        $prune = 'find ' . escapeshellarg($temporary . '/vendor')
            . ' -type d \\( -name .git -o -name .github -o -name docs -o -name example -o -name tests -o -name vendor-bin \\) -prune -exec rm -rf {} +';
        passthru($prune, $pruneStatus);
        if ($pruneStatus !== 0) {
            throw new RuntimeException('Unable to prune production vendor directories.');
        }
        $prune = 'find ' . escapeshellarg($temporary . '/vendor')
            . ' -type f \\( -name CHANGELOG.md -o -name README.md -o -name UPGRADING.md -o -name composer.lock \\) -delete';
        passthru($prune, $pruneStatus);
        if ($pruneStatus !== 0) {
            throw new RuntimeException('Unable to prune production vendor files.');
        }

        $staging = $cacheRoot . '/.cache-' . bin2hex(random_bytes(8));
        mkdir($staging . '/vendor', 0700, true);
        if (!rename($temporary . '/vendor', $staging . '/vendor')) {
            throw new RuntimeException('Unable to stage the production vendor cache.');
        }
        file_put_contents($staging . '/inputs.json', json_encode($inputs, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        file_put_contents($staging . '/integrity.json', json_encode($inventory($staging . '/vendor'), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        if (is_dir($cache)) {
            exec("rm -rf " . escapeshellarg($cache), $output, $status);
            if ($status !== 0) {
                throw new RuntimeException("Unable to remove invalid production cache.");
            }
        }
        if (!rename($staging, $cache)) {
            throw new RuntimeException('Unable to publish the production vendor cache.');
        }
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        $failed = true;
    } finally {
        if (is_dir($temporary)) {
            shell_exec('rm -rf ' . escapeshellarg($temporary));
        }
        if ($staging !== null && is_dir($staging)) {
            shell_exec("rm -rf " . escapeshellarg($staging));
        }
    }
    if (isset($failed)) {
        exit(1);
    }
} else {
    echo "Reusing production Composer vendor cache.\n";
}

if (is_dir($destination)) {
    shell_exec('rm -rf ' . escapeshellarg($destination));
}
if (!mkdir($destination, 0777, true) && !is_dir($destination)) {
    fwrite(STDERR, "Unable to create package vendor destination.\n");
    exit(1);
}
$command = 'cp -a ' . escapeshellarg($cache . '/vendor/.') . ' ' . escapeshellarg($destination);
passthru($command, $status);
// Compare to the recorded inventory already verified above, avoiding another
// complete read of the cache tree on every warm package.
$expectedFiles = json_decode(file_get_contents($cache . '/integrity.json'), true, 512, JSON_THROW_ON_ERROR);
if ($status !== 0 || $inventory($destination) !== $expectedFiles || !$valid($destination)) {
    fwrite(STDERR, "Copied production vendor tree is incomplete; refusing to package it.\n");
    exit(1);
}
