<?php
/**
 * Render GitHub release notes from the matching readme.txt changelog entry.
 * This keeps the public GitHub release body and WordPress changelog identical.
 *
 * Usage: php scripts/release-notes.php <version> [output-file]
 */

if ( php_sapi_name() !== 'cli' ) {
    exit( 'This script must be run from the command line.' );
}

$root_dir = dirname( __DIR__ );
$version  = isset( $argv[1] ) ? ltrim( trim( $argv[1] ), 'vV' ) : '';
$output   = $argv[2] ?? '';
$readme   = $root_dir . '/readme.txt';

if ( '' === $version || ! preg_match( '/^\d+(?:\.\d+){1,2}(?:-[0-9A-Za-z.-]+)?$/', $version ) ) {
    fwrite( STDERR, "Error: A valid release version is required.\n" );
    exit( 1 );
}

$content = file_get_contents( $readme );
$pattern = '/^=\s*' . preg_quote( $version, '/' ) . '\s*=\R((?:- .*(?:\R|$))*)/m';
if ( ! preg_match( $pattern, $content, $match ) ) {
    fwrite( STDERR, "Error: Changelog entry {$version} was not found in readme.txt.\n" );
    exit( 1 );
}

$bullets = trim( $match[1] );
$notes   = "## What's Changed\n\n{$bullets}\n\n";
$notes  .= "### Assets\n\n";
$notes  .= "- `kiriminaja-official.zip` — WP.org-ready build (v{$version}).\n";

if ( '' !== $output ) {
    file_put_contents( $output, $notes );
    echo "Generated {$output} from readme.txt changelog {$version}.\n";
} else {
    echo $notes;
}
