<?php

use PHPUnit\Framework\TestCase;

/** Offline process-level cache contracts; never invokes PHPUnit directly. */
final class TestResultCacheRuntimeTest extends TestCase {
	private string $root;
	private string $state;

	protected function setUp(): void {
		$this->root = dirname( __DIR__ ) . '/.paratest.cache/cache-runtime-' . bin2hex( random_bytes( 8 ) );
		$this->state = $this->root . '/.paratest.cache/runtime';
		foreach ( array( 'scripts', 'vendor/bin', '.paratest.cache/runtime', 'inc', 'assets/buyer/dist', 'assets/admin/dist' ) as $directory ) {
			mkdir( $this->root . '/' . $directory, 0777, true );
		}
		copy( dirname( __DIR__ ) . '/scripts/test-cache.php', $this->root . '/scripts/test-cache.php' );
		copy( __DIR__ . '/fixtures/test-cache-runtime.php', $this->root . '/vendor/bin/paratest' );
		file_put_contents( $this->root . '/inc/source.php', '<?php // original' );
		file_put_contents( $this->root . '/paratest.xml', '<phpunit/>' );
		file_put_contents( $this->root . '/composer.lock', '{}' );
		file_put_contents( $this->root . '/vendor/library.php', '<?php // vendor A' );
	}

	protected function tearDown(): void {
		$this->remove( $this->root );
	}

	public function test_reporting_and_nondeterministic_modes_always_execute(): void {
		foreach ( array( '--log-junit=report.xml', '--coverage-text', '--order-by=random', '--repeat=2' ) as $argument ) {
			$this->assertSame( 0, $this->runCache( array( $argument ) ) );
			$this->assertSame( 0, $this->runCache( array( $argument ) ) );
		}
		$this->assertSame( 8, $this->countRuns() );
		$this->assertSame( array(), $this->receipts() );
	}

	public function test_xml_reporting_and_random_execution_are_not_reused(): void {
		foreach ( array( '<phpunit><logging><junit outputFile="report.xml"/></logging></phpunit>', '<phpunit executionOrder="random"/>', '<phpunit><coverage/></phpunit>' ) as $xml ) {
			file_put_contents( $this->root . '/paratest.xml', $xml );
			$this->assertSame( 0, $this->runCache() );
			$this->assertSame( 0, $this->runCache() );
		}
		$this->assertSame( 6, $this->countRuns() );
		$this->assertSame( array(), $this->receipts() );
	}

	public function test_forced_success_does_not_publish_a_cache_receipt(): void {
		$this->assertSame( 0, $this->runCache() );
		$this->assertCount( 1, $this->receipts() );
		$this->assertSame( 0, $this->runCache( array(), array(), array(), true ) );
		$this->assertSame( array(), $this->receipts() );
		$this->assertSame( 0, $this->runCache( array(), array( 'KIRIOF_TEST_FORCE' => '1' ) ) );
		$this->assertSame( array(), $this->receipts() );
		$this->assertSame( 3, $this->countRuns() );
	}

	private function remove( string $path ): void {
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			foreach ( scandir( $path ) as $name ) {
				if ( '.' !== $name && '..' !== $name ) {
					$this->remove( $path . '/' . $name );
				}
			}
			rmdir( $path );
		} else {
			unlink( $path );
		}
	}

	private function start( array $extra = array(), array $environment = array(), array $php = array(), bool $force = false ): array {
		$command = array_merge( array( PHP_BINARY ), $php, array( $this->root . '/scripts/test-cache.php' ), $force ? array( '--force' ) : array(), array( '--', 'vendor/bin/paratest', '--configuration', 'paratest.xml', '--testdox' ), $extra );
		$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $this->state . '/out-' . bin2hex( random_bytes( 4 ) ), 'w' ), 2 => array( 'file', $this->state . '/err-' . bin2hex( random_bytes( 4 ) ), 'w' ) ), $pipes, $this->root, array_merge( getenv(), $environment ) );
		$this->assertIsResource( $process );
		fclose( $pipes[0] );
		return array( $process );
	}

	private function runCache( array $extra = array(), array $environment = array(), array $php = array(), bool $force = false ): int {
		return proc_close( $this->start( $extra, $environment, $php, $force )[0] );
	}

	private function countRuns(): int {
		return is_file( $this->state . '/counter' ) ? count( file( $this->state . '/counter' ) ) : 0;
	}

	private function receipts(): array {
		return glob( $this->root . '/.paratest.cache/kiriof-tests/*.json' );
	}

	public function test_success_hit_passthrough_and_command_validation(): void {
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 1, $this->countRuns() );
		$output = implode( '', array_map( 'file_get_contents', glob( $this->state . '/out-*' ) ) );
		$error = implode( '', array_map( 'file_get_contents', glob( $this->state . '/err-*' ) ) );
		$this->assertStringContainsString( 'fake suite stdout', $output );
		$this->assertStringContainsString( 'cached success', $output );
		$this->assertStringContainsString( 'fake suite stderr', $error );
		$argv = json_decode( file_get_contents( $this->state . '/argv' ), true );
		$this->assertSame( array( $this->root . '/vendor/bin/paratest', '--configuration', 'paratest.xml', '--testdox' ), $argv );
		$process = proc_open( array( PHP_BINARY, $this->root . '/scripts/test-cache.php', '--', 'vendor/bin/phpunit' ), array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'file', '/dev/null', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ), $pipes );
		$this->assertSame( 1, proc_close( $process ) );
		$this->assertSame( 1, $this->countRuns() );
	}

	public function test_source_add_edit_remove_config_lock_and_generated_inventory_invalidate(): void {
		$this->assertSame( 0, $this->runCache() );
		$changes = array(
			'inc/source.php' => '<?php // changed',
			'inc/new.php' => '<?php // new',
			'paratest.xml' => '<phpunit colors="true"/>',
			'composer.lock' => '{"changed":true}',
			'assets/buyer/dist/app.js' => 'buyer',
			'assets/admin/dist/app.js' => 'admin',
			'build/kiriminaja-official/plugin.php' => '<?php // staged',
		);
		$count = 1;
		foreach ( $changes as $path => $bytes ) {
			if ( ! is_dir( dirname( $this->root . '/' . $path ) ) ) {
				mkdir( dirname( $this->root . '/' . $path ), 0777, true );
			}
			file_put_contents( $this->root . '/' . $path, $bytes );
			$this->assertSame( 0, $this->runCache(), $path );
			$this->assertSame( ++$count, $this->countRuns(), $path );
			$this->assertSame( 0, $this->runCache() );
			$this->assertSame( $count, $this->countRuns() );
		}
		unlink( $this->root . '/inc/new.php' );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( ++$count, $this->countRuns() );
		$this->remove( $this->root . '/build/kiriminaja-official' );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( ++$count, $this->countRuns() );
	}

	public function test_vendor_bytes_not_metadata_and_noisy_trees(): void {
		$this->assertSame( 0, $this->runCache() );
		$path = $this->root . '/vendor/library.php';
		$mtime = filemtime( $path );
		file_put_contents( $path, '<?php // vendor B' );
		touch( $path, $mtime );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 2, $this->countRuns() );
		foreach ( array( 'node_modules/cache', '.e2e/report', 'build/cache', 'build/composer-vendor-cache', '.paratest/cache', 'coverage' ) as $directory ) {
			mkdir( $this->root . '/' . $directory, 0777, true );
			file_put_contents( $this->root . '/' . $directory . '/ignored.php', 'noise' );
		}
		file_put_contents( $this->root . '/debug.log', 'noise' );
		file_put_contents( $this->root . '/release.zip', 'noise' );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 2, $this->countRuns() );
	}

	public function test_environment_flags_ini_and_forced_failure_invalidate(): void {
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 0, $this->runCache( array( '--filter', 'Example' ) ) );
		$this->assertSame( 2, $this->countRuns() );
		$this->assertSame( 0, $this->runCache( array(), array( 'KIRIOF_CACHE_TEST_SECRET' => 'do-not-persist-me' ) ) );
		$this->assertSame( 3, $this->countRuns() );
		$this->assertSame( 0, $this->runCache( array(), array(), array( '-d', 'disable_functions=exec' ) ) );
		$this->assertSame( 4, $this->countRuns() );
		$this->assertSame( 0, $this->runCache( array(), array(), array( '-d', 'precision=12' ) ) );
		$this->assertSame( 5, $this->countRuns() );
		$this->assertSame( 0, $this->runCache() );
		file_put_contents( $this->state . '/fail', 'yes' );
		$this->assertSame( 7, $this->runCache( array(), array(), array(), true ) );
		$this->assertSame( 7, $this->runCache() );
		$this->assertSame( 8, $this->countRuns() );
		unlink( $this->state . '/fail' );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 0, $this->runCache( array(), array( 'KIRIOF_TEST_FORCE' => '1' ) ) );
		$this->assertSame( 10, $this->countRuns() );
		$this->assertNotContains( '--force', json_decode( file_get_contents( $this->state . '/argv' ), true ) );
		foreach ( $this->receipts() as $receipt ) {
			$this->assertStringNotContainsString( 'do-not-persist-me', file_get_contents( $receipt ) );
		}
	}

	public function test_corrupt_receipts_are_misses(): void {
		$this->assertSame( 0, $this->runCache() );
		$receipt = $this->receipts()[0];
		foreach ( array( '{', '{}', '{"schema":1,"command":[],"key":null}' ) as $corrupt ) {
			file_put_contents( $receipt, $corrupt );
			$this->assertSame( 0, $this->runCache() );
		}
		$this->assertSame( 4, $this->countRuns() );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 4, $this->countRuns() );
	}

	public function test_unverifiable_input_bypasses_cache_and_invalidates_old_success(): void {
		$this->assertSame( 0, $this->runCache() );
		symlink( $this->root . '/inc/source.php', $this->root . '/inc/linked.php' );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 3, $this->countRuns() );
		$this->assertSame( array(), $this->receipts() );
		unlink( $this->root . '/inc/linked.php' );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 4, $this->countRuns() );
	}

	private function waitForStart(): void {
		$deadline = microtime( true ) + 10;
		while ( ! is_file( $this->state . '/started' ) && microtime( true ) < $deadline ) {
			clearstatcache();
			usleep( 10000 );
		}
		$this->assertFileExists( $this->state . '/started' );
	}

	public function test_concurrent_identical_commands_wait_and_share_success(): void {
		file_put_contents( $this->state . '/wait', 'yes' );
		$first = $this->start();
		try {
			$this->waitForStart();
			$second = $this->start();
			usleep( 200000 );
			$this->assertSame( 1, $this->countRuns() );
		} finally {
			unlink( $this->state . '/wait' );
		}
		$this->assertSame( 0, proc_close( $first[0] ) );
		$this->assertSame( 0, proc_close( $second[0] ) );
		$this->assertSame( 1, $this->countRuns() );
	}

	public function test_edit_during_run_does_not_publish_mixed_success(): void {
		file_put_contents( $this->state . '/wait', 'yes' );
		$first = $this->start();
		try {
			$this->waitForStart();
			file_put_contents( $this->root . '/inc/source.php', '<?php // edited mid-suite' );
		} finally {
			unlink( $this->state . '/wait' );
		}
		$this->assertSame( 0, proc_close( $first[0] ) );
		$this->assertSame( array(), $this->receipts() );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 2, $this->countRuns() );
		$this->assertSame( 0, $this->runCache() );
		$this->assertSame( 2, $this->countRuns() );
	}

	public function test_fingerprint_is_hash_only_and_ini_file_bytes_matter(): void {
		$ini = $this->state . '/runtime.ini';
		file_put_contents( $ini, "; first bytes\nprecision=14\n" );
		$fingerprint = function ( array $php = array(), array $env = array() ): array {
			$process = proc_open( array_merge( array( PHP_BINARY ), $php, array( $this->root . '/scripts/test-cache.php', '--fingerprint-only' ) ), array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ), $pipes, $this->root, array_merge( getenv(), $env ) );
			$data = stream_get_contents( $pipes[1] );
			fclose( $pipes[1] );
			$this->assertSame( 0, proc_close( $process ) );
			return json_decode( $data, true, 512, JSON_THROW_ON_ERROR );
		};
		$first = $fingerprint( array( '-c', $ini ) );
		$this->assertSame( $first, $fingerprint( array( '-c', $ini ), array( 'KIRIOF_TEST_FORCE' => '1' ) ) );
		foreach ( $first as $name => $value ) {
			if ( 'schema' !== $name ) {
				$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $value );
			}
		}
		file_put_contents( $ini, "; changed comment only\nprecision=14\n" );
		$this->assertNotSame( $first['ini'], $fingerprint( array( '-c', $ini ) )['ini'] );
		$this->assertNotSame( $first['environment'], $fingerprint( array( '-c', $ini ), array( 'COMPOSER_BINARY' => '/different/composer' ) )['environment'] );
	}
}
