<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/helpers/legacy-checkout-source.php';

final class CheckoutCountryCompatibilityRuntimeTest extends TestCase {
    #[Test]
    #[DataProvider( 'countryPairs' )]
    public function native_fields_remain_unchanged_and_district_follows_each_address_country( string $billing, string $shipping ): void {
        $result = $this->runFixture( array(
            'action' => 'fields',
            'post' => array( 'billing_country' => $billing, 'shipping_country' => $shipping, 'ship_to_different_address' => '1' ),
        ) );
        $this->assertAddressFields( $result, $billing, $shipping );
        $this->assertSame( array(), $result['checkout_reads'], 'Posted countries must take precedence over checkout defaults.' );
    }

    public static function countryPairs(): array {
        return array(
            'both Indonesian' => array( 'ID', 'ID' ),
            'both foreign' => array( 'US', 'US' ),
            'Indonesian billing, foreign shipping' => array( 'ID', 'US' ),
            'foreign billing, Indonesian shipping' => array( 'US', 'ID' ),
            'different foreign locales' => array( 'GB', 'DE' ),
        );
    }

    #[Test]
    #[DataProvider( 'countryPairs' )]
    public function fields_removed_by_other_plugins_are_not_recreated( string $billing, string $shipping ): void {
        $removed = array();
        foreach ( array( 'billing', 'shipping' ) as $group ) {
            $removed[ $group ] = array( $group . '_state', $group . '_company', $group . '_postcode' );
        }
        $result = $this->runFixture( array(
            'action' => 'fields',
            'post' => array( 'billing_country' => $billing, 'shipping_country' => $shipping ),
            'unset_fields' => $removed,
        ) );
        foreach ( $removed as $group => $keys ) {
            foreach ( $keys as $key ) {
                $this->assertArrayNotHasKey( $key, $result['original_fields'][ $group ] );
                $this->assertArrayNotHasKey( $key, $result['fields'][ $group ] );
            }
        }
    }

    #[Test]
    #[DataProvider( 'countryPairs' )]
    public function custom_required_company_is_preserved_for_every_country( string $billing, string $shipping ): void {
        $result = $this->runFixture( array(
            'action' => 'fields',
            'post' => array( 'billing_country' => $billing, 'shipping_country' => $shipping ),
            'field_overrides' => array(
                'billing' => array( 'billing_company' => array( 'required' => true ) ),
                'shipping' => array( 'shipping_company' => array( 'required' => true ) ),
            ),
        ) );
        $this->assertAddressFields( $result, $billing, $shipping );
    }

    #[Test]
    public function city_and_province_use_adjacent_native_rows_and_preserve_theme_metadata(): void {
        $overrides = array();
        foreach ( array( 'billing', 'shipping' ) as $group ) {
            $overrides[ $group ] = array(
                $group . '_city' => array( 'priority' => 70, 'class' => array( 'form-row-wide', 'address-field', 'theme-city' ) ),
                $group . '_state' => array( 'priority' => 90, 'class' => array( 'form-row-wide', 'address-field', 'theme-province' ) ),
            );
        }
        $result = $this->runFixture( array(
            'action' => 'fields',
            'post' => array( 'billing_country' => 'ID', 'shipping_country' => 'US' ),
            'field_overrides' => $overrides,
        ) );
        $this->assertAddressFields( $result, 'ID', 'US' );
        foreach ( array( 'billing', 'shipping' ) as $group ) {
            $this->assertSame( 70, $result['fields'][ $group ][ $group . '_city' ]['priority'] );
            $this->assertSame( 71, $result['fields'][ $group ][ $group . '_state' ]['priority'] );
            $this->assertContains( 'theme-city', $result['fields'][ $group ][ $group . '_city' ]['class'] );
            $this->assertContains( 'theme-province', $result['fields'][ $group ][ $group . '_state' ]['class'] );
        }
    }

    #[Test]
    #[DataProvider( 'countryPairs' )]
    public function virtual_cart_returns_original_fields_unchanged( string $billing, string $shipping ): void {
        $result = $this->runFixture( array(
            'action' => 'fields',
            'needs_shipping' => false,
            'post' => array( 'billing_country' => $billing, 'shipping_country' => $shipping ),
        ) );
        $this->assertSame( $result['original_fields'], $result['fields'] );
        $this->assertSame( array(), $result['checkout_reads'] );
    }

    #[Test]
    public function classic_district_script_uses_edited_address_and_updates_label_before_calculation_guard(): void {
        $script = kiriof_legacy_checkout_source();
        $change = substr( $script, strpos( $script, 'function changeDistrict(){' ) );
        $change = substr( $change, 0, strpos( $change, 'function getSearchAreaKelurahan()' ) );
        $this->assertStringContainsString( "var addressType = root.attr('id') === 'kiriof_shipping_destination_area' ? 'shipping' : 'billing';", $change );
        $this->assertStringNotContainsString( "let addressType = different_address", $change );
        $this->assertStringContainsString( 'var country = kiriofGetClassicAddressCountry(addressType);', $change );
        $this->assertStringContainsString( "jQuery('#' + addressType + '_country')", $script );
        $label = strpos( $change, 'kiriofSetClassicDistrictLabel(root, label, differentAddress);' );
        $guard = strpos( $change, "if (kiriofBillingAddressConfig.isCheckout && addressType !== (differentAddress ? 'shipping' : 'billing')) {" );
        $this->assertNotFalse( $label );
        $this->assertNotFalse( $guard );
        $this->assertLessThan( $guard, $label, 'Inactive address edits must still update their district label.' );
        $this->assertMatchesRegularExpression( "/addressType !== \\(differentAddress \\? 'shipping' : 'billing'\\)\\)\\s*\\{\\s*return;/", $change );
        $this->assertStringContainsString( "on('country_to_state_changing.kiriofClassicAddress updated_checkout.kiriofClassicAddress', kiriofSyncClassicAddressFields)", $script );
        $this->assertMatchesRegularExpression( '/function kiriofSyncClassicAddressFields\(\)\s*\{\s*if \(!kiriofBillingAddressConfig.isCheckout \|\| kiriofIsBlockCheckoutContext\(\)\)\s*\{\s*return;/', $script );
    }

    #[Test]
    #[DataProvider( 'countryPairs' )]
    public function missing_post_countries_use_checkout_get_value( string $billing, string $shipping ): void {
        $result = $this->runFixture( array(
            'action' => 'fields',
            'checkout_values' => array( 'billing_country' => $billing, 'shipping_country' => $shipping ),
        ) );
        $this->assertAddressFields( $result, $billing, $shipping );
        $this->assertContains( 'billing_country', $result['checkout_reads'] );
        $this->assertContains( 'shipping_country', $result['checkout_reads'] );
        $this->assertArrayNotHasKey( 'billing_country', $result['post'] );
        $this->assertArrayNotHasKey( 'shipping_country', $result['post'] );
    }

    private function assertAddressFields( array $result, string $billing, string $shipping ): void {
        foreach ( array( 'billing' => $billing, 'shipping' => $shipping ) as $group => $country ) {
            $phone = $result['fields'][ $group ][ $group . '_phone' ];
            $this->assertSame( 91, $phone['priority'] );
            $this->assertContains( 'form-row-last', $phone['class'] );
            $this->assertTrue( $phone['required'] );
            $this->assertSame( $result['original_fields'][ $group ][ $group . '_email' ], $result['fields'][ $group ][ $group . '_email' ], 'Email schema and validation remain native.' );
            foreach ( array( 'state', 'city', 'company', 'postcode' ) as $native ) {
                $key = $group . '_' . $native;
                $this->assertArrayHasKey( $key, $result['fields'][ $group ], $key . ' must remain a native WooCommerce field.' );
                $original = $result['original_fields'][ $group ][ $key ];
                $field = $result['fields'][ $group ][ $key ];
                if ( 'company' !== $native ) {
                    $side = 'state' === $native ? 'form-row-last' : 'form-row-first';
                    $priority = array( 'city' => 70, 'state' => 71, 'postcode' => 90 );
                    $this->assertContains( $side, $field['class'] );
                    $this->assertSame( $priority[ $native ], $field['priority'] );
                    $this->assertFalse( $field['clear'] );
                    $original['class'] = array_values( array_diff( $original['class'] ?? array(), array( 'form-row-wide', 'form-row-first', 'form-row-last' ) ) );
                    $field['class'] = array_values( array_diff( $field['class'], array( 'form-row-wide', 'form-row-first', 'form-row-last' ) ) );
                    unset( $original['priority'], $field['priority'], $original['clear'], $field['clear'] );
                }
                $this->assertSame( $original, $field, $key . ' must retain native validation and non-layout metadata.' );
            }
            $countryKey = $group . '_country';
            $this->assertSame( $result['original_fields'][ $group ][ $countryKey ], $result['fields'][ $group ][ $countryKey ], 'Country field must be untouched.' );
            $districtKey = 'billing' === $group ? 'kiriof_destination_area' : 'kiriof_shipping_destination_area';
            $district = $result['fields'][ $group ][ $districtKey ];
            $this->assertSame( 'ID' === $country, $district['required'], $districtKey );
            $this->assertSame( 'ID' !== $country, in_array( 'kiriof-classic-address-hidden', $district['class'], true ), $districtKey );
        }
    }

    #[Test]
    public function district_options_read_customer_meta_and_prefer_session_values(): void {
        $input = array(
            'action' => 'fields',
            'post' => array( 'billing_country' => 'ID', 'shipping_country' => 'ID' ),
            'customer_meta' => array(
                'billing_kiriof_destination_area' => '101',
                'billing_kiriof_destination_area_name' => 'Saved billing district',
                'shipping_kiriof_destination_area' => '202',
                'shipping_kiriof_destination_area_name' => 'Saved shipping district',
            ),
        );
        $saved = $this->runFixture( $input );
        $this->assertSame( 'Saved billing district', $saved['fields']['billing']['kiriof_destination_area']['options'][101] );
        $this->assertSame( '101', $saved['fields']['billing']['kiriof_destination_area']['default'] );
        $this->assertSame( 'Saved shipping district', $saved['fields']['shipping']['kiriof_shipping_destination_area']['options'][202] );
        $this->assertSame( '202', $saved['fields']['shipping']['kiriof_shipping_destination_area']['default'] );
        $this->assertContains( 'billing_kiriof_destination_area', $saved['customer_meta_reads'] );
        $this->assertContains( 'shipping_kiriof_destination_area', $saved['customer_meta_reads'] );

        $input['session'] = array(
            'destination_id' => '303', 'destination_name' => 'Session billing district',
            'shipping_destination_id' => '404', 'shipping_destination_name' => 'Session shipping district',
        );
        $session = $this->runFixture( $input );
        $this->assertSame( 'Session billing district', $session['fields']['billing']['kiriof_destination_area']['options'][303] );
        $this->assertSame( '303', $session['fields']['billing']['kiriof_destination_area']['default'] );
        $this->assertSame( 'Session shipping district', $session['fields']['shipping']['kiriof_shipping_destination_area']['options'][404] );
        $this->assertSame( '404', $session['fields']['shipping']['kiriof_shipping_destination_area']['default'] );
    }

    #[Test]
    #[DataProvider( 'normalizationCases' )]
    public function normalization_only_populates_indonesian_destinations( array $post, array $session, array $checkout, array $expected ): void {
        $result = $this->runFixture( array( 'action' => 'normalize', 'post' => $post, 'session' => $session, 'checkout_values' => $checkout ) );
        // Legacy session history must not populate final posted fields or revive clears.
        $this->assertSame( $post, $result['post'] );
    }

    public static function normalizationCases(): array {
        $stale = array( 'destination_id' => '303', 'destination_name' => 'Session district' );
        $billing = array( 'kiriof_destination_area' => '303', 'kiriof_destination_area_name' => 'Session district' );
        $shipping = array( 'kiriof_shipping_destination_area' => '303', 'kiriof_shipping_destination_area_name' => 'Session district' );
        $foreign = array( 'billing_country' => 'US', 'shipping_country' => 'US' );
        $idUs = array( 'billing_country' => 'ID', 'shipping_country' => 'US', 'ship_to_different_address' => '1' );
        $usId = array( 'billing_country' => 'US', 'shipping_country' => 'ID', 'ship_to_different_address' => '1' );
        $id = array( 'billing_country' => 'ID', 'shipping_country' => 'ID' );
        $bothId = $id + array( 'ship_to_different_address' => '1' );
        $postedId = $bothId + array( 'kiriof_shipping_destination_area' => '505', 'kiriof_shipping_destination_area_name' => 'Posted district' );
        return array(
            'foreign billing destination ignores stale Indonesian session' => array( $foreign, $stale, array(), $foreign ),
            'foreign separate shipping ignores stale Indonesian session' => array( $foreign + array( 'ship_to_different_address' => '1' ), $stale, array(), $foreign + array( 'ship_to_different_address' => '1' ) ),
            'ID billing with foreign active shipping injects nothing' => array( $idUs, $stale, array(), $idUs ),
            'foreign billing with ID shipping does not populate billing district' => array( $usId, $stale, array(), $usId + $shipping + array( 'kiriof_checkout_token' => '1' ) ),
            'ID billing normalization preserved' => array( $id, $stale, array(), $id + $billing + array( 'kiriof_checkout_token' => '1' ) ),
            'both ID normalization preserved' => array( $bothId, $stale, array(), $bothId + $billing + $shipping + array( 'kiriof_checkout_token' => '1' ) ),
            'posted shipping district wins over stale session' => array( $postedId, $stale, array(), $postedId + array( 'kiriof_destination_area' => '505', 'kiriof_destination_area_name' => 'Posted district', 'kiriof_checkout_token' => '1' ) ),
            'missing POST countries resolve foreign checkout destination' => array( array(), $stale, array( 'billing_country' => 'US' ), array() ),
            'missing POST countries resolve Indonesian checkout destination' => array( array(), $stale, array( 'billing_country' => 'ID' ), $billing + array( 'kiriof_checkout_token' => '1' ) ),
        );
    }

    #[Test]
    #[DataProvider( 'validationCases' )]
    public function validate_order_only_requires_logistics_for_active_id_destination( string $billing, string $shipping, bool $different, array $expected ): void {
        $result = $this->runFixture( array(
            'action' => 'validate',
            'post' => array(
                'billing_country' => $billing, 'shipping_country' => $shipping,
                'ship_to_different_address' => $different ? '1' : '',
                'billing_address_1' => '123 Long billing street address',
                'shipping_address_1' => '456 Long shipping street address',
                'checkout_kiriminaja_nonce_field' => 'valid-country-nonce',
            ),
            'session' => array(),
        ) );
        $this->assertSame( array( array( 'valid-country-nonce', 'checkout-country-runtime' ) ), $result['nonce_checks'], 'The fixture must pass nonce verification, not skip validation.' );
        $this->assertSame( $expected, array_column( $result['notices'], 'message' ) );
        foreach ( $result['notices'] as $notice ) {
            $this->assertSame( 'error', $notice['type'] );
        }
    }

    public static function validationCases(): array {
        $required = array(
            '<strong>District</strong> is a required field',
            '<strong>Shipping</strong> is a required field',
            '<strong>Checkout Calculation</strong> is not finished yet',
        );
        return array(
            'foreign billing and shipping' => array( 'US', 'US', true, array() ),
            'foreign billing used as destination' => array( 'US', 'ID', false, array() ),
            'ID billing but foreign shipping destination' => array( 'ID', 'US', true, array() ),
            'foreign billing but ID shipping needs District' => array( 'US', 'ID', true, $required ),
            'ID billing destination still requires logistics' => array( 'ID', 'US', false, $required ),
        );
    }

    #[Test]
    public function foreign_order_creation_ignores_stale_kiriminaja_session(): void {
        $post = array( 'billing_country' => 'GB', 'billing_postcode' => 'SW1A 1AA' );
        $result = $this->runFixture( array(
            'action' => 'create_foreign_order',
            'post' => $post,
            'session' => array( 'chosen_shipping_methods' => array( 'kiriminaja-official:test' ), 'destination_id' => '303', 'kiriof_checkout_postcode' => '12345' ),
        ) );
        $this->assertSame( $post, $result['post'] );
        $this->assertSame( 'SW1A 1AA', $result['postcode'] );
    }

    private function runFixture( array $input ): array {
        $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-country-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1';
        exec( $command, $output, $exitCode );
        $text = implode( "\n", $output );
        $this->assertSame( 0, $exitCode, 'Runtime fixture failed: ' . $text );
        return json_decode( $text, true, 512, JSON_THROW_ON_ERROR );
    }
}
