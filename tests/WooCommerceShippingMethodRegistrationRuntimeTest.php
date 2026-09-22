<?php

declare(strict_types=1);

use KiriminAjaOfficial\Contracts\ShippingZoneMethodRepositoryInterface;
use KiriminAjaOfficial\Repositories\ShippingZoneMethodRepository;
use KiriminAjaOfficial\Services\WooCommerceShippingMethodRegistrationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $key, $default = false ) {
        return $GLOBALS['shipping_method_test_options'][ $key ] ?? $default;
    }
}

if ( ! function_exists( 'do_action' ) ) {
    function do_action( $hook_name, ...$args ) {
        $GLOBALS['shipping_method_test_actions'][] = array( $hook_name, $args );
    }
}

if ( ! class_exists( 'WC_Cache_Helper' ) ) {
    class WC_Cache_Helper {
        public static array $calls = array();

        public static function get_transient_version( $group, $refresh = false ) {
            self::$calls[] = array( $group, $refresh );

            return '1';
        }
    }
}

require_once PLUGIN_DIR . '/inc/Contracts/ShippingZoneMethodRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Repositories/ShippingZoneMethodRepository.php';
require_once PLUGIN_DIR . '/inc/Services/WooCommerceShippingMethodRegistrationService.php';

final class WooCommerceShippingMethodRegistrationRuntimeTest extends TestCase
{
    private $previous_wpdb;

    protected function setUp(): void
    {
        global $wpdb;
        $this->previous_wpdb                       = $wpdb ?? null;
        $GLOBALS['shipping_method_test_options'] = array();
        $GLOBALS['shipping_method_test_actions'] = array();
        WC_Cache_Helper::$calls                  = array();
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->previous_wpdb;
    }

    #[Test]
    public function repository_enables_shipping_method_instance(): void
    {
        global $wpdb;
        $wpdb = new ShippingZoneMethodWpdbFake( 1 );

        $result = ( new ShippingZoneMethodRepository() )->enable( 42 );

        $this->assertTrue( $result );
        $this->assertSame( 'wp_woocommerce_shipping_zone_methods', $wpdb->table );
        $this->assertSame( array( 'is_enabled' => 1 ), $wpdb->data );
        $this->assertSame( array( 'instance_id' => 42 ), $wpdb->where );
        $this->assertSame( array( '%d' ), $wpdb->data_format );
        $this->assertSame( array( '%d' ), $wpdb->where_format );
    }

    #[Test]
    public function repository_treats_zero_affected_rows_as_success(): void
    {
        global $wpdb;
        $wpdb = new ShippingZoneMethodWpdbFake( 0 );

        $this->assertTrue( ( new ShippingZoneMethodRepository() )->enable( 42 ) );
    }

    #[Test]
    public function default_constructor_uses_wordpress_shipping_zone_repository(): void
    {
        global $wpdb;
        $wpdb    = new ShippingZoneMethodWpdbFake( 1 );
        $service = new WooCommerceShippingMethodRegistrationService();

        $this->enableMethodInstance( $service, 16, 8 );

        $this->assertSame( array( 'instance_id' => 16 ), $wpdb->where );
        $this->assertCount( 1, $GLOBALS['shipping_method_test_actions'] );
        $this->assertSame( array( array( 'shipping', true ) ), WC_Cache_Helper::$calls );
    }

    #[Test]
    public function successful_persistence_triggers_status_hook_and_cache_invalidation(): void
    {
        $repository = new ShippingZoneMethodRepositoryFake( true );
        $service    = new WooCommerceShippingMethodRegistrationService( $repository );

        $this->enableMethodInstance( $service, 17, 9 );

        $this->assertSame( array( 17 ), $repository->instance_ids );
        $this->assertSame(
            array(
                array(
                    'woocommerce_shipping_zone_method_status_toggled',
                    array( 17, 'kiriminaja-official', 9, 1 ),
                ),
            ),
            $GLOBALS['shipping_method_test_actions']
        );
        $this->assertSame( array( array( 'shipping', true ) ), WC_Cache_Helper::$calls );
        $this->assertSame(
            array( 'enabled' => 'yes', 'title' => 'KiriminAja' ),
            $GLOBALS['shipping_method_test_options']['woocommerce_kiriminaja-official_17_settings']
        );
    }

    #[Test]
    public function failed_persistence_does_not_trigger_status_hook_or_cache_invalidation(): void
    {
        $repository = new ShippingZoneMethodRepositoryFake( false );
        $service    = new WooCommerceShippingMethodRegistrationService( $repository );

        $this->enableMethodInstance( $service, 18, 10 );

        $this->assertSame( array( 18 ), $repository->instance_ids );
        $this->assertSame( array(), $GLOBALS['shipping_method_test_actions'] );
        $this->assertSame( array(), WC_Cache_Helper::$calls );
        $this->assertSame(
            array( 'enabled' => 'yes', 'title' => 'KiriminAja' ),
            $GLOBALS['shipping_method_test_options']['woocommerce_kiriminaja-official_18_settings']
        );
    }

    private function enableMethodInstance( WooCommerceShippingMethodRegistrationService $service, int $instance_id, int $zone_id ): void
    {
        $method = new ReflectionMethod( $service, 'enableMethodInstance' );
        $method->invoke( $service, $instance_id, $zone_id );
    }
}

final class ShippingZoneMethodWpdbFake
{
    public string $prefix = 'wp_';
    public string $table = '';
    public array $data = array();
    public array $where = array();
    public array $data_format = array();
    public array $where_format = array();
    private $result;

    public function __construct( $result )
    {
        $this->result = $result;
    }

    public function update( $table, $data, $where, $data_format, $where_format )
    {
        $this->table        = $table;
        $this->data         = $data;
        $this->where        = $where;
        $this->data_format  = $data_format;
        $this->where_format = $where_format;

        return $this->result;
    }
}

final class ShippingZoneMethodRepositoryFake implements ShippingZoneMethodRepositoryInterface
{
    public array $instance_ids = array();
    private bool $result;

    public function __construct( bool $result )
    {
        $this->result = $result;
    }

    public function enable( int $instance_id ): bool
    {
        $this->instance_ids[] = $instance_id;

        return $this->result;
    }
}
