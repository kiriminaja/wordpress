<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckoutRaceRuntimeTest extends TestCase {
    private const OLD_RATE = 'kiriminaja-official_jne_REG';
    private const NEW_RATE = 'kiriminaja-official_jnt_EZ';

    #[Test]
    public function rate_refresh_preserves_native_instant_instead_of_first_express(): void {
        $instant = 'kiriminaja-instant:7:gosend:instant';
        $native = array( $instant, 'flat_rate:2' );
        $result = $this->runFixture( array(
            'operation' => 'choose',
            'session' => array( 'chosen_shipping_methods' => $native, 'kiriof_chosen_shipping_methods' => array( self::OLD_RATE ) ),
            'selections' => array( array( 'method' => self::OLD_RATE, 'previous' => $instant, 'available' => array( self::OLD_RATE, $instant ) ) ),
        ) );
        $this->assertSame( array( $instant ), $result['methods'] );
        $this->assertSame( $native, $result['session']['chosen_shipping_methods'] );
    }

    #[Test]
    #[DataProvider( 'refreshSelections' )]
    public function refresh_uses_exact_package_selection_without_restoring_shadow( string $previous, array $available, string $default, string $expected ): void {
        $session = array( 'chosen_shipping_methods' => array( $previous ), 'kiriof_chosen_shipping_methods' => array( self::OLD_RATE ) );
        $result = $this->runFixture( array(
            'operation' => 'choose', 'session' => $session,
            'selections' => array( array( 'method' => $default, 'previous' => $previous, 'available' => $available ) ),
        ) );
        $this->assertSame( array( $expected ), $result['methods'] );
        $this->assertSame( $session, $result['session'] );
    }

    public static function refreshSelections(): array {
        $instant = 'kiriminaja-instant:7:gosend:instant';
        return array(
            'Express retained' => array( self::NEW_RATE, array( self::OLD_RATE, self::NEW_RATE ), self::OLD_RATE, self::NEW_RATE ),
            'Instant retained with empty default' => array( $instant, array( self::OLD_RATE, $instant ), '', $instant ),
            'Instant quote removed' => array( $instant, array( self::OLD_RATE ), self::OLD_RATE, self::OLD_RATE ),
            'Express quote removed' => array( self::NEW_RATE, array( $instant ), $instant, $instant ),
            'no rates' => array( $instant, array(), '', '' ),
            'no valid default must not restore shadow' => array( $instant, array( self::OLD_RATE ), '', '' ),
        );
    }

    #[Test]
    #[DataProvider( 'multiPackageRequests' )]
    public function overlapping_package_rates_do_not_apply_package_zero_request_to_other_packages( array $post ): void {
        $instant = 'kiriminaja-instant:7:gosend:instant';
        $native = array( self::NEW_RATE, $instant );
        $session = array( 'chosen_shipping_methods' => $native, 'kiriof_chosen_shipping_methods' => array( self::OLD_RATE ) );
        $result = $this->runFixture( array(
            'operation' => 'choose', 'session' => $session, 'post' => $post, 'route' => '/wc/store/v1/cart/select-shipping-rate',
            'selections' => array_map( static fn( $previous ) => array( 'method' => self::OLD_RATE, 'previous' => $previous, 'available' => array( self::OLD_RATE, self::NEW_RATE, $instant ) ), $native ),
        ) );
        $this->assertSame( $native, $result['methods'] );
        $this->assertSame( $session, $result['session'] );
    }

    public static function multiPackageRequests(): array {
        return array(
            'classic' => array( array( 'shipping_method' => array( self::NEW_RATE, 'kiriminaja-instant:7:gosend:instant' ) ) ),
            'legacy AJAX' => array( array( 'shipping_metode_id' => self::NEW_RATE ) ),
            'Store API package zero' => array( array( 'rate_id' => self::NEW_RATE, 'package_id' => 0 ) ),
        );
    }

    #[Test]
    public function native_selection_wins_without_collapsing_multiple_packages(): void {
        $native = array( self::NEW_RATE, 'flat_rate:2' );
        $result = $this->runFixture( array(
            'operation' => 'choose',
            'session' => array( 'chosen_shipping_methods' => $native, 'kiriof_chosen_shipping_methods' => array( self::OLD_RATE ) ),
            'selections' => array(
                array( 'method' => self::NEW_RATE, 'available' => array( self::OLD_RATE, self::NEW_RATE ) ),
                array( 'method' => 'flat_rate:2', 'available' => array( self::OLD_RATE, 'flat_rate:2' ) ),
            ),
        ) );
        $this->assertSame( $native, $result['methods'] );
        $this->assertSame( $native, $result['session']['chosen_shipping_methods'] );
    }

    #[Test]
    public function explicit_store_api_selection_does_not_collapse_package_session(): void {
        $native = array( self::NEW_RATE, 'flat_rate:2' );
        $result = $this->runFixture( array(
            'operation' => 'choose', 'post' => array( 'rate_id' => self::NEW_RATE ), 'route' => '/wc/store/v1/cart/select-shipping-rate',
            'session' => array( 'chosen_shipping_methods' => $native ),
            'selections' => array( array( 'method' => self::OLD_RATE, 'previous' => self::NEW_RATE, 'available' => array( self::OLD_RATE, self::NEW_RATE ) ) ),
        ) );
        $this->assertSame( array( self::NEW_RATE ), $result['methods'] );
        $this->assertSame( $native, $result['session']['chosen_shipping_methods'] );
    }

    #[Test]
    #[DataProvider( 'explicitSelections' )]
    public function explicit_request_selection_still_wins( array $post, string $route, string $expected = self::NEW_RATE ): void {
        $result = $this->runFixture( array(
            'operation' => 'choose', 'post' => $post, 'route' => $route,
            'session' => array( 'kiriof_chosen_shipping_methods' => array( self::OLD_RATE ) ),
            'selections' => array( array( 'method' => self::OLD_RATE, 'available' => array( self::OLD_RATE, self::NEW_RATE, $expected ) ) ),
        ) );
        $this->assertSame( array( $expected ), $result['methods'] );
    }

    public static function explicitSelections(): array {
        return array(
            'classic' => array( array( 'shipping_method' => array( self::NEW_RATE ) ), '/wc/store/v1/cart' ),
            'legacy AJAX' => array( array( 'shipping_metode_id' => self::NEW_RATE ), '/wc/store/v1/cart' ),
            'Store API' => array( array( 'rate_id' => self::NEW_RATE ), '/wc/store/v1/cart/select-shipping-rate' ),
            'classic Instant' => array( array( 'shipping_method' => array( 'kiriminaja-instant:7:gosend:instant' ) ), '/wc/store/v1/cart', 'kiriminaja-instant:7:gosend:instant' ),
            'AJAX Instant' => array( array( 'shipping_metode_id' => 'kiriminaja-instant:7:gosend:instant' ), '/wc/store/v1/cart', 'kiriminaja-instant:7:gosend:instant' ),
            'Store API Instant' => array( array( 'rate_id' => 'kiriminaja-instant:7:gosend:instant' ), '/wc/store/v1/cart/select-shipping-rate', 'kiriminaja-instant:7:gosend:instant' ),
        );
    }

    #[Test]
    public function invalid_native_selection_keeps_legacy_mirror_fallback(): void {
        $result = $this->runFixture( array(
            'operation' => 'choose', 'session' => array( 'kiriof_chosen_shipping_methods' => array( self::OLD_RATE ) ),
            'selections' => array( array( 'method' => 'missing', 'available' => array( self::OLD_RATE ) ) ),
        ) );
        $this->assertSame( array( self::OLD_RATE ), $result['methods'] );
    }

    #[Test]
    #[DataProvider( 'syncActions' )]
    public function sync_checkout_does_not_select_or_clear_courier_but_legacy_does( ?string $action, int $destination, string $payloadRate, array $expected ): void {
        $session = array( 'chosen_shipping_methods' => array( self::NEW_RATE, 'flat_rate:2' ), 'kiriof_chosen_shipping_methods' => array( self::NEW_RATE, 'flat_rate:2' ), 'kiriof_expedition' => 'jnt_EZ' );
        $data = array( 'destination_id' => $destination, 'shipping_metode_id' => $payloadRate, 'payment_method' => 'bacs', 'insurance' => 1 );
        if ( null !== $action ) { $data['action'] = $action; }
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => $session, 'data' => $data ) );
        $this->assertSame( $expected, $result['session']['chosen_shipping_methods'] );
        $this->assertSame( $expected, $result['session']['kiriof_chosen_shipping_methods'] );
        $this->assertSame( 'sync_checkout' === $action ? 'jnt_EZ' : ( $payloadRate ? 'jne_REG' : '' ), $result['session']['kiriof_expedition'] );
        $this->assertSame( $destination > 0 ? $destination : '', $result['session']['destination_id'] );
        $this->assertSame( 1, $result['session']['kiriof_insurance'] );
        $this->assertSame( 'bacs', $result['session']['chosen_payment_method'] );
    }

    public static function syncActions(): array {
        $native = array( self::NEW_RATE, 'flat_rate:2' );
        return array(
            'sync stale courier' => array( 'sync_checkout', 222, self::OLD_RATE, $native ),
            'sync empty destination' => array( 'sync_checkout', 0, '', $native ),
            'legacy courier' => array( null, 222, self::OLD_RATE, array( self::OLD_RATE ) ),
            'legacy clear' => array( null, 0, '', array() ),
            'other legacy action' => array( 'update_checkout', 222, self::OLD_RATE, array( self::OLD_RATE ) ),
        );
    }

    #[Test]
    public function bare_order_method_keeps_native_courier_service(): void {
        $result = $this->runFixture( array(
            'operation' => 'order', 'order_methods' => array( 'kiriminaja-official' ),
            'session' => array( 'chosen_shipping_methods' => array( self::NEW_RATE ), 'destination_id' => '222' ),
        ) );
        $this->assertSame( 'jnt_EZ', $result['meta']['_kiriof_checkout_expedition'] );
    }

    #[Test]
    #[DataProvider( 'districtCases' )]
    public function submitted_district_has_precedence_and_names_stay_paired( array $params, array $session, string $id, string $name ): void {
        $session['chosen_shipping_methods'] = array( self::NEW_RATE );
        $result = $this->runFixture( array( 'operation' => 'order', 'params' => $params, 'session' => $session, 'meta' => array( '_shipping_kiriof_destination_area' => 'stale', '_shipping_kiriof_destination_name' => 'stale' ) ) );
        $this->assertSame( $id, $result['meta']['_kiriof_checkout_destination_area'] );
        $this->assertSame( $name, $result['meta']['_kiriof_checkout_destination_area_name'] );
        $this->assertSame( $id, $result['meta']['_shipping_kiriof_destination_area'] );
        $this->assertSame( $name, $result['meta']['_shipping_kiriof_destination_name'] );
    }

    public static function districtCases(): array {
        $session = array( 'kiriof_destination_area' => '111', 'kiriof_destination_area_name' => 'Old district' );
        $field = static fn( $id ) => array( 'shipping_address' => array( 'additional_fields' => array( 'kiriminaja-official/kiriof_destination_area' => $id ) ) );
        return array(
            'submitted new district' => array( $field( '222' ), $session, '222', '' ),
            'explicitly empty clears metadata' => array( $field( '' ), $session, '', '' ),
            'same district keeps name' => array( $field( '111' ), $session, '111', 'Old district' ),
            'missing field legacy session' => array( array(), $session, '111', 'Old district' ),
            'matching shipping pair' => array( $field( '222' ), $session + array( 'shipping_destination_id' => '222', 'shipping_destination_name' => 'New district' ), '222', 'New district' ),
            'name field not mistaken for ID' => array( array( 'kiriof_destination_area_name' => 'Wrong ID' ), $session, '111', 'Old district' ),
            'shipping beats billing' => array( $field( '222' ) + array( 'billing_address' => array( 'additional_fields' => array( 'kiriminaja-official/kiriof_destination_area' => '111' ) ) ), $session, '222', '' ),
        );
    }

    private function runFixture( array $input ): array {
        $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-race-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1';
        exec( $command, $output, $exitCode );
        $text = implode( "\n", $output );
        $this->assertSame( 0, $exitCode, $text );
        return json_decode( $text, true, 512, JSON_THROW_ON_ERROR );
    }
}
