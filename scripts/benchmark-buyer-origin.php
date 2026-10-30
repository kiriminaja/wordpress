<?php
/**
 * Benchmark the real shipment location service/repository on SQLite, not MySQL.
 *
 * Usage: php scripts/benchmark-buyer-origin.php [--query-source=/tmp/baseline.php]
 *        [--warmups=5] [--samples=30]
 *
 * Save HEAD:inc/Services/ShipmentLocationService.php with git show before edits
 * and pass that temporary file to measure the baseline in a separate process.
 * This standalone CLI bootstrap intentionally runs without WordPress/ABSPATH;
 * the CLI-only fixture establishes its own isolated bootstrap after the SAPI guard.
 */

declare(strict_types=1);

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$options = getopt( '', array( 'query-source:', 'warmups:', 'samples:' ) );
$source  = $options['query-source'] ?? dirname( __DIR__ ) . '/inc/Services/ShipmentLocationService.php';
if ( ! is_file( $source ) ) {
	fwrite( STDERR, "Service source does not exist.\n" );
	exit( 1 );
}
$config = array( 'query_source' => realpath( $source ), 'mode' => 'benchmark' );
foreach ( array( 'warmups' => 5, 'samples' => 30 ) as $name => $default ) {
	$value = filter_var( $options[ $name ] ?? $default, FILTER_VALIDATE_INT );
	if ( false === $value || $value < ( 'warmups' === $name ? 0 : 1 ) ) {
		fwrite( STDERR, "Invalid --{$name} value.\n" );
		exit( 1 );
	}
	$config[ $name ] = $value;
}
$argv[1] = json_encode( $config, JSON_THROW_ON_ERROR );
require dirname( __DIR__ ) . '/tests/fixtures/buyer-origin-performance.php';
