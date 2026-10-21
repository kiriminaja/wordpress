<?php
/** Native, success-only ParaTest receipt cache. No test results or secrets are stored. */
declare(strict_types=1);

function kiriof_test_digest( $value ): string {
	return hash( 'sha256', serialize( $value ) );
}

function kiriof_test_plain_configuration( string $root, array $args ): bool {
	$config = $root . '/paratest.xml';
	foreach ( $args as $index => $arg ) {
		if ( in_array( $arg, array( '--configuration', '-c' ), true ) ) {
			$config = $args[ $index + 1 ] ?? '';
		} elseif ( str_starts_with( $arg, '--configuration=' ) ) {
			$config = substr( $arg, strlen( '--configuration=' ) );
		}
	}
	if ( ! str_starts_with( $config, '/' ) ) {
		$config = $root . '/' . $config;
	}
	if ( realpath( $config ) !== realpath( $root . '/paratest.xml' ) ) {
		return false;
	}
	$xml = @file_get_contents( $config );
	// Only plain suite execution is reusable; configured reports/extensions may have effects.
	return is_string( $xml ) && ! preg_match( '/<!DOCTYPE|<(?:logging|coverage|extensions)\b|executionOrder\s*=\s*[\'"][^\'"]*random/i', $xml );
}

/** Values stay in memory: the public runtime fingerprint contains only digests. */
function kiriof_test_runtime(): array {
	$binary = realpath( PHP_BINARY );
	if ( ! $binary || ! is_file( $binary ) ) {
		throw new RuntimeException( 'PHP binary unavailable' );
	}
	$extensions = array();
	foreach ( get_loaded_extensions() as $extension ) {
		$extensions[ $extension ] = phpversion( $extension );
	}
	ksort( $extensions );
	$settings = ini_get_all( null, true );
	if ( false === $settings ) {
		throw new RuntimeException( 'Effective ini unavailable' );
	}
	ksort( $settings );
	$files = array();
	$loaded = php_ini_loaded_file();
	if ( $loaded ) {
		$files[] = $loaded;
	}
	$scanned = php_ini_scanned_files();
	if ( $scanned ) {
		$files = array_merge( $files, preg_split( '/,\s*/', trim( $scanned ) ) );
	}
	$ini = array();
	foreach ( $files as $file ) {
		$file = trim( $file );
		if ( '' !== $file ) {
			$bytes = @file_get_contents( $file );
			if ( false === $bytes ) {
				throw new RuntimeException( 'Ini file unreadable' );
			}
			$ini[] = array( hash( 'sha256', $file ), hash( 'sha256', $bytes ) );
		}
	}
	$environment = getenv();
	if ( ! is_array( $environment ) ) {
		throw new RuntimeException( 'Environment unavailable' );
	}
	// Only shell invocation bookkeeping and the wrapper force switch are omitted.
	unset( $environment['_'], $environment['SHLVL'], $environment['KIRIOF_TEST_FORCE'] );
	ksort( $environment );
	$binary_hash = @hash_file( 'sha256', $binary );
	if ( false === $binary_hash ) {
		throw new RuntimeException( 'PHP binary unreadable' );
	}
	return array(
		'schema' => 1,
		'binary' => kiriof_test_digest( array( PHP_BINARY, $binary, $binary_hash, PHP_VERSION, PHP_VERSION_ID, PHP_OS, PHP_OS_FAMILY, PHP_INT_SIZE, php_uname() ) ),
		'extensions' => kiriof_test_digest( $extensions ),
		'ini' => kiriof_test_digest( array( $settings, $ini ) ),
		'locale' => kiriof_test_digest( array( setlocale( LC_ALL, '0' ), date_default_timezone_get() ) ),
		'environment' => kiriof_test_digest( $environment ),
	);
}

/** Prune noisy trees before descent; generated trees get their own full inventories. */
function kiriof_test_inventory( string $root ): array {
	$records = array();
	$ignored = array( '.git', 'node_modules', 'vendor', 'build', 'cache', '.cache', 'coverage', '.e2e', '.paratest', '.paratest.cache', '.phpunit.cache', '.svelte-kit', '.vite', 'playwright-report', 'test-results', '.idea', '.vscode', 'composer-vendor-cache' );
	$generated = array( 'vendor', 'assets/admin/dist', 'assets/buyer/dist', 'build/kiriminaja-official' );
	$visit = function ( string $relative, bool $complete ) use ( &$visit, &$records, $root, $ignored, $generated ): void {
		$absolute = $root . '/' . $relative;
		if ( is_link( $absolute ) ) {
			throw new RuntimeException( 'Symlink inputs are not cacheable' );
		}
		if ( ! file_exists( $absolute ) ) {
			$records[] = array( $relative, 'missing' );
			return;
		}
		if ( is_dir( $absolute ) ) {
			if ( ! $complete && '' !== $relative && ( in_array( basename( $relative ), $ignored, true ) || str_starts_with( basename( $relative ), '.e2e' ) || in_array( $relative, $generated, true ) ) ) {
				return;
			}
			$records[] = array( $relative, 'directory' );
			$names = @scandir( $absolute );
			if ( false === $names ) {
				throw new RuntimeException( 'Input directory unreadable' );
			}
			foreach ( $names as $name ) {
				if ( '.' === $name || '..' === $name ) {
					continue;
				}
				if ( ! $complete && ( '.DS_Store' === $name || preg_match( '/\.(zip|log|tsbuildinfo)$/i', $name ) ) ) {
					continue;
				}
				$visit( '' === $relative ? $name : $relative . '/' . $name, $complete );
			}
		} elseif ( is_file( $absolute ) ) {
			$hash = @hash_file( 'sha256', $absolute );
			if ( false === $hash ) {
				throw new RuntimeException( 'Input file unreadable' );
			}
			$records[] = array( $relative, $hash );
		} else {
			throw new RuntimeException( 'Special inputs are not cacheable' );
		}
	};
	$visit( '', false );
	foreach ( $generated as $directory ) {
		$visit( $directory, true );
	}
	return $records;
}

function kiriof_test_key( string $root, array $command ): string {
	clearstatcache();
	return kiriof_test_digest( array( 1, $command, kiriof_test_runtime(), kiriof_test_inventory( $root ) ) );
}

function kiriof_test_execute( string $root, array $command ): int {
	$process = proc_open( $command, array( 0 => STDIN, 1 => STDOUT, 2 => STDERR ), $pipes, $root );
	return is_resource( $process ) ? proc_close( $process ) : 1;
}

function kiriof_test_main( array $args ): int {
	if ( array( '--fingerprint-only' ) === $args ) {
		echo json_encode( kiriof_test_runtime(), JSON_THROW_ON_ERROR ) . PHP_EOL;
		return 0;
	}
	$force = '1' === getenv( 'KIRIOF_TEST_FORCE' );
	if ( '--force' === ( $args[0] ?? null ) ) {
		$force = true;
		array_shift( $args );
	}
	if ( '--' !== array_shift( $args ) ) {
		throw new InvalidArgumentException( 'Usage: php scripts/test-cache.php [--force] -- vendor/bin/paratest [args...]' );
	}
	$root = dirname( __DIR__ );
	$executable = array_shift( $args );
	$expected = $root . '/vendor/bin/paratest';
	if ( ! is_string( $executable ) || ! in_array( $executable, array( 'vendor/bin/paratest', './vendor/bin/paratest', $expected ), true ) || ! is_file( $expected ) || is_link( $expected ) ) {
		throw new InvalidArgumentException( 'Only the repository vendor/bin/paratest PHP launcher is supported' );
	}
	$command = array_merge( array( PHP_BINARY, $expected ), $args );
	if ( ! kiriof_test_plain_configuration( $root, $args ) ) {
		return kiriof_test_execute( $root, $command );
	}
	// Reports and repeated/randomized execution are requested effects, not reusable checks.
	foreach ( $args as $arg ) {
		if ( preg_match( '/^--(?:coverage(?:-.+)?|log(?:-.+)?|testdox-(?:html|text)|random-order(?:-.+)?|order-by|repeat|printer)(?:=|$)/i', $arg ) ) {
			return kiriof_test_execute( $root, $command );
		}
	}
	$command_hash = kiriof_test_digest( $command );
	$directory = $root . '/.paratest.cache/kiriof-tests';
	$receipt = $directory . '/' . $command_hash . '.json';
	$lock = false;
	$temporary = null;
	try {
		if ( ! is_dir( $directory ) && ! @mkdir( $directory, 0777, true ) && ! is_dir( $directory ) ) {
			throw new RuntimeException( 'Cache directory unavailable' );
		}
		$lock = @fopen( $directory . '/' . $command_hash . '.lock', 'c' );
		if ( false === $lock ) {
			throw new RuntimeException( 'Cache lock unavailable' );
		}
		$deadline = microtime( true ) + 30;
		while ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( microtime( true ) >= $deadline ) {
				fclose( $lock );
				fwrite( STDERR, "[test-cache] timed out waiting for running suite\n" );
				return 75;
			}
			usleep( 50000 );
		}
	} catch ( Throwable $error ) {
		if ( is_resource( $lock ) ) {
			fclose( $lock );
		}
		@unlink( $receipt );
		fwrite( STDERR, "[test-cache] cache unavailable; running ParaTest\n" );
		return kiriof_test_execute( $root, $command );
	}
	try {
		$key = null;
		if ( ! $force ) {
			try {
				$key = kiriof_test_key( $root, $command );
			} catch ( Throwable $error ) {
				fwrite( STDERR, "[test-cache] inputs unavailable; running ParaTest\n" );
			}
		}
		$saved = json_decode( (string) @file_get_contents( $receipt ), true );
		if ( ! $force && null !== $key && is_array( $saved ) && array_keys( $saved ) === array( 'schema', 'command', 'key' ) && 1 === $saved['schema'] && $command_hash === $saved['command'] && $key === $saved['key'] ) {
			echo "[test-cache] cached success; unchanged ParaTest inputs\n";
			return 0;
		}
		// Invalidate before running, including forced runs and uncacheable inputs.
		if ( file_exists( $receipt ) && ! @unlink( $receipt ) ) {
			throw new RuntimeException( 'Cannot invalidate previous receipt' );
		}
		$status = kiriof_test_execute( $root, $command );
		if ( 0 === $status && null !== $key ) {
			try {
				if ( $key !== kiriof_test_key( $root, $command ) ) {
					fwrite( STDERR, "[test-cache] inputs changed during execution; success not cached\n" );
					return 0;
				}
				$temporary = $receipt . '.' . bin2hex( random_bytes( 8 ) ) . '.tmp';
				$bytes = json_encode( array( 'schema' => 1, 'command' => $command_hash, 'key' => $key ), JSON_THROW_ON_ERROR );
				if ( strlen( $bytes ) !== file_put_contents( $temporary, $bytes ) || ! rename( $temporary, $receipt ) ) {
					throw new RuntimeException( 'Cannot publish receipt' );
				}
			} catch ( Throwable $error ) {
				fwrite( STDERR, "[test-cache] success not cached\n" );
			}
		}
		return $status;
	} finally {
		if ( null !== $temporary ) {
			@unlink( $temporary );
		}
		flock( $lock, LOCK_UN );
		fclose( $lock );
	}
}

try {
	exit( kiriof_test_main( array_slice( $argv, 1 ) ) );
} catch ( Throwable $error ) {
	fwrite( STDERR, '[test-cache] ' . $error->getMessage() . PHP_EOL );
	exit( 1 );
}
