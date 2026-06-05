<?php
/**
 * Build-time script: injects KIRIOF_API_BASE_URL constant into a staged kiriminaja.php.
 *
 * Usage: php scripts/inject-api-url.php <target-file> <api-url>
 */
if ( $argc < 3 ) {
    fwrite( STDERR, "Usage: php inject-api-url.php <target-file> <api-url>\n" );
    exit( 1 );
}

$file = $argv[1];
$url  = rtrim( trim( $argv[2] ), '/' );

if ( ! file_exists( $file ) ) {
    fwrite( STDERR, "Error: File not found: {$file}\n" );
    exit( 1 );
}

if ( ! preg_match( '#^https?://#i', $url ) ) {
    fwrite( STDERR, "Error: URL must start with http:// or https://: {$url}\n" );
    exit( 1 );
}

$content = file_get_contents( $file );

$anchor  = "define( 'KIRIOF_MAX_COD_AMOUNT'";
$inject  = "define( 'KIRIOF_API_BASE_URL', '" . $url . "' );\n";

if ( strpos( $content, $anchor ) === false ) {
    fwrite( STDERR, "Error: Anchor '{$anchor}' not found in {$file}\n" );
    exit( 1 );
}

$content = str_replace( $anchor, $inject . $anchor, $content );
file_put_contents( $file, $content );

echo "  → Injected KIRIOF_API_BASE_URL: {$url}\n";
