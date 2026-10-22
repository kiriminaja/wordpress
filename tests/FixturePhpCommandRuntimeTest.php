<?php

// Also exercise a fresh minimal parent, without PHPUnit or the host bootstrap.
if ( in_array( '--fixture', $argv ?? array(), true ) ) {
    require_once __DIR__ . '/helpers/fixture-php.php';
    $code = 'echo json_encode([function_exists("ctype_digit"), function_exists("mb_strlen"), get_loaded_extensions(), $argv]);';
    $output = array();
    exec( kiriof_test_fixture_php_shell( array( '-r', $code, '--', 'space quote\' "$HOME"; $(echo unsafe)' ) ), $output, $status );
    echo json_encode( array( 'parent' => array( function_exists( 'ctype_digit' ), function_exists( 'mb_strlen' ) ), 'child' => json_decode( implode( "\n", $output ), true ), 'status' => $status ) );
    exit;
}

use PHPUnit\Framework\TestCase;

final class FixturePhpCommandRuntimeTest extends TestCase {
    public function testChildMatchesParentFunctionAvailabilityAndMinimalExtensionProfile(): void {
        $code = 'echo json_encode([function_exists("ctype_digit"), function_exists("mb_strlen"), get_loaded_extensions()]);';
        exec( kiriof_test_fixture_php_shell( array( '-r', $code ) ), $output, $status );
        self::assertSame( 0, $status );
        $child = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
        self::assertSame( function_exists( 'ctype_digit' ), $child[0] );
        self::assertSame( function_exists( 'mb_strlen' ), $child[1] );
        if ( in_array( '-n', kiriof_test_fixture_php_argv(), true ) ) {
            exec( escapeshellarg( PHP_BINARY ) . ' -n -r ' . escapeshellarg( 'echo json_encode(get_loaded_extensions());' ), $minimal, $probe_status );
            self::assertSame( 0, $probe_status );
            $allowed = array_merge( json_decode( implode( "\n", $minimal ), true, 512, JSON_THROW_ON_ERROR ), array( 'ctype', 'mbstring' ) );
            self::assertSame( array(), array_values( array_diff( $child[2], $allowed ) ), 'Minimal fixtures must not load unrelated shared modules such as mongodb.' );
        }
    }

    public function testFreshMinimalParentPreservesItsOwnAvailabilityAndExactShellArguments(): void {
        exec( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __FILE__ ) . ' --fixture', $output, $status );
        self::assertSame( 0, $status );
        $result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
        self::assertSame( 0, $result['status'] );
        self::assertSame( $result['parent'], array_slice( $result['child'], 0, 2 ) );
        self::assertSame( 'space quote\' "$HOME"; $(echo unsafe)', $result['child'][3][1] );
    }

    public function testShellBuilderEscapesEveryArgvElementAndPreservesOptions(): void {
        $arguments = array( '-d', 'error_reporting=22527', '-r', 'echo $argv[1];', '--', "quote' space; \"$\\" );
        self::assertSame( implode( ' ', array_map( 'escapeshellarg', kiriof_test_fixture_php_argv( $arguments ) ) ), kiriof_test_fixture_php_shell( $arguments ) );
        self::assertSame( $arguments, array_slice( kiriof_test_fixture_php_argv( $arguments ), -count( $arguments ) ) );
    }

    public function testPrefixLoadsOnlyMissingParentActiveSharedModules(): void {
        $directory = sys_get_temp_dir() . '/kiriof fixture ' . bin2hex( random_bytes( 8 ) );
        mkdir( $directory );
        touch( $directory . '/ctype.so' );
        touch( $directory . '/mbstring.so' );
        try {
            $parent = array( 'Core', 'ctype', 'mbstring', 'mongodb' );
            self::assertSame( array( '/php', '-n', '-d', 'extension=' . $directory . '/ctype.so', '-d', 'extension=' . $directory . '/mbstring.so' ), kiriof_test_fixture_php_prefix( '/php', $parent, array( 'Core' ), $directory, 'so' ) );
            self::assertSame( array( '/php', '-n', '-d', 'extension=' . $directory . '/mbstring.so' ), kiriof_test_fixture_php_prefix( '/php', $parent, array( 'Core', 'ctype' ), $directory, 'so' ) );
            self::assertSame( array( '/php', '-n' ), kiriof_test_fixture_php_prefix( '/php', array( 'Core' ), array( 'Core' ), $directory, 'so' ) );
            self::assertSame( array( '/php', '-n' ), kiriof_test_fixture_php_prefix( '/php', $parent, array( 'Core', 'ctype', 'mbstring' ), $directory, 'so' ) );
        } finally {
            unlink( $directory . '/ctype.so' );
            unlink( $directory . '/mbstring.so' );
            rmdir( $directory );
        }
    }

    public function testUnknownProbeOrUnresolvableModuleFallsBackRatherThanSkipping(): void {
        self::assertSame( array( '/php' ), kiriof_test_fixture_php_prefix( '/php', array( 'ctype' ), null, '', 'so' ) );
        self::assertSame( array( '/php' ), kiriof_test_fixture_php_prefix( '/php', array( 'ctype', 'mbstring' ), array(), __DIR__ . '/missing-modules', 'so' ) );
        self::assertSame( array( '/php' ), kiriof_test_fixture_php_prefix( '/php', array( 'ctype' ), array(), '', 'so' ) );
        self::assertSame( array( '/php' ), kiriof_test_fixture_php_prefix( '/php', array(), array( 'ctype' ), '', 'so' ) );
    }
}
