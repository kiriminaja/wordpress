<?php
/** Fake PHP ParaTest launcher, copied into each isolated repository. */
$state = dirname( __DIR__, 2 ) . '/.paratest.cache/runtime';
file_put_contents( $state . '/counter', "run\n", FILE_APPEND | LOCK_EX );
file_put_contents( $state . '/argv', json_encode( $argv ) );
fwrite( STDOUT, "fake suite stdout\n" );
fwrite( STDERR, "fake suite stderr\n" );
if ( is_file( $state . '/wait' ) ) {
	file_put_contents( $state . '/started', 'yes' );
	$deadline = microtime( true ) + 10;
	while ( is_file( $state . '/wait' ) && microtime( true ) < $deadline ) {
		clearstatcache();
		usleep( 10000 );
	}
}
exit( is_file( $state . '/fail' ) ? 7 : 0 );
