<?php

use KiriminAjaOfficial\Repositories\ShippingDiscountRegionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( 'esc_sql' ) ) {
    function esc_sql( $value ) {
        return $value;
    }
}

require_once PLUGIN_DIR . '/inc/Repositories/ShippingDiscountRegionRepository.php';

final class ShippingDiscountRegionRepositoryRuntimeTest extends TestCase
{
    private $previous_wpdb;

    protected function setUp(): void
    {
        global $wpdb;
        $this->previous_wpdb = $wpdb ?? null;
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->previous_wpdb;
    }

    #[Test]
    public function repository_exposes_its_database_error(): void
    {
        global $wpdb;
        $wpdb = new ShippingDiscountRegionWpdbFake();
        $wpdb->last_error = 'Province table is unavailable';

        $repository = new ShippingDiscountRegionRepository();

        $this->assertSame( 'Province table is unavailable', $repository->getLastError() );
    }

    #[Test]
    public function repository_returns_empty_string_without_database_error(): void
    {
        global $wpdb;
        $wpdb = new ShippingDiscountRegionWpdbFake();

        $repository = new ShippingDiscountRegionRepository();

        $this->assertSame( '', $repository->getLastError() );
    }
}

final class ShippingDiscountRegionWpdbFake
{
    public string $prefix = 'wp_';
    public string $last_error = '';
}
