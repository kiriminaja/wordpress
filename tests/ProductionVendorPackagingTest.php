<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Offline coverage of cache reuse, invalidation, and production install policy. */
final class ProductionVendorPackagingTest extends TestCase
{
    public function test_package_uses_composer_and_coexists_with_an_existing_composer_loader(): void
    {
        $vendor = PLUGIN_DIR . '/build/' . PLUGIN_SLUG . '/vendor';
        $this->assertFileExists($vendor . '/composer/ClassLoader.php');
        $this->assertDirectoryDoesNotExist($vendor . '/phast');
        $classmap = require $vendor . '/composer/autoload_classmap.php';
        $this->assertArrayNotHasKey('Http\\Discovery\\Composer\\Plugin', $classmap);
        $probe = <<<'PHP'
$first = require $argv[1];
$before = \Composer\InstalledVersions::getRawData();
$second = require $argv[2];
if ($first === $second || $before !== \Composer\InstalledVersions::getRawData()) { exit(2); }
if ($second !== (require $argv[2])) { exit(3); }
if (!class_exists('KiriminAja\Base\Api\Api')) { exit(4); }
$file = $second->findFile('KiriminAjaOfficial\Services\InstantDispatchService');
if (realpath($file) !== realpath(dirname($argv[2], 2) . '/inc/Services/InstantDispatchService.php')) { exit(5); }
echo 'OK';
PHP;
        exec(kiriof_test_fixture_php_shell(array('-r', $probe, PLUGIN_DIR . '/vendor/autoload.php', $vendor . '/autoload.php')) . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        $this->assertSame('OK', implode("\n", $output));
    }

    public function test_preloaded_phast_symbols_cannot_redeclare_the_packaged_loader(): void
    {
        $probe = <<<'PHP'
namespace Phast\Autoload {
    class ClassLoader {}
    function includeFile($file) { throw new \RuntimeException('Foreign loader must not be called.'); }
}
namespace Phast { class InstalledVersions {} }
namespace {
    $loader = require $argv[1];
    if (!$loader instanceof \Composer\Autoload\ClassLoader) { exit(2); }
    if (!class_exists('KiriminAja\Base\Api\Api')) { exit(3); }
    echo 'OK';
}
PHP;
        exec(kiriof_test_fixture_php_shell(array('-r', $probe, PLUGIN_DIR . '/build/' . PLUGIN_SLUG . '/vendor/autoload.php')) . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        $this->assertSame('OK', implode("\n", $output));
    }

    public function test_exact_composer_inputs_and_full_payload_integrity_control_cache_reuse(): void
    {
        $root = sys_get_temp_dir() . '/kiriof-vendor-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $composer = $root . '/composer';
        file_put_contents($composer, '#!' . PHP_BINARY . "\n" . <<<'PHP'
<?php
if (in_array('--version', $argv, true)) {
    echo "composer 0.1.0\n";
    exit(0);
}
$expected = ['install', '--no-dev', '--no-scripts', '--no-plugins', '--no-interaction', '--no-progress', '--prefer-dist', '--optimize-autoloader'];
$project = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--working-dir=')) {
        $project = substr($argument, 14);
    }
}
if ($project === null || array_slice($argv, 1, count($expected)) !== $expected
    || count($argv) !== 10 || !is_file($project . '/composer.json') || !is_file($project . '/composer.lock')) {
    exit(2);
}
if (hash_file('sha256', $project . '/composer.lock') !== hash_file('sha256', __DIR__ . '/composer.lock')
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
        chmod($composer, 0700);
            file_put_contents($root . '/composer.json', '{"name":"fixture/changed","require":{"php":">=8.2.35"}}');
        file_put_contents($root . '/composer.lock', 'fixture lock');

        $run = function () use ($root, $composer): void {
            $command = 'KIRIOF_COMPOSER=' . escapeshellarg($composer) . ' '
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
            file_put_contents($root . '/composer.lock', 'changed lock');
            $run();
            $this->assertSame(4, $installCount(), 'Exact lock bytes must invalidate the cache.');
        file_put_contents($root . '/composer.json', '{"name":"fixture/project","require":{"php":">=8.2.35"}}');
            $run();
            $this->assertSame(5, $installCount(), 'Exact manifest bytes must invalidate the cache.');
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    public function test_changed_composer_lock_fails_packaging(): void
    {
        $root = sys_get_temp_dir() . '/kiriof-lock-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $composer = $root . '/composer';
        file_put_contents($composer, <<<'SH'
#!/bin/sh
if [ "$1" = "--version" ]; then echo composer; exit 0; fi
for argument in "$@"; do
    case "$argument" in --working-dir=*) project="${argument#--working-dir=}" ;; esac
done
printf changed > "$project/composer.lock"
SH
        );
        chmod($composer, 0700);
        file_put_contents($root . '/composer.json', '{"name":"fixture/project","require":{"php":">=8.2.35"}}');
        file_put_contents($root . '/composer.lock', 'original');
        $command = 'KIRIOF_COMPOSER=' . escapeshellarg($composer) . ' ' . escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(dirname(__DIR__) . '/scripts/package-production-vendor.php') . ' ' . escapeshellarg($root) . ' '
            . escapeshellarg($root . '/package/vendor') . ' ' . escapeshellarg($root . '/cache') . ' 2>&1';
        try {
            exec($command, $output, $status);
            $this->assertNotSame(0, $status);
            $this->assertStringContainsString('composer.lock', implode("\n", $output));
            $this->assertSame('original', file_get_contents($root . '/composer.lock'));
            $this->assertDirectoryDoesNotExist($root . '/package/vendor');
            $this->assertSame([], glob($root . '/cache/*/integrity.json'));
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }
}
