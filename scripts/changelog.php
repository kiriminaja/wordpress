#!/usr/bin/env php
<?php
/**
 * Generate a PR-based changelog from GitHub release notes and update readme.txt.
 *
 * Usage:
 *   php scripts/changelog.php [version] [from-ref] [bump-type]
 *   make changelog                        # auto-bumps patch version (e.g. 2.1.3 -> 2.1.4)
 *   make changelog BUMP=minor             # bumps minor (e.g. 2.1.4 -> 2.2.0)
 *   make changelog BUMP=major             # bumps major (e.g. 2.2.0 -> 3.0.0)
 *   make changelog V=2.2.0               # explicit version
 *   make changelog V=2.2.0 FROM=v2.1.9  # explicit version + previous release tag
 *
 * Bump types:
 *   patch (default) — 2.1.3 -> 2.1.4, auto-rolls to minor at .99 (2.1.99 -> 2.2.0)
 *   minor           — 2.1.4 -> 2.2.0,  auto-rolls to major at .99 (2.99.0 -> 3.0.0)
 *   major           — 2.2.0 -> 3.0.0
 *
 * Uses GitHub's generated release notes since the previous release tag.
 * Requires an authenticated gh CLI and a pushed release branch.
 * Prepends the new version entry to the == Changelog == section.
 * Also updates: Stable tag, KIRIOF_VERSION, Version header, and WC tested up to.
 */

if ( php_sapi_name() !== 'cli' ) {
    exit( 'This script must be run from the command line.' );
}

require_once __DIR__ . '/release-notes.php';

$root_dir   = dirname( __DIR__ );
$readme     = $root_dir . '/readme.txt';
$plugin     = $root_dir . '/kiriminaja.php';

if ( ! file_exists( $readme ) ) {
    fwrite( STDERR, "Error: readme.txt not found.\n" );
    exit( 1 );
}

// --- Determine version ---
$version   = isset( $argv[1] ) && $argv[1] !== '' ? $argv[1] : null;
$from_ref  = isset( $argv[2] ) && $argv[2] !== '' ? $argv[2] : null;
$bump_type = isset( $argv[3] ) && $argv[3] !== '' ? $argv[3] : 'patch';

// Strip leading "v" if present (e.g. v2.1.9 -> 2.1.9)
if ( $version !== null ) {
    $version = ltrim( $version, 'vV' );
}

// Accept explicit prerelease versions used by the publish workflow.
if ( $version !== null && ! preg_match( '/^\d+(\.\d+){0,2}(?:-[A-Za-z0-9]+(?:[.-][A-Za-z0-9]+)*)?$/', $version ) ) {
    fwrite( STDERR, "Warning: Ignoring invalid version argument '{$version}'. Falling back to bump.\n" );
    $version = null;
}

// Validate bump type.
if ( ! in_array( $bump_type, [ 'patch', 'minor', 'major' ], true ) ) {
    $bump_type = 'patch';
}

// Read current version from kiriminaja.php
$current_version = null;
if ( file_exists( $plugin ) ) {
    $content = file_get_contents( $plugin );
    if ( preg_match( "/define\(\s*'KIRIOF_VERSION',\s*'([^']+)'/", $content, $m ) ) {
        $current_version = $m[1];
    }
}

if ( ! $version ) {
    if ( $current_version ) {
        $parts = array_map( 'intval', explode( '.', $current_version ) );
        // Ensure we have at least 3 parts
        while ( count( $parts ) < 3 ) {
            $parts[] = 0;
        }
        [ $major, $minor, $patch ] = $parts;

        switch ( $bump_type ) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                // Auto-roll to major if minor exceeds 99
                if ( $minor > 99 ) {
                    $major++;
                    $minor = 0;
                }
                break;
            case 'patch':
            default:
                $patch++;
                // Auto-roll to minor if patch exceeds 99
                if ( $patch > 99 ) {
                    $minor++;
                    $patch = 0;
                    // Auto-roll to major if minor also exceeds 99
                    if ( $minor > 99 ) {
                        $major++;
                        $minor = 0;
                    }
                }
                break;
        }

        $version = "{$major}.{$minor}.{$patch}";
        echo "Auto-bumped version ({$bump_type}): {$current_version} -> {$version}\n";
    }
}

if ( ! $version ) {
    fwrite( STDERR, "Error: Could not determine version. Pass it as argument: php scripts/changelog.php 2.1.3\n" );
    exit( 1 );
}

echo "Generating changelog for version {$version}...\n";

// --- Read readme.txt ---
$readme_content = file_get_contents( $readme );

// Use the latest changelog version as the previous GitHub release tag.
$last_version = null;
if ( preg_match( '/^== Changelog ==\s*\n= ([^\s=]+) =/m', $readme_content, $m ) ) {
    $last_version = $m[1];
}

// Skip if this version already exists in changelog
if ( $last_version === $version ) {
    echo "Version {$version} already exists in changelog. Skipping.\n";
    exit( 0 );
}

$marker = '== Changelog ==';
$pos    = strpos( $readme_content, $marker );
if ( false === $pos ) {
    fwrite( STDERR, "Error: '== Changelog ==' section not found in readme.txt.\n" );
    exit( 1 );
}

try {
    $previous_tag = $from_ref ?: ( $last_version ? 'v' . $last_version : null );
    $notes = kiriof_generate_release_notes( $root_dir, $version, $previous_tag );
} catch ( Throwable $error ) {
    fwrite( STDERR, 'Error: ' . $error->getMessage() . "\n" );
    exit( 1 );
}

$before = substr( $readme_content, 0, $pos + strlen( $marker ) );
$after  = ltrim( substr( $readme_content, $pos + strlen( $marker ) ), "\r\n" );
file_put_contents( $readme, $before . "\n= {$version} =\n{$notes}\n\n" . $after );
echo "Updated readme.txt with GitHub release notes for version {$version}.\n";

// --- Also update Stable tag and Version header ---
$new_content = file_get_contents( $readme );
$new_content = preg_replace(
    '/^Stable tag:\s*.+$/m',
    "Stable tag: {$version}",
    $new_content
);
file_put_contents( $readme, $new_content );

echo "Updated Stable tag to {$version}.\n";

// --- Update KIRIOF_VERSION in kiriminaja.php ---
if ( file_exists( $plugin ) ) {
    $plugin_content = file_get_contents( $plugin );

    $plugin_content = preg_replace(
        "/define\(\s*'KIRIOF_VERSION',\s*'[^']+'\s*\)/",
        "define( 'KIRIOF_VERSION', '{$version}' )",
        $plugin_content
    );

    $plugin_content = preg_replace(
        '/^\s*\*\s*Version:\s*.+$/m',
        " * Version:         {$version}",
        $plugin_content
    );

    file_put_contents( $plugin, $plugin_content );
    echo "Updated kiriminaja.php version to {$version}.\n";
}

// --- Sync translation package headers with release version ---
$lang_dir = $root_dir . '/lang';
if ( is_dir( $lang_dir ) ) {
    $translation_files = glob( $lang_dir . '/*.{po,pot}', GLOB_BRACE );

    foreach ( $translation_files as $translation_file ) {
        $translation_content = file_get_contents( $translation_file );
        $updated_content     = preg_replace(
            '/^"Project-Id-Version:\s*KiriminAja Official\s+[^\\\\"]*\\\\n"$/m',
            "\"Project-Id-Version: KiriminAja Official {$version}\\n\"",
            $translation_content
        );

        if ( $updated_content !== null && $updated_content !== $translation_content ) {
            file_put_contents( $translation_file, $updated_content );
            echo 'Updated ' . basename( $translation_file ) . " Project-Id-Version to {$version}.\n";
        }
    }

    $msgfmt = trim( (string) shell_exec( 'command -v msgfmt 2>/dev/null' ) );
    if ( $msgfmt ) {
        foreach ( glob( $lang_dir . '/*.po' ) as $po_file ) {
            $mo_file = preg_replace( '/\.po$/', '.mo', $po_file );
            $cmd     = escapeshellcmd( $msgfmt ) . ' -o ' . escapeshellarg( $mo_file ) . ' ' . escapeshellarg( $po_file );
            exec( $cmd, $msgfmt_output, $msgfmt_status );

            if ( $msgfmt_status !== 0 ) {
                fwrite( STDERR, 'Warning: Failed to compile ' . basename( $mo_file ) . ".\n" );
            } else {
                echo 'Compiled ' . basename( $mo_file ) . ".\n";
            }
        }
    } elseif ( glob( $lang_dir . '/*.po' ) ) {
        fwrite( STDERR, "Error: msgfmt not found; cannot compile updated .mo translation files.\n" );
        exit( 1 );
    }
}

echo "Done!\n";
