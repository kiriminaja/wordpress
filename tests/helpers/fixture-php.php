<?php
/** Minimal fixture PHP startup, retaining the parent's ctype/mbstring profile. */

/**
 * Build the prefix separately so shared-module and fallback cases can be tested
 * without attempting to load synthetic modules. A null probe means it failed.
 *
 * @param list<string> $parent_extensions
 * @param list<string>|null $minimal_extensions
 * @return list<string>
 */
function kiriof_test_fixture_php_prefix( string $binary, array $parent_extensions, ?array $minimal_extensions, string $extension_dir, string $suffix ): array {
    if ( $minimal_extensions === null ) {
        return array( $binary );
    }

    $argv = array( $binary, '-n' );
    foreach ( array( 'ctype', 'mbstring' ) as $extension ) {
        $parent_active = in_array( $extension, $parent_extensions, true );
        $minimal_active = in_array( $extension, $minimal_extensions, true );
        if ( $parent_active === $minimal_active ) {
            continue;
        }
        // Built-ins cannot be unloaded. Missing/custom module paths cannot be
        // reconstructed safely: retain the normal parent startup instead.
        $module = rtrim( $extension_dir, '/\\' ) . DIRECTORY_SEPARATOR . $extension . '.' . $suffix;
        if ( ! $parent_active || $extension_dir === '' || ! is_file( $module ) ) {
            return array( $binary );
        }
        $argv[] = '-d';
        $argv[] = 'extension=' . $module;
    }

    return $argv;
}

/** @param list<string> $arguments @return list<string> */
function kiriof_test_fixture_php_argv( array $arguments = array() ): array {
    static $prefix;
    if ( $prefix === null ) {
        $output = array();
        $status = 1;
        exec( escapeshellarg( PHP_BINARY ) . ' -n -r ' . escapeshellarg( 'echo json_encode(get_loaded_extensions());' ) . ' 2>&1', $output, $status );
        $minimal = $status === 0 ? json_decode( implode( "\n", $output ), true ) : null;
        if ( ! is_array( $minimal ) || array_filter( $minimal, static fn( $value ) => ! is_string( $value ) ) ) {
            $minimal = null;
        }
        $prefix = kiriof_test_fixture_php_prefix( PHP_BINARY, get_loaded_extensions(), $minimal, (string) ini_get( 'extension_dir' ), PHP_SHLIB_SUFFIX );
    }

    return array_merge( $prefix, $arguments );
}

/** @param list<string> $arguments */
function kiriof_test_fixture_php_shell( array $arguments = array() ): string {
    return implode( ' ', array_map( 'escapeshellarg', kiriof_test_fixture_php_argv( $arguments ) ) );
}
