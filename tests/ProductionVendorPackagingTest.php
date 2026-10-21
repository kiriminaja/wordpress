<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Offline coverage of cache reuse, invalidation, and production install policy. */
final class ProductionVendorPackagingTest extends TestCase
{
    public function test_exact_inputs_and_full_payload_integrity_control_cache_reuse(): void
    {
        $root = sys_get_temp_dir() . '/kiriof-vendor-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $composer = $root . '/composer';
        file_put_contents($composer, '#!' . PHP_BINARY . "\n" . <<<'PHP'
<?php
if (in_array('--version', $argv, true)) {
    echo "Composer fake 1.0\n";
    exit(0);
}
$directory = substr(end($argv), strlen('--working-dir='));
$manifest = json_decode(file_get_contents($directory . '/composer.json'), true);
if ($manifest['config']['preferred-install'] !== ['kiriminaja/kiriminaja-php' => 'source', '*' => 'dist']
    || !in_array('--no-dev', $argv, true) || in_array('--prefer-source', $argv, true)) {
    exit(2);
}
file_put_contents(__DIR__ . '/installs', "install\n", FILE_APPEND);
mkdir($directory . '/vendor/kiriminaja/kiriminaja-php/src/Base/Api', 0700, true);
mkdir($directory . '/vendor/kiriminaja/kiriminaja-php/.git', 0700, true);
file_put_contents($directory . '/vendor/kiriminaja/kiriminaja-php/.git/config', 'discard');
file_put_contents($directory . '/vendor/kiriminaja/kiriminaja-php/README.md', 'discard');
file_put_contents($directory . '/vendor/kiriminaja/kiriminaja-php/src/Base/Api/Api.php', '<?php namespace KiriminAja\Base\Api; class Api {}');
file_put_contents($directory . '/vendor/autoload.php', '<?php require __DIR__ . "/kiriminaja/kiriminaja-php/src/Base/Api/Api.php";');
file_put_contents($directory . '/vendor/other-runtime.php', '<?php // Must be integrity-checked too.');
PHP
        );
        chmod($composer, 0700);
        file_put_contents($root . '/composer.json', '{"config":{},"require":{}}');
        file_put_contents($root . '/composer.lock', '{"packages":[]}');

        $run = function () use ($root, $composer): void {
            $command = 'KIRIOF_COMPOSER=' . escapeshellarg($composer) . ' ' . escapeshellarg(PHP_BINARY)
                . ' ' . escapeshellarg(dirname(__DIR__) . '/scripts/package-production-vendor.php')
                . ' ' . escapeshellarg($root) . ' ' . escapeshellarg($root . '/package/vendor')
                . ' ' . escapeshellarg($root . '/cache') . ' 2>&1';
            exec($command, $output, $status);
            $this->assertSame(0, $status, implode("\n", $output));
        };
        $installCount = static fn (): int => count(file($root . '/installs'));
        try {
            $run();
            $this->assertSame(1, $installCount());
            $this->assertFileDoesNotExist($root . '/package/vendor/kiriminaja/kiriminaja-php/README.md');
            $this->assertDirectoryDoesNotExist($root . '/package/vendor/kiriminaja/kiriminaja-php/.git');
            $run();
            $this->assertSame(1, $installCount(), 'An unchanged package must not reinstall dependencies.');

            $cachedVendor = glob($root . '/cache/*/vendor')[0];
            file_put_contents($cachedVendor . '/other-runtime.php', 'corrupt');
            $run();
            $this->assertSame(2, $installCount(), 'Corruption outside the SDK/autoloader must invalidate the cache.');
            unlink($cachedVendor . '/other-runtime.php');
            $run();
            $this->assertSame(3, $installCount(), 'Deleted runtime files must invalidate the cache.');

            file_put_contents($root . '/composer.lock', '{"packages":[],"changed":true}');
            $run();
            $this->assertSame(4, $installCount(), 'Exact lock bytes must invalidate the cache.');
            file_put_contents($root . '/composer.json', '{"config":{},"require":{},"description":"changed"}');
            $run();
            $this->assertSame(5, $installCount(), 'Exact manifest bytes must invalidate the cache.');
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }
}
