<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccountShippingDestinationRuntimeTest extends TestCase {
    #[Test]
    public function shipping_has_only_one_district_field_validator_and_session_writer(): void {
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'post' => self::post(), 'session' => self::staleSession(), 'operations' => array( 'legacy_fields', 'legacy_validate', 'validate', 'persist_address', 'legacy_saved', 'saved', 'form' ) ) );
        $this->assertSame( array(
            'legacy_fields' => array( 'shipping_postcode' ),
            'fields count' => 1,
            'notices' => array(),
            'destination' => self::destination(),
            'session shipping_destination_id' => '222',
        ), array(
            'legacy_fields' => array_keys( $result['legacy_fields'] ),
            'fields count' => count( $result['fields'] ),
            'notices' => $result['notices'],
            'destination' => $result['destination'],
            'session shipping_destination_id' => $result['session']['shipping_destination_id'],
        ) );
    }

    #[Test]
    public function account_shipping_layout_pairs_city_state_and_postcode_phone_without_changing_theme_fields(): void {
        $fields = array();
        foreach ( array( 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_state', 'shipping_postcode', 'shipping_city' ) as $key ) {
            $fields[$key] = array( 'label' => $key, 'class' => array( 'form-row-wide', 'theme-field' ), 'value' => 'saved', 'required' => true );
        }
        $result = $this->runFixture( array( 'address_fields' => $fields, 'operations' => array( 'layout', 'form' ) ) );
        $this->assertSame( array( 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_city', 'shipping_state', 'shipping_postcode', 'shipping_phone' ), array_keys( $result['layout'] ) );
        foreach ( array( 'shipping_city' => 'first', 'shipping_state' => 'last', 'shipping_postcode' => 'first', 'shipping_phone' => 'last' ) as $key => $position ) {
            $this->assertContains( 'form-row-' . $position, $result['layout'][$key]['class'] );
        }
        $this->assertSame( array(
            'layout shipping_city value' => 'saved',
            'contains theme-field' => true,
            'layout shipping_phone type' => 'tel',
            'layout shipping_phone validate' => array( 'phone' ),
            'hidden district retry control' => true,
        ), array(
            'layout shipping_city value' => $result['layout']['shipping_city']['value'],
            'contains theme-field' => in_array( 'theme-field', $result['layout']['shipping_state']['class'], true ),
            'layout shipping_phone type' => $result['layout']['shipping_phone']['type'],
            'layout shipping_phone validate' => $result['layout']['shipping_phone']['validate'],
            'hidden district retry control' => str_contains( $result['html'], 'id="kiriof-account-district-retry" hidden' ),
        ) );
        foreach ( array( array( 'type' => 'billing' ), array( 'current_user' => 0 ), array( 'account_page' => false ), array( 'endpoint_type' => 'billing' ) ) as $scope ) {
            $this->assertSame( $fields, $this->runFixture( $scope + array( 'address_fields' => $fields, 'operations' => array( 'layout' ) ) )['layout'] );
        }
        $fields['shipping_phone'] = array( 'type' => 'tel', 'label' => 'Theme phone', 'required' => false, 'class' => array( 'theme-phone' ), 'value' => '08123' );
        $phone = $this->runFixture( array( 'address_fields' => $fields, 'operations' => array( 'layout' ) ) )['layout']['shipping_phone'];
        $this->assertSame( array(
            'label' => 'Theme phone',
            'value' => '08123',
            'required' => false,
        ), array(
            'label' => $phone['label'],
            'value' => $phone['value'],
            'required' => $phone['required'],
        ) );
    }

    private static function address(): array {
        return array( 'address_1' => 'Main street', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' );
    }

    private static function destination( bool $pin = true ): array {
        $destination = array( 'district_id' => '222', 'district_label' => 'Canonical district', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => $pin ? 2 : 1 );
        return $pin ? $destination + array( 'destination_latitude' => '0', 'destination_longitude' => '0', 'shipping_address' => self::address() ) : $destination;
    }

    private static function profile( bool $existing = false ): array {
        $meta = array( 'billing_address_1' => 'Billing untouched', 'billing_kiriof_destination_area' => '999' );
        foreach ( self::address() as $field => $value ) { $meta['shipping_' . $field] = $value; }
        if ( $existing ) {
            $meta['_kiriof_buyer_destination'] = self::destination();
            $meta['_kiriof_buyer_destination_address'] = self::address();
            $meta['_kiriof_buyer_destination_coordinates'] = array( 'latitude' => '0', 'longitude' => '0' );
        }
        return array( 7 => $meta, 8 => array( 'shipping_address_1' => 'Other user' ) );
    }

    private static function post( ?array $destination = null ): array {
        return array( 'kiriof_account_destination_nonce' => 'valid-nonce', 'kiriof_account_district' => '222', 'kiriof_account_destination' => json_encode( $destination ?? self::destination(), JSON_THROW_ON_ERROR ) );
    }

    private static function staleSession(): array {
        return array_fill_keys( array( 'kiriof_buyer_destination', 'kiriof_buyer_destination_coordinates', 'shipping_destination_id', 'shipping_destination_name', 'destination_id', 'destination_name', 'kiriof_destination_area', 'kiriof_destination_area_name', 'kiriof_checkout_postcode', 'kiriof_checkout_token', 'kiriof_destination_postcode_map' ), 'stale' ) + array( 'unrelated' => 'keep' );
    }

    #[Test]
    public function registers_native_account_hooks_only(): void {
        $result = $this->runFixture( array( 'operations' => array( 'register' ) ) );
        $this->assertSame( array(
            array( 'woocommerce_address_to_edit', 30, 2 ),
            array( 'woocommerce_my_account_after_my_address', 20, 1 ),
            array( 'woocommerce_after_edit_address_form_shipping', 20, 0 ),
            array( 'woocommerce_after_save_address_validation', 20, 4 ),
            array( 'woocommerce_customer_save_address', 20, 4 ),
            array( 'wp_enqueue_scripts', 30, 0 ),
        ), $result['hooks'] );
    }

    #[Test]
    public function native_form_restores_saved_selection_and_zero_coordinate_complete_badge(): void {
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'operations' => array( 'form', 'badges' ) ) );
        $this->assertSame( array(
            'fields' => 'kiriof_account_district',
            'fields type' => 'select',
            'fields required' => false,
            'selected district' => '222',
            'fields options' => 'Canonical district',
            'native district select' => true,
            'accessible district status' => true,
            'complete destination badge' => true,
            'excludes Need Pin Location' => false,
            'nonces' => array( array( 'kiriof_save_account_destination', 'kiriof_account_destination_nonce' ) ),
            'destination' => self::destination(),
            'writes' => array(),
        ), array(
            'fields' => $result['fields'][0][0],
            'fields type' => $result['fields'][0][1]['type'],
            'fields required' => $result['fields'][0][1]['required'],
            'selected district' => $result['fields'][0][2],
            'fields options' => $result['fields'][0][1]['options'][222],
            'native district select' => str_contains( $result['html'], '<select name="kiriof_account_district"' ),
            'accessible district status' => str_contains( $result['html'], 'aria-describedby="kiriof-account-district-status"' ),
            'complete destination badge' => str_contains( $result['html'], 'is-complete' ),
            'excludes Need Pin Location' => str_contains( $result['html'], 'Need Pin Location' ),
            'nonces' => $result['nonces'],
            'destination' => $result['destination'],
            'writes' => $result['writes'],
        ) );
    }

    #[Test]
    public function badges_warn_for_missing_district_and_pin_and_suppress_billing_or_guests(): void {
        $result = $this->runFixture( array( 'operations' => array( 'badges' ) ) );
        $this->assertSame( array(
            'contains Subdistrict Not Set' => true,
            'contains Need Pin Location' => true,
        ), array(
            'contains Subdistrict Not Set' => str_contains( $result['html'], 'Subdistrict Not Set' ),
            'contains Need Pin Location' => str_contains( $result['html'], 'Need Pin Location' ),
        ) );
        $result = $this->runFixture( array( 'meta' => array( 7 => self::profile()[7] + array( '_kiriof_buyer_destination' => self::destination( false ) ) ), 'operations' => array( 'badges' ) ) );
        $this->assertSame( array(
            'excludes Subdistrict Not Set' => false,
            'contains Need Pin Location' => true,
        ), array(
            'excludes Subdistrict Not Set' => str_contains( $result['html'], 'Subdistrict Not Set' ),
            'contains Need Pin Location' => str_contains( $result['html'], 'Need Pin Location' ),
        ) );
        foreach ( array( array( 'type' => 'billing' ), array( 'current_user' => 0 ) ) as $case ) {
            $this->assertSame( '', $this->runFixture( $case + array( 'operations' => array( 'badges' ) ) )['html'] );
        }
        $this->assertSame( '', $this->runFixture( array( 'current_user' => 0, 'operations' => array( 'form' ) ) )['html'] );
    }

    #[Test]
    public function assets_are_account_endpoint_scoped_and_shipping_map_has_no_blocks_dependencies(): void {
        foreach ( array( array( 'current_user' => 0 ), array( 'account_page' => false ), array( 'edit_address' => false ) ) as $case ) {
            $result = $this->runFixture( $case + array( 'operations' => array( 'assets' ) ) );
            $this->assertSame( array(
                'styles' => array(),
                'scripts' => array(),
            ), array(
                'styles' => $result['styles'],
                'scripts' => $result['scripts'],
            ) );
        }
        $billing = $this->runFixture( array( 'endpoint_type' => 'billing', 'operations' => array( 'assets' ) ) );
        $this->assertSame( array(
            'scripts' => array(),
            'localized' => array(),
        ), array(
            'scripts' => $billing['scripts'],
            'localized' => $billing['localized'],
        ) );
        $result = $this->runFixture( array( 'operations' => array( 'assets' ) ) );
        $this->assertSame( array(
            'styles' => array( 'kiriof-account-destination', 'kiriof-leaflet' ),
            'scripts' => array( 'kiriof-map-provider', 'kiriof-account-shipping' ),
            'map provider dependencies' => array( 'kiriof-leaflet' ),
            'account shipping dependencies' => array( 'kiriof-map-provider' ),
            'account shipping bundle' => true,
            'localized' => array( 'kiriof-account-shipping', 'kiriofAccountShippingConfig' ),
            'localized nonce' => 'lookup-nonce',
            'localized ajaxUrl' => '/wp-admin/admin-ajax.php',
            'localized map enabled' => true,
            'contains district' => true,
            'excludes wc-blocks' => false,
        ), array(
            'styles' => array_column( $result['styles'], 0 ),
            'scripts' => array_column( $result['scripts'], 0 ),
            'map provider dependencies' => $result['scripts'][0][2],
            'account shipping dependencies' => $result['scripts'][1][2],
            'account shipping bundle' => str_contains( $result['scripts'][1][1], 'assets/buyer/dist/kiriminaja-buyer-account-shipping.js' ),
            'localized' => array_slice( $result['localized'][0], 0, 2 ),
            'localized nonce' => $result['localized'][0][2]['nonce'],
            'localized ajaxUrl' => $result['localized'][0][2]['ajaxUrl'],
            'localized map enabled' => $result['localized'][0][2]['map']['enabled'],
            'contains district' => array_key_exists( 'district', $result['localized'][0][2]['i18n'] ),
            'excludes wc-blocks' => str_contains( json_encode( $result['scripts'] ), 'wc-blocks' ),
        ) );
    }

    #[Test]
    public function validation_uses_candidate_customer_then_saves_canonical_label_only_after_woo_save(): void {
        $address = self::address(); $address['address_1'] = 'New candidate street'; $address['postcode'] = '54321';
        $destination = self::destination(); $destination['shipping_address'] = $address; $destination['postcode'] = '54321'; $destination['district_label'] = 'Client supplied label';
        $result = $this->runFixture( array( 'candidate' => $address, 'meta' => self::profile(), 'post' => self::post( $destination ), 'operations' => array( 'validate', 'persist_address', 'saved', 'badges', 'form' ) ) );
        $this->assertSame( array(
            'steps writes' => array(),
            'steps validated district_label' => 'Canonical district',
            'lookups' => array( '54321' ),
        ), array(
            'steps writes' => $result['steps'][0]['writes'],
            'steps validated district_label' => $result['steps'][0]['validated'][7]['district_label'],
            'lookups' => $result['lookups'],
        ) );
        $destination['district_label'] = 'Canonical district';
        $this->assertSame( array(
            'destination' => $destination,
            'notices' => array(),
            'meta _kiriof_buyer_destination_address' => $address,
            'meta _kiriof_buyer_destination_coordinates' => array( 'latitude' => '0', 'longitude' => '0' ),
            'meta _wc_shipping/kiriminaja-official/kiriof_destination_area' => '222',
        ), array(
            'destination' => $result['destination'],
            'notices' => $result['notices'],
            'meta _kiriof_buyer_destination_address' => $result['meta'][7]['_kiriof_buyer_destination_address'],
            'meta _kiriof_buyer_destination_coordinates' => $result['meta'][7]['_kiriof_buyer_destination_coordinates'],
            'meta _wc_shipping/kiriminaja-official/kiriof_destination_area' => $result['meta'][7]['_wc_shipping/kiriminaja-official/kiriof_destination_area'],
        ) );
        foreach ( $result['writes'] as $write ) { $this->assertSame( 7, $write[0] ); $this->assertStringNotContainsString( 'billing', $write[1] ); }
        $this->assertSame( array(
            'meta' => self::profile()[8],
            'meta billing_address_1' => 'Billing untouched',
        ), array(
            'meta' => $result['meta'][8],
            'meta billing_address_1' => $result['meta'][7]['billing_address_1'],
        ) );
    }

    #[Test]
    public function nonce_auth_and_shipping_isolation_prevent_writes(): void {
        foreach ( array( array( 'current_user' => 0 ), array( 'current_user' => 8 ), array( 'user_id' => 8 ), array( 'type' => 'billing' ), array( 'post' => array_replace( self::post(), array( 'kiriof_account_destination_nonce' => 'invalid' ) ) ) ) as $case ) {
            $result = $this->runFixture( $case + array( 'post' => self::post(), 'meta' => self::profile( true ), 'session' => self::staleSession() ) );
            $this->assertSame( array(
                'writes' => array(),
                'lookups' => array(),
                'steps validated' => array(),
                'meta' => self::profile( true ),
                'session' => self::staleSession(),
            ), array(
                'writes' => $result['writes'],
                'lookups' => $result['lookups'],
                'steps validated' => $result['steps'][0]['validated'],
                'meta' => $result['meta'],
                'session' => $result['session'],
            ) );
        }
    }

    #[Test]
    public function invalid_api_identity_snapshot_and_stale_address_never_queue_or_mutate_saved_profile(): void {
        $cases = array();
        foreach ( array( 'unknown', 'failure', 'throw', 'malformed' ) as $mode ) { $cases['lookup/' . $mode] = array( 'lookup_mode' => $mode ); }
        foreach ( array( 'district_id' => '0', 'destination_latitude' => 'NaN', 'destination_longitude' => '181', 'postcode' => '99999', 'country' => 'US' ) as $field => $value ) {
            $destination = self::destination(); $destination[$field] = $value; $cases['snapshot/' . $field] = array( 'post' => self::post( $destination ) );
        }
        foreach ( array_keys( self::address() ) as $field ) {
            $address = self::address(); $address[$field] = 'country' === $field ? 'US' : ( 'postcode' === $field ? '54321' : 'Changed' );
            if ( 'country' !== $field ) { $cases['stale address/' . $field] = array( 'candidate' => $address ); }
        }
        $cases['select identity mismatch'] = array( 'post' => array_replace( self::post(), array( 'kiriof_account_district' => '333' ) ) );
        $cases['broken snapshot JSON'] = array( 'post' => array_replace( self::post(), array( 'kiriof_account_destination' => '{broken' ) ) );
        $cases['unsafe API label'] = array( 'rows' => array( array( 'id' => 222, 'text' => '<script>alert(1)</script>' ) ) );
        $expected = $actual = array();
        foreach ( $cases as $name => $case ) {
            $result = $this->runFixture( $case + array( 'meta' => self::profile( true ), 'post' => self::post(), 'session' => self::staleSession() ) );
            $expected[$name] = array(
                'writes' => array(),
                'steps validated' => array(),
                'meta' => self::profile( true ),
                'session' => self::staleSession(),
                'notices' => false,
            );
            $actual[$name] = array(
                'writes' => $result['writes'],
                'steps validated' => $result['steps'][0]['validated'],
                'meta' => $result['meta'],
                'session' => $result['session'],
                'notices' => empty( $result['notices'] ),
            );
        }
        $this->assertSame( $expected, $actual );
    }

    #[Test]
    public function failed_revalidation_discards_previously_queued_destination(): void {
        $bad = self::post(); $bad['kiriof_account_destination_nonce'] = 'invalid';
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'post' => self::post(), 'operations' => array( 'validate', array( 'post' => $bad ), 'clear_errors', 'saved' ) ) );
        $this->assertSame( array(
            'steps validated' => false,
            'queue after failed validation' => array(),
            'writes' => array(),
        ), array(
            'steps validated' => empty( $result['steps'][0]['validated'] ),
            'queue after failed validation' => $result['steps'][1]['validated'],
            'writes' => $result['writes'],
        ) );
    }

    #[Test]
    public function no_javascript_native_select_with_empty_hidden_snapshot_saves_api_canonical_identity(): void {
        $post = self::post(); $post['kiriof_account_destination'] = '';
        $result = $this->runFixture( array( 'meta' => self::profile(), 'post' => $post ) );
        $this->assertSame( array(
            'steps writes' => array(),
            'destination' => self::destination( false ),
            'notices' => array(),
            'excludes _kiriof_buyer_destination_coordinates' => false,
        ), array(
            'steps writes' => $result['steps'][0]['writes'],
            'destination' => $result['destination'],
            'notices' => $result['notices'],
            'excludes _kiriof_buyer_destination_coordinates' => array_key_exists( '_kiriof_buyer_destination_coordinates', $result['meta'][7] ),
        ) );
    }

    #[Test]
    public function no_javascript_changed_select_rejects_existing_hidden_identity_mismatch(): void {
        $post = self::post(); $post['kiriof_account_district'] = '333';
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'post' => $post ) );
        $this->assertSame( array(
            'writes' => array(),
            'lookups' => array(),
            'steps validated' => array(),
            'notices' => false,
        ), array(
            'writes' => $result['writes'],
            'lookups' => $result['lookups'],
            'steps validated' => $result['steps'][0]['validated'],
            'notices' => empty( $result['notices'] ),
        ) );
    }

    #[Test]
    public function non_indonesian_shipping_save_clears_district_and_pin_without_api_lookup(): void {
        $address = self::address(); $address['country'] = 'US'; $address['postcode'] = '90210';
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'candidate' => $address, 'post' => self::post(), 'session' => self::staleSession(), 'operations' => array( 'validate', 'persist_address', 'saved' ) ) );
        $this->assertSame( array(
            'lookups' => array(),
            'destination' => array( 'district_id' => '', 'district_label' => '', 'postcode' => '90210', 'country' => 'US', 'address_type' => 'shipping', 'version' => 1 ),
            'excludes _kiriof_buyer_destination_coordinates' => false,
            'meta shipping_kiriof_destination_area' => '',
            'session kiriof_buyer_destination_coordinates' => null,
            'excludes kiriof_destination_postcode_map' => false,
        ), array(
            'lookups' => $result['lookups'],
            'destination' => $result['destination'],
            'excludes _kiriof_buyer_destination_coordinates' => array_key_exists( '_kiriof_buyer_destination_coordinates', $result['meta'][7] ),
            'meta shipping_kiriof_destination_area' => $result['meta'][7]['shipping_kiriof_destination_area'],
            'session kiriof_buyer_destination_coordinates' => $result['session']['kiriof_buyer_destination_coordinates'],
            'excludes kiriof_destination_postcode_map' => array_key_exists( 'kiriof_destination_postcode_map', $result['session'] ),
        ) );
    }

    #[Test]
    public function successful_account_save_replaces_all_stale_session_aliases(): void {
        $result = $this->runFixture( array( 'meta' => self::profile(), 'post' => self::post(), 'session' => self::staleSession() ) );
        $this->assertSame( array(
            'session kiriof_buyer_destination' => self::destination(),
            'session kiriof_buyer_destination_coordinates' => array( 'latitude' => '0', 'longitude' => '0' ),
        ), array(
            'session kiriof_buyer_destination' => $result['session']['kiriof_buyer_destination'],
            'session kiriof_buyer_destination_coordinates' => $result['session']['kiriof_buyer_destination_coordinates'],
        ) );
        foreach ( array( 'destination_id', 'shipping_destination_id', 'kiriof_destination_area' ) as $key ) { $this->assertSame( '222', $result['session'][$key] ); }
        foreach ( array( 'destination_name', 'shipping_destination_name', 'kiriof_destination_area_name' ) as $key ) { $this->assertSame( 'Canonical district', $result['session'][$key] ); }
        $this->assertSame( array(
            'session kiriof_checkout_postcode' => '12345',
            'session kiriof_checkout_token' => '1',
            'session kiriof_destination_postcode_map' => array( '12345' => array( 'destination_id' => '222', 'destination_name' => 'Canonical district' ) ),
            'session unrelated' => 'keep',
        ), array(
            'session kiriof_checkout_postcode' => $result['session']['kiriof_checkout_postcode'],
            'session kiriof_checkout_token' => $result['session']['kiriof_checkout_token'],
            'session kiriof_destination_postcode_map' => $result['session']['kiriof_destination_postcode_map'],
            'session unrelated' => $result['session']['unrelated'],
        ) );
    }

    #[Test]
    public function account_save_never_resets_another_wc_customers_session(): void {
        $result = $this->runFixture( array( 'meta' => self::profile(), 'post' => self::post(), 'customer_id' => 8, 'session' => self::staleSession() ) );
        $this->assertSame( array(
            'destination' => self::destination(),
            'session' => self::staleSession(),
        ), array(
            'destination' => $result['destination'],
            'session' => $result['session'],
        ) );
    }

    #[Test]
    public function other_woo_validation_errors_prevent_persistence_and_session_changes(): void {
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'post' => self::post(), 'session' => self::staleSession(), 'operations' => array( 'validate', 'other_error', 'saved' ) ) );
        $this->assertSame( array(
            'writes' => array(),
            'meta' => self::profile( true ),
            'session' => self::staleSession(),
        ), array(
            'writes' => $result['writes'],
            'meta' => $result['meta'],
            'session' => $result['session'],
        ) );
    }

    #[Test]
    public function absent_account_fields_degrade_safely_without_plugin_writes(): void {
        $result = $this->runFixture( array( 'meta' => self::profile( true ) ) );
        $this->assertSame( array(
            'writes' => array(),
            'lookups' => array(),
            'notices' => array(),
            'steps validated' => array(),
        ), array(
            'writes' => $result['writes'],
            'lookups' => $result['lookups'],
            'notices' => $result['notices'],
            'steps validated' => $result['steps'][0]['validated'],
        ) );
    }

    #[Test]
    public function failed_submission_redisplays_posted_json_safely_instead_of_saved_selection(): void {
        $destination = self::destination( false ); $destination['district_id'] = '333'; $destination['district_label'] = 'District " onfocus="alert(1) & Co';
        $post = self::post( $destination ); $post['kiriof_account_district'] = '333';
        // Model PHP's slashed request strings, so wp_unslash reproduces the actual JSON.
        $post['kiriof_account_destination'] = addslashes( $post['kiriof_account_destination'] );
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'post' => $post, 'lookup_mode' => 'failure', 'operations' => array( 'validate', 'form' ) ) );
        $this->assertSame( array(
            'fields' => '333',
            'fields options' => $destination['district_label'],
            'escaped quote' => true,
            'escaped ampersand' => true,
            'unescaped event handler' => false,
            'excludes script' => false,
            'writes' => array(),
        ), array(
            'fields' => $result['fields'][0][2],
            'fields options' => $result['fields'][0][1]['options'][333],
            'escaped quote' => str_contains( $result['html'], '&quot;' ),
            'escaped ampersand' => str_contains( $result['html'], '&amp; Co' ),
            'unescaped event handler' => str_contains( $result['html'], ' onfocus="alert(1)' ),
            'excludes script' => str_contains( $result['html'], '<script' ),
            'writes' => $result['writes'],
        ) );
        $bad = self::destination(); $bad['district_label'] = '<script>alert(1)</script>';
        $result = $this->runFixture( array( 'post' => self::post( $bad ), 'operations' => array( 'form' ) ) );
        $this->assertStringNotContainsString( '<script', $result['html'] );
    }

    private function runFixture( array $input ): array {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/account-shipping-destination-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $exit_code );
        $text = implode( "\n", $output );
        $this->assertSame( 0, $exit_code, $text );
        return json_decode( $text, true, 512, JSON_THROW_ON_ERROR );
    }
}
