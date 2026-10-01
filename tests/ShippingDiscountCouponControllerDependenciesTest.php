<?php

declare(strict_types=1);

use KiriminAjaOfficial\Controllers\ShippingDiscountCouponController;
use KiriminAjaOfficial\Repositories\ShippingDiscountRegionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'esc_sql' ) ) {
    function esc_sql( $value ) {
        return $value;
    }
}

require_once PLUGIN_DIR . '/inc/Repositories/ShippingDiscountRegionRepository.php';
require_once PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php';

final class ShippingDiscountCouponControllerDependenciesTest extends TestCase {
    #[Test]
    public function constructor_remains_callable_without_arguments(): void {
        $previous_wpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = (object) array( 'prefix' => 'wp_' );

        try {
            $controller = new ShippingDiscountCouponController();

            $this->assertInstanceOf( ShippingDiscountCouponController::class, $controller );
            $this->assertSame( 0, ( new ReflectionMethod( $controller, '__construct' ) )->getNumberOfRequiredParameters() );
        } finally {
            if ( null === $previous_wpdb ) {
                unset( $GLOBALS['wpdb'] );
            } else {
                $GLOBALS['wpdb'] = $previous_wpdb;
            }
        }
    }

    #[Test]
    public function constructor_reuses_injected_region_repository(): void {
        $repository = $this->getMockBuilder( ShippingDiscountRegionRepository::class )
            ->disableOriginalConstructor()
            ->getMock();

        $controller = new ShippingDiscountCouponController( $repository );
        $property = ( new ReflectionClass( $controller ) )->getProperty( 'region_repository' );

        $this->assertSame( $repository, $property->getValue( $controller ) );
    }

    #[Test]
    public function repository_construction_is_confined_to_optional_constructor_default(): void {
        $source = file_get_contents( PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php' );

        $this->assertIsString( $source );
        $this->assertStringContainsString( '?ShippingDiscountRegionRepository $region_repository = null', $source );
        $this->assertSame( 1, substr_count( $source, 'new ShippingDiscountRegionRepository()' ) );
        $this->assertGreaterThanOrEqual( 4, substr_count( $source, '$this->region_repository' ) );
    }
}
