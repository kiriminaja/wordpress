#!/usr/bin/env php
<?php
/**
 * Generate changelog from git commits and update readme.txt
 *
 * Usage:
 *   php scripts/changelog.php [version] [from-ref] [bump-type]
 *   make changelog                        # auto-bumps patch version (e.g. 2.1.3 -> 2.1.4)
 *   make changelog BUMP=minor             # bumps minor (e.g. 2.1.4 -> 2.2.0)
 *   make changelog BUMP=major             # bumps major (e.g. 2.2.0 -> 3.0.0)
 *   make changelog V=2.2.0               # explicit version
 *   make changelog V=2.2.0 FROM=abc1234  # explicit version + starting ref
 *
 * Bump types:
 *   patch (default) — 2.1.3 -> 2.1.4, auto-rolls to minor at .99 (2.1.99 -> 2.2.0)
 *   minor           — 2.1.4 -> 2.2.0,  auto-rolls to major at .99 (2.99.0 -> 3.0.0)
 *   major           — 2.2.0 -> 3.0.0
 *
 * Reads git log since the last version in readme.txt (or from-ref),
 * filters commits containing "feature/feat" or "fixing/fix" keywords,
 * and prepends the new version entry to the == Changelog == section.
 * Also updates: Stable tag, KIRIOF_VERSION, Version header, and WC tested up to.
 */

if ( php_sapi_name() !== 'cli' ) {
    exit( 'This script must be run from the command line.' );
}

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

// Validate version is a semver-ish string; otherwise treat as missing.
if ( $version !== null && ! preg_match( '/^\d+(\.\d+){0,2}$/', $version ) ) {
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

// --- Find the last version tag in changelog to scope git log ---
$last_version = null;
if ( preg_match( '/^== Changelog ==\s*\n= ([^\s=]+) =/m', $readme_content, $m ) ) {
    $last_version = $m[1];
}

// Skip if this version already exists in changelog
if ( $last_version === $version ) {
    echo "Version {$version} already exists in changelog. Skipping.\n";
    exit( 0 );
}

// --- Determine commit range ---
// Priority: git tag > grep commit by version > optional --from arg
$since_arg = '';

if ( $from_ref ) {
    // Explicit ref passed as second argument
    $since_arg = escapeshellarg( $from_ref ) . '..HEAD';
    echo "Using explicit range: {$from_ref}..HEAD\n";
} elseif ( $last_version ) {
    // Try git tag first
    $tag_cmd   = sprintf(
        'cd %s && git tag -l %s 2>/dev/null',
        escapeshellarg( $root_dir ),
        escapeshellarg( $last_version )
    );
    $tag_exists = trim( (string) shell_exec( $tag_cmd ) );

    if ( $tag_exists ) {
        $since_arg = escapeshellarg( $last_version ) . '..HEAD';
        echo "Using tag range: {$last_version}..HEAD\n";
    } else {
        // Find the commit that set this version in kiriminaja.php (current branch only)
        $escaped  = escapeshellarg( "KIRIOF_VERSION', '{$last_version}'" );
        $find_cmd = sprintf(
            'cd %s && git log --oneline --format=%%H -S %s -- kiriminaja.php | head -1',
            escapeshellarg( $root_dir ),
            $escaped
        );
        $since_hash = trim( shell_exec( $find_cmd ) );

        if ( $since_hash ) {
            $since_arg = escapeshellarg( $since_hash ) . '..HEAD';
            echo "Using commit range: {$since_hash}..HEAD\n";
        } else {
            echo "Warning: Could not find boundary for {$last_version}. Reading last 30 commits.\n";
            $since_arg = '-30';
        }
    }
}

$log_cmd = sprintf(
    'cd %s && git log %s --format="%%s" --no-merges',
    escapeshellarg( $root_dir ),
    $since_arg
);

$output = [];
exec( $log_cmd, $output, $ret );

if ( $ret !== 0 ) {
    fwrite( STDERR, "Error: git log failed.\n" );
    exit( 1 );
}

// --- Filter commits by keywords ---
$features = [];
$fixes    = [];

foreach ( $output as $line ) {
    $line = trim( $line );
    if ( empty( $line ) ) {
        continue;
    }

    // Clean up common prefixes (AB#xxxxx, feat:, fix:, chore:, etc.)
    $clean = preg_replace( '/^(AB#\d+\s*)?/', '', $line );
    $clean = preg_replace( '/^(feat|fix|chore|refactor|docs|style|test|ci|build|perf):\s*/i', '', $clean );
    $clean = trim( $clean );

    if ( empty( $clean ) ) {
        continue;
    }

    $lower = strtolower( $line );

    if ( preg_match( '/\bfeature|feat\b/i', $lower ) ) {
        $features[] = ucfirst( $clean );
    } elseif ( preg_match( '/\bfixing|fix\b/i', $lower ) ) {
        $fixes[] = ucfirst( $clean );
    }
}

// Deduplicate
$features = array_unique( $features );
$fixes    = array_unique( $fixes );

if ( empty( $features ) && empty( $fixes ) ) {
    echo "No feature/fixing commits found since {$last_version}. Nothing to add.\n";
    exit( 0 );
}

// --- Build changelog entry ---
$entry = "= {$version} =\n";

foreach ( $features as $f ) {
    $entry .= "- {$f}\n";
}
foreach ( $fixes as $f ) {
    $entry .= "- {$f}\n";
}

echo "Found " . count( $features ) . " feature(s) and " . count( $fixes ) . " fix(es).\n";

// --- Insert into readme.txt ---
$marker = '== Changelog ==';
$pos    = strpos( $readme_content, $marker );

if ( $pos === false ) {
    fwrite( STDERR, "Error: '== Changelog ==' section not found in readme.txt.\n" );
    exit( 1 );
}

$insert_pos     = $pos + strlen( $marker ) . "\n";
$before         = substr( $readme_content, 0, $pos + strlen( $marker ) );
$after          = substr( $readme_content, $pos + strlen( $marker ) );

$new_content = $before . "\n" . $entry . $after;

file_put_contents( $readme, $new_content );

echo "Updated readme.txt with changelog for version {$version}.\n";

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

echo "Done!\n";
