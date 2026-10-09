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
                $this->assertSame(
                    [
                    '1: array_key_exists( key, result[original_fields][ group ] )' => false,
                    '2: array_key_exists( key, result[fields][ group ] )' => false,
                    ],
                    [
                    '1: array_key_exists( key, result[original_fields][ group ] )' => array_key_exists( $key, $result['original_fields'][ $group ] ),
                    '2: array_key_exists( key, result[fields][ group ] )' => array_key_exists( $key, $result['fields'][ $group ] ),
                    ]
                );
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
            $this->assertSame(
                [
                '1: result[fields][ group ][ group . _city ][priority]' => 70,
                '2: result[fields][ group ][ group . _state ][priority]' => 71,
                '3: in_array( theme-city, result[fields][ group ][ group . _city ][class], true )' => true,
                '4: in_array( theme-province, result[fields][ group ][ group . _state ][class], true )' => true,
                ],
                [
                '1: result[fields][ group ][ group . _city ][priority]' => $result['fields'][ $group ][ $group . '_city' ]['priority'],
                '2: result[fields][ group ][ group . _state ][priority]' => $result['fields'][ $group ][ $group . '_state' ]['priority'],
                '3: in_array( theme-city, result[fields][ group ][ group . _city ][class], true )' => in_array( 'theme-city', $result['fields'][ $group ][ $group . '_city' ]['class'], true ),
                '4: in_array( theme-province, result[fields][ group ][ group . _state ][class], true )' => in_array( 'theme-province', $result['fields'][ $group ][ $group . '_state' ]['class'], true ),
                ]
            );
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
        $this->assertSame(
            [
            '1: result[fields]' => $result['original_fields'],
            '2: result[checkout_reads]' => array(),
            ],
            [
            '1: result[fields]' => $result['fields'],
            '2: result[checkout_reads]' => $result['checkout_reads'],
            ]
        );
    }

    #[Test]
    #[DataProvider( 'countryPairs' )]
    public function missing_post_countries_use_checkout_get_value( string $billing, string $shipping ): void {
        $result = $this->runFixture( array(
            'action' => 'fields',
            'checkout_values' => array( 'billing_country' => $billing, 'shipping_country' => $shipping ),
        ) );
        $this->assertAddressFields( $result, $billing, $shipping );
        $this->assertSame(
            [
            '1: in_array( billing_country, result[checkout_reads], true )' => true,
            '2: in_array( shipping_country, result[checkout_reads], true )' => true,
            '3: array_key_exists( billing_country, result[post] )' => false,
            '4: array_key_exists( shipping_country, result[post] )' => false,
            ],
            [
            '1: in_array( billing_country, result[checkout_reads], true )' => in_array( 'billing_country', $result['checkout_reads'], true ),
            '2: in_array( shipping_country, result[checkout_reads], true )' => in_array( 'shipping_country', $result['checkout_reads'], true ),
            '3: array_key_exists( billing_country, result[post] )' => array_key_exists( 'billing_country', $result['post'] ),
            '4: array_key_exists( shipping_country, result[post] )' => array_key_exists( 'shipping_country', $result['post'] ),
            ]
        );
    }

    private function assertAddressFields( array $result, string $billing, string $shipping ): void {
        $expected = $actual = array();
        foreach ( array( 'billing' => $billing, 'shipping' => $shipping ) as $group => $country ) {
            $phone = $result['fields'][$group][$group . '_phone'];
            $expected[$group]['phone'] = array( 'priority' => 91, 'last' => true, 'required' => true );
            $actual[$group]['phone'] = array( 'priority' => $phone['priority'], 'last' => in_array( 'form-row-last', $phone['class'], true ), 'required' => $phone['required'] );
            foreach ( array( 'email', 'country', 'state', 'city', 'company', 'postcode' ) as $native ) {
                $key = $group . '_' . $native;
                $original = $result['original_fields'][$group][$key];
                $field = $result['fields'][$group][$key];
                if ( in_array( $native, array( 'state', 'city', 'postcode' ), true ) ) {
                    $side = 'state' === $native ? 'form-row-last' : 'form-row-first';
                    $priority = array( 'city' => 70, 'state' => 71, 'postcode' => 90 );
                    $expected[$group][$native . ' layout'] = array( 'side' => true, 'priority' => $priority[$native], 'clear' => false );
                    $actual[$group][$native . ' layout'] = array( 'side' => in_array( $side, $field['class'], true ), 'priority' => $field['priority'], 'clear' => $field['clear'] );
                    $original['class'] = array_values( array_diff( $original['class'] ?? array(), array( 'form-row-wide', 'form-row-first', 'form-row-last' ) ) );
                    $field['class'] = array_values( array_diff( $field['class'], array( 'form-row-wide', 'form-row-first', 'form-row-last' ) ) );
                    unset( $original['priority'], $field['priority'], $original['clear'], $field['clear'] );
                }
                $expected[$group][$native . ' native metadata'] = $original;
                $actual[$group][$native . ' native metadata'] = $field;
            }
            $districtKey = 'billing' === $group ? 'kiriof_destination_area' : 'kiriof_shipping_destination_area';
            $district = $result['fields'][$group][$districtKey];
            $expected[$group]['district'] = array( 'required' => 'ID' === $country, 'hidden' => 'ID' !== $country );
            $actual[$group]['district'] = array( 'required' => $district['required'], 'hidden' => in_array( 'kiriof-classic-address-hidden', $district['class'], true ) );
        }
        $this->assertSame( $expected, $actual );
    }

    #[Test]
    public function district_options_read_customer_meta_and_prefer_session_values(): void {
        $expectedCases = $actualCases = [];
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
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: saved[fields][billing][kiriof_destination_area][options][101]' => 'Saved billing district',
            '2: saved[fields][billing][kiriof_destination_area][default]' => '101',
            '3: saved[fields][shipping][kiriof_shipping_destination_area][options][202]' => 'Saved shipping district',
            '4: saved[fields][shipping][kiriof_shipping_destination_area][default]' => '202',
            '5: in_array( billing_kiriof_destination_area, saved[customer_meta_reads], true )' => true,
            '6: in_array( shipping_kiriof_destination_area, saved[customer_meta_reads], true )' => true,
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: saved[fields][billing][kiriof_destination_area][options][101]' => $saved['fields']['billing']['kiriof_destination_area']['options'][101],
            '2: saved[fields][billing][kiriof_destination_area][default]' => $saved['fields']['billing']['kiriof_destination_area']['default'],
            '3: saved[fields][shipping][kiriof_shipping_destination_area][options][202]' => $saved['fields']['shipping']['kiriof_shipping_destination_area']['options'][202],
            '4: saved[fields][shipping][kiriof_shipping_destination_area][default]' => $saved['fields']['shipping']['kiriof_shipping_destination_area']['default'],
            '5: in_array( billing_kiriof_destination_area, saved[customer_meta_reads], true )' => in_array( 'billing_kiriof_destination_area', $saved['customer_meta_reads'], true ),
            '6: in_array( shipping_kiriof_destination_area, saved[customer_meta_reads], true )' => in_array( 'shipping_kiriof_destination_area', $saved['customer_meta_reads'], true ),
            ];

        $input['session'] = array(
            'destination_id' => '303', 'destination_name' => 'Session billing district',
            'shipping_destination_id' => '404', 'shipping_destination_name' => 'Session shipping district',
        );
        $session = $this->runFixture( $input );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: session[fields][billing][kiriof_destination_area][options][303]' => 'Session billing district',
            '2: session[fields][billing][kiriof_destination_area][default]' => '303',
            '3: session[fields][shipping][kiriof_shipping_destination_area][options][404]' => 'Session shipping district',
            '4: session[fields][shipping][kiriof_shipping_destination_area][default]' => '404',
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: session[fields][billing][kiriof_destination_area][options][303]' => $session['fields']['billing']['kiriof_destination_area']['options'][303],
            '2: session[fields][billing][kiriof_destination_area][default]' => $session['fields']['billing']['kiriof_destination_area']['default'],
            '3: session[fields][shipping][kiriof_shipping_destination_area][options][404]' => $session['fields']['shipping']['kiriof_shipping_destination_area']['options'][404],
            '4: session[fields][shipping][kiriof_shipping_destination_area][default]' => $session['fields']['shipping']['kiriof_shipping_destination_area']['default'],
            ];
        $this->assertSame( $expectedCases, $actualCases );
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
        $expectedCases = $actualCases = [];
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
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: result[nonce_checks]' => array( array( 'valid-country-nonce', 'checkout-country-runtime' ) ),
            '2: array_column( result[notices], message )' => $expected,
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: result[nonce_checks]' => $result['nonce_checks'],
            '2: array_column( result[notices], message )' => array_column( $result['notices'], 'message' ),
            ];
        foreach ( $result['notices'] as $notice ) {
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = 'error';
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $notice['type'];
        }
        $this->assertSame( $expectedCases, $actualCases );
    }

    public static function validationCases(): array {
        $required = array(
            '<strong>Subdistrict</strong> is a required field',
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
        $this->assertSame(
            [
            '1: result[post]' => $post,
            '2: result[postcode]' => 'SW1A 1AA',
            ],
            [
            '1: result[post]' => $result['post'],
            '2: result[postcode]' => $result['postcode'],
            ]
        );
    }

    private function runFixture( array $input ): array {
        $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-country-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1';
        exec( $command, $output, $exitCode );
        $text = implode( "\n", $output );
        $this->assertSame( 0, $exitCode, 'Runtime fixture failed: ' . $text );
        return json_decode( $text, true, 512, JSON_THROW_ON_ERROR );
    }
}
