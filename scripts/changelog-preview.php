<?php
/**
 * Preview public changelog bullets for a git range without modifying files.
 * Usage: php scripts/changelog-preview.php [from-ref] [to-ref]
 */

if ( php_sapi_name() !== 'cli' ) {
    exit( 'This script must be run from the command line.' );
}

$root_dir = dirname( __DIR__ );
$from_ref = $argv[1] ?? '';
$to_ref   = $argv[2] ?? 'HEAD';
$range    = '' !== $from_ref ? escapeshellarg( $from_ref . '..' . $to_ref ) : '-30';
$command  = sprintf(
    'cd %s && git log --first-parent %s --format="%%P%%x1f%%s%%x1e"',
    escapeshellarg( $root_dir ),
    $range
);
$raw = shell_exec( $command );
if ( null === $raw ) {
    fwrite( STDERR, "Error: git log failed.\n" );
    exit( 1 );
}

$notes = array();
foreach ( array_filter( explode( chr( 30 ), $raw ) ) as $commit ) {
    $fields = explode( chr( 31 ), trim( $commit ), 2 );
    if ( 2 !== count( $fields ) || 1 !== count( array_filter( preg_split( '/\s+/', trim( $fields[0] ) ) ) ) ) {
        continue;
    }
    if ( preg_match( '/^(feat(?:ure)?|fix(?:ing)?|perf)(?:\([^)]*\))?!?:\s*(.+)$/i', trim( $fields[1] ), $match ) ) {
        $notes[] = '- ' . ucfirst( trim( $match[2] ) );
    }
}

echo implode( "\n", array_values( array_unique( $notes ) ) ) . "\n";
