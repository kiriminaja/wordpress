<?php
use PHPUnit\Framework\TestCase;

/** The subprocess fixture exits like WordPress, rather than throwing inside the controller's catch. */
final class CallbackExactAuthenticationRuntimeTest extends TestCase {
    private function runScenario( array $overrides = array() ): array {
        $scenario = array_replace(
            array(
                'headers' => array( 'Authorization' => 'Bearer exact-token' ),
                'raw_body' => '{"method":"processed_packages","packages":[{"order_id":"ORDER-1"}]}',
                'token' => 'exact-token',
            ),
            $overrides
        );
        $fixture = <<<'PHP'
namespace KiriminAjaOfficial\Controllers {
    function getallheaders() { return $GLOBALS['scenario']['headers']; }
    function file_get_contents( $filename, $use_include_path = false, $context = null, $offset = 0, $length = null ) {
        $GLOBALS['read_length'] = $length;
        if ( ! empty( $GLOBALS['scenario']['read_failure'] ) ) { return false; }
        $body = $GLOBALS['scenario']['raw_body'];
        if ( isset( $GLOBALS['scenario']['body_size'] ) ) {
            $body = '{"padding":"' . str_repeat( 'a', $GLOBALS['scenario']['body_size'] - 14 ) . '"}';
        }
        return null === $length ? $body : substr( $body, $offset, $length );
    }
}
namespace {
    define( 'ABSPATH', '/' );
    $GLOBALS['scenario'] = json_decode( base64_decode( $argv[1] ), true );
    $GLOBALS['logs'] = array();
    $GLOBALS['reads'] = 0;
    $_SERVER = array( 'REQUEST_METHOD' => $GLOBALS['scenario']['method'] ?? 'POST' );
    if ( isset( $GLOBALS['scenario']['server_authorization'] ) ) {
        $_SERVER['HTTP_AUTHORIZATION'] = $GLOBALS['scenario']['server_authorization'];
    }
    $_SERVER['CONTENT_LENGTH'] = $GLOBALS['scenario']['content_length'] ?? '99999999999999999999';
    $_POST = array( 'packages' => array( array( 'order_id' => 'POST-ONLY' ) ) );
    function getallheaders() { return $GLOBALS['scenario']['headers']; }
    function wp_unslash( $value ) { return stripslashes( $value ); }
    function sanitize_text_field( $value ) {
        return trim( preg_replace( '/%[a-f0-9]{2}/i', '', strip_tags( $value ) ) );
    }
    function kiriof_sanitize_recursive( $value ) {
        if ( is_object( $value ) || is_array( $value ) ) {
            foreach ( $value as $key => $item ) {
                if ( is_object( $value ) ) { $value->$key = kiriof_sanitize_recursive( $item ); }
                else { $value[$key] = kiriof_sanitize_recursive( $item ); }
            }
            return $value;
        }
        return is_string( $value ) ? sanitize_text_field( $value ) : $value;
    }
    function kiriof_log( $level, $message, $context = array() ) {
        $GLOBALS['logs'][] = compact( 'level', 'message', 'context' );
    }
    function send_fixture_response( $success, $data, $status ) {
        echo json_encode( array(
            'success' => $success, 'data' => $data, 'status' => $status,
            'headers' => $GLOBALS['captured_headers'] ?? null,
            'body' => $GLOBALS['captured_body'] ?? null,
            'reads' => $GLOBALS['reads'], 'logs' => $GLOBALS['logs'],
            'read_length' => $GLOBALS['read_length'] ?? null,
        ) );
        exit;
    }
    function wp_send_json_error( $data, $status = 200 ) { send_fixture_response( false, $data, $status ); }
    function wp_send_json_success( $data, $status = 200 ) { send_fixture_response( true, $data, $status ); }
    function wp_die() { exit; }
    $root = $argv[2];
    require $root . '/inc/Utils/ServiceResponse.php';
    require $root . '/inc/Base/BaseService.php';
    require $root . '/inc/Services/CallbackHandlerService.php';
    require $root . '/inc/Controllers/CallbackController.php';
    class ExactAuthenticationHandler extends \KiriminAjaOfficial\Services\CallbackHandlerService {
        public function header( $header ) {
            $GLOBALS['captured_headers'] = $header;
            return parent::header( $header );
        }
        public function body( $body ) {
            $GLOBALS['captured_body'] = $body;
            return parent::body( $body );
        }
    }
    $repository = new class {
        public function getTransactionByOrderIds( $ids ) {
            ++$GLOBALS['reads'];
            if ( ! empty( $GLOBALS['scenario']['throw'] ) ) {
                throw new \RuntimeException( 'SECRET-token buyer@example.com SQL failure /private/database.php' );
            }
            return false;
        }
    };
    $handler = new ExactAuthenticationHandler( $repository, new \stdClass(), $GLOBALS['scenario']['token'] );
    ( new \KiriminAjaOfficial\Controllers\CallbackController( $handler ) )->kiriminAjaCallback();
}
PHP;
        $output = array();
        $status = 0;
        exec(
            escapeshellarg( PHP_BINARY ) . ' -d error_reporting=' . ( E_ALL & ~E_DEPRECATED ) . ' -r ' . escapeshellarg( $fixture ) . ' '
            . escapeshellarg( base64_encode( json_encode( $scenario, JSON_THROW_ON_ERROR ) ) ) . ' '
            . escapeshellarg( PLUGIN_DIR ) . ' 2>&1',
            $output,
            $status
        );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }

    public function test_malformed_tokens_are_not_normalized_into_valid_credentials(): void {
        foreach ( array( 'Bearer exact%20-token', 'Bearer <b>exact-token</b>', 'Bearer exact-\ntoken' ) as $token ) {
            $result = $this->runScenario( array( 'headers' => array( 'Authorization' => $token ) ) );
            $this->assertSame( 401, $result['status'] );
            $this->assertSame( 0, $result['reads'] );
            $this->assertSame( $token, $result['headers']['authorization'] );
        }
    }

    public function test_header_case_bearer_whitespace_and_direct_token_remain_supported(): void {
        foreach ( array( 'exact-token', '  bEaReR exact-token  ' ) as $token ) {
            $result = $this->runScenario( array( 'headers' => array( 'aUtHoRiZaTiOn' => $token ) ) );
            $this->assertSame( 503, $result['status'] );
            $this->assertSame( 1, $result['reads'] );
            $this->assertSame( $token, $result['headers']['authorization'] );
        }
        $result = $this->runScenario( array( 'headers' => array( 'Authorization' => 'Bearer exact%20-token' ), 'token' => 'exact%20-token' ) );
        $this->assertSame( 1, $result['reads'] );
    }

    public function test_server_headers_are_supported_but_array_header_values_are_not(): void {
        $result = $this->runScenario( array( 'headers' => array(), 'server_authorization' => 'Bearer exact-token' ) );
        $this->assertSame( 1, $result['reads'] );
        $result = $this->runScenario( array( 'headers' => array( 'Authorization' => array( 'Bearer exact-token' ) ) ) );
        $this->assertSame( 401, $result['status'] );
        $this->assertSame( 0, $result['reads'] );
        $this->assertArrayNotHasKey( 'authorization', $result['headers'] );
    }

    public function test_raw_identity_values_reach_handler_unchanged_without_post_fallback(): void {
        $body = array(
            'method' => 'processed_packages',
            'packages' => array( array( 'order_id' => 'ORDER%20-1', 'shipment_id' => '<b>SHIP-1</b>', 'reason' => "raw\nreason" ) ),
        );
        $result = $this->runScenario( array( 'raw_body' => json_encode( $body ) ) );
        $this->assertSame( $body, $result['body'] );
        $this->assertSame( 0, $result['reads'] );
        $this->assertSame( 400, $result['status'] );
        $this->assertSame( 'Invalid callback payload', $result['data']['text'] );
    }

    public function test_only_json_objects_are_accepted(): void {
        foreach ( array( '', '{', '[]', '[{"order_id":"ORDER-1"}]', 'null', 'true', '42', '"text"' ) as $body ) {
            $result = $this->runScenario( array( 'raw_body' => $body ) );
            $this->assertSame( 400, $result['status'] );
            $this->assertSame( 0, $result['reads'] );
            $this->assertNull( $result['body'] );
        }
        $result = $this->runScenario( array( 'read_failure' => true ) );
        $this->assertSame( 400, $result['status'] );
    }

    public function test_body_limit_is_enforced_independently_of_content_length(): void {
        $result = $this->runScenario( array( 'body_size' => 2097153, 'content_length' => '1' ) );
        $this->assertSame( 400, $result['status'] );
        $this->assertSame( 2097153, $result['read_length'] );
        $this->assertSame( 0, $result['reads'] );
        $result = $this->runScenario( array( 'body_size' => 2097152 ) );
        $this->assertSame( 'Invalid callback payload', $result['data']['text'] );
    }

    public function test_unexpected_failures_return_retryable_fixed_message_and_safe_log(): void {
        $result = $this->runScenario( array( 'throw' => true ) );
        $this->assertSame( 500, $result['status'] );
        $this->assertSame( 'Unable to process KiriminAja callback. Please retry.', $result['data']['text'] );
        $this->assertSame( array(), $result['data']['data'] );
        $this->assertSame( 'RuntimeException', $result['logs'][0]['context']['exception_class'] );
        $this->assertStringNotContainsString( 'SECRET-token', json_encode( $result ) );
        $this->assertStringNotContainsString( 'buyer@example.com', json_encode( $result ) );
        $this->assertStringNotContainsString( '/private/database.php', json_encode( $result ) );
    }
}
