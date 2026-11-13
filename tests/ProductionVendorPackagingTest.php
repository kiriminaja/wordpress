<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Offline coverage of cache reuse, invalidation, and production install policy. */
final class ProductionVendorPackagingTest extends TestCase
{
    public function test_exact_phast_inputs_and_full_payload_integrity_control_cache_reuse(): void
    {
        $root = sys_get_temp_dir() . '/kiriof-vendor-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $phast = $root . '/phast';
        file_put_contents($phast, '#!' . PHP_BINARY . "\n" . <<<'PHP'
<?php
if (in_array('--version', $argv, true)) {
    echo "phast 0.1.0\n";
    exit(0);
}
$expected = ['install', '--no-dev', '--no-scripts'];
$project = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--project-dir=')) {
        $project = substr($argument, 14);
    }
}
if ($project === null || array_slice($argv, 1, 3) !== $expected
    || count($argv) !== 5 || !is_file($project . '/phast.toml') || !is_file($project . '/phast.lock')
    || is_file($project . '/composer.json') || is_file($project . '/composer.lock')) {
    exit(2);
}
if (hash_file('sha256', $project . '/phast.toml') !== hash_file('sha256', __DIR__ . '/phast.toml')
    || hash_file('sha256', $project . '/phast.lock') !== hash_file('sha256', __DIR__ . '/phast.lock')
    || $project === __DIR__) {
    exit(3);
}
file_put_contents(__DIR__ . '/installs', $project . "\n", FILE_APPEND);
mkdir($project . '/vendor/kiriminaja/kiriminaja-php/src/Base/Api', 0700, true);
mkdir($project . '/vendor/kiriminaja/kiriminaja-php/.git', 0700, true);
foreach (['docs', 'tests', '.github', 'vendor-bin'] as $directory) {
    mkdir($project . '/vendor/kiriminaja/kiriminaja-php/' . $directory, 0700, true);
    file_put_contents($project . '/vendor/kiriminaja/kiriminaja-php/' . $directory . '/discard.php', '<?php // discard');
}
file_put_contents($project . '/vendor/kiriminaja/kiriminaja-php/.git/config', 'discard');
file_put_contents($project . '/vendor/kiriminaja/kiriminaja-php/README.md', 'discard');
file_put_contents($project . '/vendor/kiriminaja/kiriminaja-php/src/Base/Api/Api.php', '<?php namespace KiriminAja\\Base\\Api; class Api {}');
file_put_contents($project . '/vendor/autoload.php', '<?php require __DIR__ . "/kiriminaja/kiriminaja-php/src/Base/Api/Api.php";');
file_put_contents($project . '/vendor/other-runtime.php', '<?php // Must be integrity-checked too.');
PHP
        );
        chmod($phast, 0700);
        file_put_contents($root . '/phast.toml', "[package]\nname = \"fixture\"\n[require]\nphp = \">=8.2.35\"\n");
        file_put_contents($root . '/phast.lock', 'fixture lock');

        $run = function () use ($root, $phast): void {
            $command = 'KIRIOF_PHAST=' . escapeshellarg($phast) . ' '
                . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/scripts/package-production-vendor.php')
                . ' ' . escapeshellarg($root) . ' ' . escapeshellarg($root . '/package/vendor')
                . ' ' . escapeshellarg($root . '/cache') . ' 2>&1';
            exec($command, $output, $status);
            $this->assertSame(0, $status, implode("\n", $output));
        };
        $installCount = static fn (): int => count(file($root . '/installs'));
        try {
            $run();
            $this->assertSame(1, $installCount());
            $this->assertFileExists($root . '/package/vendor/autoload.php');
            $this->assertFileExists($root . '/package/vendor/kiriminaja/kiriminaja-php/src/Base/Api/Api.php');
            $this->assertFileDoesNotExist($root . '/package/vendor/kiriminaja/kiriminaja-php/README.md');
            $this->assertDirectoryDoesNotExist($root . '/package/vendor/kiriminaja/kiriminaja-php/.git');
            foreach (['docs', 'tests', '.github', 'vendor-bin'] as $directory) {
                $this->assertDirectoryDoesNotExist($root . '/package/vendor/kiriminaja/kiriminaja-php/' . $directory);
            }
            $run();
            $this->assertSame(1, $installCount(), 'An unchanged package must not reinstall dependencies.');

            $cachedVendor = glob($root . '/cache/*/vendor')[0];
            file_put_contents($cachedVendor . '/other-runtime.php', 'corrupt');
            $run();
            $this->assertSame(2, $installCount(), 'Corruption outside the SDK/autoloader must invalidate the cache.');
            unlink($cachedVendor . '/other-runtime.php');
            $run();
            $this->assertSame(3, $installCount(), 'Deleted runtime files must invalidate the cache.');
            file_put_contents($root . '/phast.lock', 'changed lock');
            $run();
            $this->assertSame(4, $installCount(), 'Exact lock bytes must invalidate the cache.');
            file_put_contents($root . '/phast.toml', "[package]\nname = \"changed\"\n");
            $run();
            $this->assertSame(5, $installCount(), 'Exact manifest bytes must invalidate the cache.');
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    public function test_changed_phast_lock_fails_packaging(): void
    {
        $root = sys_get_temp_dir() . '/kiriof-lock-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $phast = $root . '/phast';
        file_put_contents($phast, <<<'SH'
#!/bin/sh
if [ "$1" = "--version" ]; then echo phast; exit 0; fi
project="${4#--project-dir=}"
printf changed > "$project/phast.lock"
SH
        );
        chmod($phast, 0700);
        file_put_contents($root . '/phast.toml', '[package]');
        file_put_contents($root . '/phast.lock', 'original');
        $command = 'KIRIOF_PHAST=' . escapeshellarg($phast) . ' ' . escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(dirname(__DIR__) . '/scripts/package-production-vendor.php') . ' ' . escapeshellarg($root) . ' '
            . escapeshellarg($root . '/package/vendor') . ' ' . escapeshellarg($root . '/cache') . ' 2>&1';
        try {
            exec($command, $output, $status);
            $this->assertNotSame(0, $status);
            $this->assertStringContainsString('phast.lock', implode("\n", $output));
            $this->assertSame('original', file_get_contents($root . '/phast.lock'));
            $this->assertDirectoryDoesNotExist($root . '/package/vendor');
            $this->assertSame([], glob($root . '/cache/*/integrity.json'));
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }
}
