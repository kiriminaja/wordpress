<?php
/**
 * CPU-only benchmark with a fake wpdb and the real courier catalog/repository.
 *
 * Usage: php scripts/benchmark-courier-policy.php [--repository-source=/tmp/before.php]
 *        [--samples=15] [--iterations=50] [--warmups=2]
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$options = getopt( '', array( 'repository-source:', 'samples:', 'iterations:', 'warmups:' ) );
$config  = array(
	'mode'              => 'benchmark',
	'repository_source' => $options['repository-source'] ?? dirname( __DIR__ ) . '/inc/Repositories/SettingRepository.php',
);
foreach ( array( 'samples' => 15, 'iterations' => 50, 'warmups' => 2 ) as $name => $default ) {
	$value = filter_var( $options[ $name ] ?? $default, FILTER_VALIDATE_INT );
	if ( false === $value || $value < ( 'warmups' === $name ? 0 : 1 ) ) {
		fwrite( STDERR, "Invalid --{$name} value.\n" );
		exit( 1 );
	}
	$config[ $name ] = $value;
}
if ( ! is_file( $config['repository_source'] ) ) {
	fwrite( STDERR, "Repository source does not exist.\n" );
	exit( 1 );
}
$argv[1] = json_encode( $config, JSON_THROW_ON_ERROR );
require dirname( __DIR__ ) . '/tests/fixtures/courier-policy-performance.php';
