<?php
/**
 * Fails packaging when PHP source contains literal backslash escape tokens
 * outside strings, which can cause production parse errors (for example \t
 * before a class member after an unsafe formatter edit).
 */

$root = dirname( __DIR__ );
$paths = array( $root . '/inc', $root . '/templates', $root . '/kiriminaja.php', $root . '/uninstall.php' );
$files = array();

foreach ( $paths as $path ) {
	if ( is_file( $path ) ) {
		$files[] = $path;
		continue;
	}
	if ( ! is_dir( $path ) ) {
		continue;
	}
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( 'php' === $file->getExtension() ) {
			$files[] = $file->getPathname();
		}
	}
}

$invalid = array();
foreach ( $files as $file ) {
	$source = file_get_contents( $file );
	if ( false === $source ) {
		continue;
	}
	$tokens = token_get_all( $source );
	foreach ( $tokens as $token ) {
		if ( ! is_array( $token ) ) {
			continue;
		}
		if ( T_STRING === $token[0] && in_array( $token[1], array( '\\t', '\\n', '\\r' ), true ) ) {
			$invalid[] = str_replace( $root . '/', '', $file ) . ':' . $token[2] . ' contains invalid literal ' . $token[1];
		}
	}
}

if ( $invalid ) {
	fwrite( STDERR, "Invalid literal PHP escape tokens:\n" . implode( "\n", $invalid ) . "\n" );
	exit( 1 );
}
