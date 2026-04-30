#!/usr/bin/env php
<?php
/**
 * Open GitHub new release page with auto-filled tag, title, and changelog body.
 *
 * Usage:
 *   php scripts/github-release.php [version]
 *
 * If version is omitted, reads KIRIOF_VERSION from kiriminaja.php.
 * Extracts the changelog entry for that version from readme.txt,
 * then opens the GitHub release page in the default browser.
 */

if ( php_sapi_name() !== 'cli' ) {
    exit( 'This script must be run from the command line.' );
}

$root_dir = dirname( __DIR__ );
$readme   = $root_dir . '/readme.txt';
$plugin   = $root_dir . '/kiriminaja.php';

// --- Determine version ---
$version = $argv[1] ?? null;

if ( ! $version && file_exists( $plugin ) ) {
    $content = file_get_contents( $plugin );
    if ( preg_match( '/define\s*\(\s*[\'"]KIRIOF_VERSION[\'"]\s*,\s*([\'"])([^\'"]+)\1/', $content, $m ) ) {
        $version = $m[2];
    }
}

if ( ! $version ) {
    fwrite( STDERR, "Error: Could not determine version.\n" );
    exit( 1 );
}

// --- Detect repo from git remote ---
$remote_url = trim( (string) shell_exec(
    sprintf( 'cd %s && git remote get-url origin 2>/dev/null', escapeshellarg( $root_dir ) )
) );

$repo = 'kiriminaja/plugin-wp'; // fallback
if ( preg_match( '#github\.com[:/]([^/]+/[^/.]+)#', $remote_url, $m ) ) {
    $repo = $m[1];
}

// --- Extract changelog entry from readme.txt ---
$body = '';
if ( file_exists( $readme ) ) {
    $readme_content = file_get_contents( $readme );

    // Match the changelog block for this version
    $escaped_version = preg_quote( $version, '/' );
    $pattern = '/^= ' . $escaped_version . ' =\s*\n(.*?)(?=^= |\z)/ms';

    if ( preg_match( $pattern, $readme_content, $m ) ) {
        $lines = array_filter( array_map( 'trim', explode( "\n", trim( $m[1] ) ) ) );

        // Convert WordPress readme format to markdown
        $md_lines = [];
        foreach ( $lines as $line ) {
            if ( str_starts_with( $line, '* ' ) ) {
                $md_lines[] = '- ' . substr( $line, 2 );
            } else {
                $md_lines[] = $line;
            }
        }
        $body = implode( "\n", $md_lines );
    }
}

if ( empty( $body ) ) {
    echo "Warning: No changelog entry found for {$version}. Release body will be empty.\n";
}

// --- Build full release body ---
$tag   = "v{$version}";
$title = "v{$version}";

$full_body = "## What's Changed\n\n{$body}\n\n**Full Changelog**: https://github.com/{$repo}/compare/v{$version}...{$tag}";

// --- Construct GitHub release URL ---
$params = http_build_query( [
    'tag'    => $tag,
    'title'  => $title,
    'body'   => $full_body,
] );

$url = "https://github.com/{$repo}/releases/new?{$params}";

echo "Opening GitHub release page for {$tag}...\n";
echo "Repo: {$repo}\n";
echo "Tag: {$tag}\n";

if ( ! empty( $body ) ) {
    echo "\nChangelog:\n{$body}\n";
}

// --- Open browser ---
$os = PHP_OS_FAMILY;
switch ( $os ) {
    case 'Darwin':
        $open_cmd = 'open';
        break;
    case 'Linux':
        $open_cmd = 'xdg-open';
        break;
    case 'Windows':
        $open_cmd = 'start';
        break;
    default:
        echo "\nCould not detect OS to open browser. Open this URL manually:\n{$url}\n";
        exit( 0 );
}

passthru( sprintf( '%s %s', $open_cmd, escapeshellarg( $url ) ) );

echo "\nDone! Create the release on GitHub and attach the zip file.\n";
