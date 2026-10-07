<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CodGatewayRecursionRuntimeTest extends TestCase {
    public static function gatewayCases(): iterable {
        foreach ( array( true, false, null ) as $eligibility ) {
            foreach ( array( false, true ) as $nativeCod ) {
                yield 'eligibility ' . var_export( $eligibility, true ) . ' native ' . (int) $nativeCod => array(
                    array( 'eligibility' => $eligibility, 'native_cod' => $nativeCod ),
                    $nativeCod && false !== $eligibility,
                    false === $eligibility || $nativeCod ? 1 : 2,
                );
            }
        }
        yield 'preceding filter hides COD' => array( array( 'native_cod' => true, 'remove_cod' => true ), false, 2 );
        yield 'following filter hides COD' => array( array( 'native_cod' => true, 'remove_cod_after' => true ), false, 1 );
        yield 'fallback honors following filter' => array( array( 'native_cod' => true, 'remove_cod_after' => true, 'direct' => true ), false, 1 );
        yield 'plugin disabled' => array( array( 'enable_cod' => 'no' ), false, 1 );
        yield 'native COD disabled' => array( array( 'enabled' => 'no' ), false, 1 );
        yield 'no wildcard' => array( array( 'wildcard' => false ), false, 1 );
        yield 'non KiriminAja preserves native COD' => array( array( 'native_cod' => true, 'method' => 'flat_rate:1', 'eligibility' => false ), true, 1 );
        yield 'non checkout preserves native COD' => array( array( 'native_cod' => true, 'checkout' => false, 'eligibility' => false ), true, 1 );
        yield 'virtual cart removes COD outside checkout' => array( array( 'native_cod' => true, 'checkout' => false, 'needs_shipping' => false ), false, 1 );
        yield 'empty chosen methods' => array( array( 'method' => '' ), false, 1 );
        yield 'direct fallback may restore an available COD only' => array( array( 'native_cod' => true, 'direct' => true ), true, 1 );
    }

    #[DataProvider( 'gatewayCases' )]
    public function test_filter_is_bounded_and_preserves_gateway_availability( array $scenario, bool $cod, int $calls ): void {
        $result = $this->runFixture( $scenario );
        $this->assertSame( $cod, in_array( 'cod', $result['gateways'], true ) );
        $this->assertSame( $calls, $result['calls'] );
        $this->assertSame( ( $scenario['direct'] ?? false ) ? 1 : $calls, $result['max_depth'] );
        $this->assertContains( 'bacs', $result['gateways'] );
        $this->assertNotContains( 'unavailable', $result['gateways'], 'Fallback must not resurrect unrelated unavailable gateways.' );
        if ( false === ( $scenario['eligibility'] ?? null ) && 'flat_rate:1' !== ( $scenario['method'] ?? '' ) && ( $scenario['checkout'] ?? true ) ) {
            foreach ( array( 'chosen_payment_method', 'payment_method', 'kiriof_payment_method' ) as $key ) {
                $this->assertSame( '', $result['session'][ $key ] );
            }
        }
    }

    public function test_fallback_exception_releases_guard_for_subsequent_calls(): void {
        $result = $this->runFixture( array( 'exception' => true, 'native_cod' => true, 'direct' => true ) );
        $this->assertSame( 'Gateway fixture failure', $result['exception'] );
        $this->assertContains( 'cod', $result['gateways'] );
        $this->assertSame( 2, $result['calls'] );
        $this->assertSame( 1, $result['max_depth'] );
    }

    private function runFixture( array $scenario ): array {
        // A standalone process exercises the real controller without leaking
        // WooCommerce/WordPress function stubs into the rest of the test suite.
        $fixture = <<<'PHP'
<?php
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository {
        public function getSettingByKey( $key ) { return (object) array( 'value' => $GLOBALS['scenario']['enable_cod'] ); }
    }
}
namespace {
    define( 'ABSPATH', __DIR__ );
    $scenario = array_replace( array(
        'eligibility' => true, 'native_cod' => false, 'remove_cod' => false,
        'remove_cod_after' => false, 'enable_cod' => 'yes', 'enabled' => 'yes',
        'wildcard' => true, 'method' => 'kiriminaja-official:jne:REG',
        'checkout' => true, 'needs_shipping' => true, 'exception' => false, 'direct' => false,
    ), json_decode( $argv[2], true, 512, JSON_THROW_ON_ERROR ) );
    function WC() { return $GLOBALS['wc']; }
    function is_checkout() { return $GLOBALS['scenario']['checkout']; }
    function get_option( $key, $default = array() ) {
        return array( 'enabled' => $GLOBALS['scenario']['enabled'], 'enable_for_methods' => $GLOBALS['scenario']['wildcard'] ? array( 'kiriminaja-official' ) : array() );
    }
    function apply_filters( $hook, $gateways ) {
        if ( $GLOBALS['scenario']['remove_cod'] ) { unset( $gateways['cod'] ); }
        $gateways = $GLOBALS['controller']->kiriof_filter_cod_availability( $gateways );
        if ( $GLOBALS['scenario']['remove_cod_after'] ) { unset( $gateways['cod'] ); }
        return $gateways;
    }
    class GatewayManager {
        public int $calls = 0;
        public int $depth = 0;
        public int $maxDepth = 0;
        public function get_available_payment_gateways() {
            ++$this->calls;
            ++$this->depth;
            $this->maxDepth = max( $this->maxDepth, $this->depth );
            try {
                if ( $this->depth > 3 ) { throw new \RuntimeException( 'Recursive gateway lookup' ); }
                if ( $GLOBALS['scenario']['exception'] ) {
                    $GLOBALS['scenario']['exception'] = false;
                    throw new \RuntimeException( 'Gateway fixture failure' );
                }
                // Simulate native is_available(), not the unfiltered registry:
                // the registered but unavailable gateway is never returned.
                $gateways = array( 'bacs' => (object) array( 'id' => 'bacs' ) );
                if ( $GLOBALS['scenario']['native_cod'] ) { $gateways['cod'] = (object) array( 'id' => 'cod' ); }
                return apply_filters( 'woocommerce_available_payment_gateways', $gateways );
            } finally { --$this->depth; }
        }
    }
    $manager = new GatewayManager();
    $wc = new class( $manager ) {
        public $session;
        public $cart;
        private $manager;
        public function __construct( $manager ) {
            $this->manager = $manager;
            $this->cart = new class { public function needs_shipping() { return $GLOBALS['scenario']['needs_shipping']; } };
            $this->session = new class {
                public array $data = array();
                public function get( $key, $default = null ) { return $this->data[$key] ?? $default; }
                public function set( $key, $value ) { $this->data[$key] = $value; }
                public function __unset( $key ) { unset( $this->data[$key] ); }
            };
        }
        public function payment_gateways() { return $this->manager; }
    };
    $wc->session->data = array(
        'chosen_shipping_methods' => '' === $scenario['method'] ? array() : array( $scenario['method'] ),
        'chosen_payment_method' => 'cod', 'payment_method' => 'cod', 'kiriof_payment_method' => 'cod',
    );
    if ( null !== $scenario['eligibility'] ) {
        $wc->session->set( 'kiriof_shipping_coupon_rate_meta', array( $scenario['method'] => array( 'cod_available' => $scenario['eligibility'] ? 'yes' : 'no' ) ) );
    }
    require $argv[1] . '/inc/Controllers/CheckoutController.php';
    $reflection = new \ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class );
    $controller = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty( 'setting_repository' )->setValue( $controller, new \KiriminAjaOfficial\Repositories\SettingRepository() );
    $exception = null;
    $invoke = function () use ( $scenario, $controller, $manager ) {
        return $scenario['direct'] ? $controller->kiriof_filter_cod_availability( array( 'bacs' => (object) array( 'id' => 'bacs' ) ) ) : $manager->get_available_payment_gateways();
    };
    try { $gateways = $invoke(); } catch ( \RuntimeException $error ) {
        $exception = $error->getMessage();
        if ( 'Gateway fixture failure' !== $exception ) { throw $error; }
        $gateways = $invoke();
    }
    echo json_encode( array( 'gateways' => array_keys( $gateways ), 'calls' => $manager->calls, 'max_depth' => $manager->maxDepth, 'session' => $wc->session->data, 'exception' => $exception ), JSON_THROW_ON_ERROR );
}
PHP;
        $path = tempnam( sys_get_temp_dir(), 'kiriof-cod-' );
        try {
            file_put_contents( $path, $fixture );
            exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $path ) . ' ' . escapeshellarg( dirname( __DIR__ ) ) . ' ' . escapeshellarg( json_encode( $scenario, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
            $this->assertSame( 0, $status, implode( "\n", $output ) );
            return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
        } finally {
            unlink( $path );
        }
    }
}
