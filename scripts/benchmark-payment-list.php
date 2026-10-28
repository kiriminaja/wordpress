<?php
/**
 * Benchmark the actual payment read model with the SQLite runtime fixture.
 *
 * Usage: php scripts/benchmark-payment-list.php [--query-source=/tmp/baseline.php]
 *        [--groups=2000] [--warmups=3] [--iterations=15]
 *
 * Capture an old source with git show REV:inc/Queries/WordPressPaymentListQuery.php
 * into a temporary file, then pass its absolute path. Each invocation loads just
 * one query implementation, so baseline and current classes cannot collide.
 */

declare(strict_types=1);

$options = getopt( '', array( 'query-source:', 'groups:', 'warmups:', 'iterations:' ) );
$source  = $options['query-source'] ?? dirname( __DIR__ ) . '/inc/Queries/WordPressPaymentListQuery.php';
if ( ! is_file( $source ) ) {
    fwrite( STDERR, "Query source does not exist.\n" );
    exit( 1 );
}
$benchmark = array();
foreach ( array( 'groups' => 2000, 'warmups' => 3, 'iterations' => 15 ) as $name => $default ) {
    $value = filter_var( $options[ $name ] ?? $default, FILTER_VALIDATE_INT );
    if ( false === $value || $value < ( 'warmups' === $name ? 0 : 1 ) ) {
        fwrite( STDERR, "Invalid --{$name} value.\n" );
        exit( 1 );
    }
    $benchmark[ $name ] = $value;
}
$argv[1] = json_encode( array( 'query_source' => realpath( $source ), 'benchmark' => $benchmark ), JSON_THROW_ON_ERROR );
require dirname( __DIR__ ) . '/tests/fixtures/payment-list-database-runtime.php';
