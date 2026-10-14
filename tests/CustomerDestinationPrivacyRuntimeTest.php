<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CustomerDestinationPrivacyRuntimeTest extends TestCase {
    private function profile(): array {
        return array(
            '_kiriof_buyer_destination' => array( 'district_id' => '222', 'district_label' => '<b>District</b>', 'destination_latitude' => 0, 'destination_longitude' => '106.8', 'shipping_address' => array( 'address_1' => 'Street' ), 'api_key' => 'SECRET' ),
            '_kiriof_buyer_destination_coordinates' => array( 'latitude' => '0', 'longitude' => '106.8' ),
            '_kiriof_buyer_destination_address' => array( 'address_1' => 'Street' ),
            'shipping_kiriof_destination_area' => '222',
            'shipping_kiriof_destination_area_name' => 'District',
            'shipping_kiriminaja-official/kiriof_destination_area' => '222',
            'shipping_kiriminaja-official/kiriof_destination_area_name' => 'District',
            '_wc_shipping/kiriminaja-official/kiriof_destination_area' => '222',
            '_wc_shipping/kiriminaja-official/kiriof_destination_area_name' => 'District',
            'shipping_address_1' => 'Native street', 'billing_address_1' => 'Billing',
            'billing_kiriof_destination_area' => '999', 'unrelated' => 'Other',
        );
    }

    private function input(): array {
        return array( 'users' => array( 7 => 'buyer@example.com', 8 => 'other@example.com' ), 'email' => 'buyer@example.com', 'meta' => array( 7 => $this->profile(), 8 => $this->profile() ) );
    }

    #[Test]
    public function exports_only_the_requested_account_with_zero_coordinates_and_no_secrets_or_html(): void {
        $input = $this->input();
        $result = $this->runFixture( $input );
        $this->assertTrue( $result['response']['done'] );
        $this->assertSame( 'kiriof-customer-7', $result['response']['data'][0]['item_id'] );
        $this->assertCount( 9, $result['response']['data'][0]['data'] );
        $text = json_encode( $result['response'] );
        $this->assertStringNotContainsString( 'SECRET', $text );
        $this->assertStringNotContainsString( '<b>', $text );
        $destination = json_decode( $result['response']['data'][0]['data'][0]['value'], true );
        $this->assertSame( '0', $destination['destination_latitude'] );
        $this->assertSame( 'Street', $destination['shipping_address']['address_1'] );
        $this->assertSame( $input['meta'], $result['meta'] );
    }

    #[Test]
    public function empty_and_malformed_account_metadata_finishes_without_inventing_values(): void {
        $input = array_replace( $this->input(), array( 'meta' => array( 7 => array() ) ) );
        $this->assertSame( array( 'data' => array(), 'done' => true ), $this->runFixture( $input )['response'] );
        $input['meta'][7] = array(
            '_kiriof_buyer_destination' => array( 'api_key' => 'SECRET', 'shipping_address' => 'malformed' ),
            '_kiriof_buyer_destination_coordinates' => array( 'latitude' => array( 'unexpected' ), 'longitude' => 0 ),
        );
        $result = $this->runFixture( $input );
        $this->assertCount( 1, $result['response']['data'][0]['data'] );
        $this->assertSame( array( 'longitude' => '0' ), json_decode( $result['response']['data'][0]['data'][0]['value'], true ) );
        $this->assertSame( $input['meta'], $result['meta'] );
    }

    #[Test]
    public function unknown_guest_and_later_pages_never_export_or_erase_any_account(): void {
        foreach ( array( array( 'email' => 'guest@example.com' ), array( 'email' => '' ), array( 'page' => 2 ), array( 'page' => 0 ) ) as $changes ) {
            $input = array_replace( $this->input(), $changes );
            $this->assertSame( array( 'data' => array(), 'done' => true ), $this->runFixture( $input )['response'] );
            $result = $this->runFixture( $input + array( 'operation' => 'erase' ) );
            $this->assertFalse( $result['response']['items_removed'] );
            $this->assertFalse( $result['response']['items_retained'] );
            $this->assertSame( $input['meta'], $result['meta'] );
        }
    }

    #[Test]
    public function erases_only_plugin_shipping_keys_and_explicitly_reports_business_retention_on_repeat(): void {
        $result = $this->runFixture( $this->input() + array( 'operation' => 'erase' ) );
        $this->assertSame( array( 'shipping_address_1' => 'Native street', 'billing_address_1' => 'Billing', 'billing_kiriof_destination_area' => '999', 'unrelated' => 'Other' ), $result['meta'][7] );
        $this->assertSame( $this->profile(), $result['meta'][8] );
        $this->assertTrue( $result['response']['items_removed'] );
        $this->assertTrue( $result['response']['items_retained'] );
        $this->assertTrue( $result['response']['done'] );
        $this->assertFalse( $result['repeat']['items_removed'] );
        $this->assertTrue( $result['repeat']['items_retained'] );
        $this->assertStringContainsString( 'financial/legal retention', $result['response']['messages'][0] );
    }

    #[Test]
    public function woo_order_hook_preserves_native_fields_and_durable_booking_and_financial_metadata(): void {
        $snapshot = array( 'context' => array( 'destination' => $this->profile()['_kiriof_buyer_destination'], 'recipient' => array( 'phone' => '08123456789', 'address' => 'Booking street', 'zipcode' => '12345' ), 'origin' => array( 'api_key' => 'ORIGINSECRET' ) ), 'token' => 'TOKEN' );
        $durable = array( '_kiriof_instant_checkout_snapshot' => $snapshot, '_kiriof_instant_checkout_selection' => 'hash', '_kiriof_instant_checkout_invoice' => 'invoice', '_order_total' => '100' );
        $result = $this->runFixture( array( 'operation' => 'order', 'order_meta' => $this->profile() + $durable ) );
        $this->assertSame( 'Native', $result['response'][0]['name'] );
        $text = json_encode( $result['response'] );
        $this->assertStringContainsString( '08123456789', $text );
        $this->assertStringContainsString( 'Booking street', $text );
        $this->assertStringNotContainsString( 'SECRET', $text );
        $this->assertStringNotContainsString( 'TOKEN', $text );
        $this->assertSame( array( 'shipping_address_1' => 'Native street', 'billing_address_1' => 'Billing', 'billing_kiriof_destination_area' => '999', 'unrelated' => 'Other' ) + $durable, $result['order_meta'] );
        $this->assertSame( 2, $result['saves'] );
    }

    #[Test]
    public function registers_standard_wp_and_woo_hooks_without_overwriting_other_providers(): void {
        $result = $this->runFixture( array( 'operation' => 'register' ) );
        $this->assertSame( array( 'existing', 'kiriof-customer-destination' ), $result['exporters'] );
        $this->assertSame( $result['exporters'], $result['erasers'] );
        $this->assertSame( array(
            array( 'wp_privacy_personal_data_exporters', 10, 1 ),
            array( 'wp_privacy_personal_data_erasers', 10, 1 ),
            array( 'woocommerce_privacy_export_order_personal_data', 10, 2 ),
            array( 'woocommerce_privacy_remove_order_personal_data', 10, 1 ),
        ), $result['hooks'] );
    }

    private function runFixture( array $input ): array {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/customer-destination-privacy-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $exit_code );
        $text = implode( "\n", $output );
        $this->assertSame( 0, $exit_code, $text );
        return json_decode( $text, true, 512, JSON_THROW_ON_ERROR );
    }
}
