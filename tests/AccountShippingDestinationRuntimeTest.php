<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccountShippingDestinationRuntimeTest extends TestCase {
    #[Test]
    public function shipping_has_only_one_district_field_validator_and_session_writer(): void {
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'post' => self::post(), 'session' => self::staleSession(), 'operations' => array( 'legacy_fields', 'legacy_validate', 'validate', 'persist_address', 'legacy_saved', 'saved', 'form' ) ) );
        $this->assertSame( array( 'shipping_postcode' ), array_keys( $result['legacy_fields'] ) );
        $this->assertCount( 1, $result['fields'] );
        $this->assertSame( array(), $result['notices'] );
        $this->assertSame( self::destination(), $result['destination'] );
        $this->assertSame( '222', $result['session']['shipping_destination_id'] );
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
        $this->assertSame( 'saved', $result['layout']['shipping_city']['value'] );
        $this->assertContains( 'theme-field', $result['layout']['shipping_state']['class'] );
        $this->assertSame( 'tel', $result['layout']['shipping_phone']['type'] );
        $this->assertSame( array( 'phone' ), $result['layout']['shipping_phone']['validate'] );
        $this->assertStringContainsString( 'id="kiriof-account-district-retry" hidden', $result['html'] );
        foreach ( array( array( 'type' => 'billing' ), array( 'current_user' => 0 ), array( 'account_page' => false ), array( 'endpoint_type' => 'billing' ) ) as $scope ) {
            $this->assertSame( $fields, $this->runFixture( $scope + array( 'address_fields' => $fields, 'operations' => array( 'layout' ) ) )['layout'] );
        }
        $fields['shipping_phone'] = array( 'type' => 'tel', 'label' => 'Theme phone', 'required' => false, 'class' => array( 'theme-phone' ), 'value' => '08123' );
        $phone = $this->runFixture( array( 'address_fields' => $fields, 'operations' => array( 'layout' ) ) )['layout']['shipping_phone'];
        $this->assertSame( 'Theme phone', $phone['label'] );
        $this->assertSame( '08123', $phone['value'] );
        $this->assertFalse( $phone['required'] );
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
        $this->assertSame( 'kiriof_account_district', $result['fields'][0][0] );
        $this->assertSame( 'select', $result['fields'][0][1]['type'] );
        $this->assertFalse( $result['fields'][0][1]['required'] );
        $this->assertSame( '222', $result['fields'][0][2] );
        $this->assertSame( 'Canonical district', $result['fields'][0][1]['options'][222] );
        $this->assertStringContainsString( '<select name="kiriof_account_district"', $result['html'] );
        $this->assertStringContainsString( 'aria-describedby="kiriof-account-district-status"', $result['html'] );
        $this->assertStringContainsString( 'is-complete', $result['html'] );
        $this->assertStringNotContainsString( 'Need Pin Location', $result['html'] );
        $this->assertSame( array( array( 'kiriof_save_account_destination', 'kiriof_account_destination_nonce' ) ), $result['nonces'] );
        $this->assertSame( self::destination(), $result['destination'] );
        $this->assertSame( array(), $result['writes'] );
    }

    #[Test]
    public function badges_warn_for_missing_district_and_pin_and_suppress_billing_or_guests(): void {
        $result = $this->runFixture( array( 'operations' => array( 'badges' ) ) );
        $this->assertStringContainsString( 'Subdistrict Not Set', $result['html'] );
        $this->assertStringContainsString( 'Need Pin Location', $result['html'] );
        $result = $this->runFixture( array( 'meta' => array( 7 => self::profile()[7] + array( '_kiriof_buyer_destination' => self::destination( false ) ) ), 'operations' => array( 'badges' ) ) );
        $this->assertStringNotContainsString( 'Subdistrict Not Set', $result['html'] );
        $this->assertStringContainsString( 'Need Pin Location', $result['html'] );
        foreach ( array( array( 'type' => 'billing' ), array( 'current_user' => 0 ) ) as $case ) {
            $this->assertSame( '', $this->runFixture( $case + array( 'operations' => array( 'badges' ) ) )['html'] );
        }
        $this->assertSame( '', $this->runFixture( array( 'current_user' => 0, 'operations' => array( 'form' ) ) )['html'] );
    }

    #[Test]
    public function assets_are_account_endpoint_scoped_and_shipping_map_has_no_blocks_dependencies(): void {
        foreach ( array( array( 'current_user' => 0 ), array( 'account_page' => false ), array( 'edit_address' => false ) ) as $case ) {
            $result = $this->runFixture( $case + array( 'operations' => array( 'assets' ) ) );
            $this->assertSame( array(), $result['styles'] );
            $this->assertSame( array(), $result['scripts'] );
        }
        $billing = $this->runFixture( array( 'endpoint_type' => 'billing', 'operations' => array( 'assets' ) ) );
        $this->assertSame( array(), $billing['scripts'] );
        $this->assertSame( array(), $billing['localized'] );
        $result = $this->runFixture( array( 'operations' => array( 'assets' ) ) );
        $this->assertSame( array( 'kiriof-account-destination', 'kiriof-leaflet' ), array_column( $result['styles'], 0 ) );
        $this->assertSame( array( 'kiriof-map-provider', 'kiriof-account-shipping' ), array_column( $result['scripts'], 0 ) );
        $this->assertSame( array( 'kiriof-leaflet' ), $result['scripts'][0][2] );
        $this->assertSame( array( 'kiriof-map-provider' ), $result['scripts'][1][2] );
        $this->assertStringContainsString( 'assets/buyer/dist/kiriminaja-buyer-account-shipping.js', $result['scripts'][1][1] );
        $this->assertSame( array( 'kiriof-account-shipping', 'kiriofAccountShippingConfig' ), array_slice( $result['localized'][0], 0, 2 ) );
        $this->assertSame( 'lookup-nonce', $result['localized'][0][2]['nonce'] );
        $this->assertSame( '/wp-admin/admin-ajax.php', $result['localized'][0][2]['ajaxUrl'] );
        $this->assertTrue( $result['localized'][0][2]['map']['enabled'] );
        $this->assertArrayHasKey( 'district', $result['localized'][0][2]['i18n'] );
        $this->assertStringNotContainsString( 'wc-blocks', json_encode( $result['scripts'] ) );
    }

    #[Test]
    public function validation_uses_candidate_customer_then_saves_canonical_label_only_after_woo_save(): void {
        $address = self::address(); $address['address_1'] = 'New candidate street'; $address['postcode'] = '54321';
        $destination = self::destination(); $destination['shipping_address'] = $address; $destination['postcode'] = '54321'; $destination['district_label'] = 'Client supplied label';
        $result = $this->runFixture( array( 'candidate' => $address, 'meta' => self::profile(), 'post' => self::post( $destination ), 'operations' => array( 'validate', 'persist_address', 'saved', 'badges', 'form' ) ) );
        $this->assertSame( array(), $result['steps'][0]['writes'] );
        $this->assertSame( 'Canonical district', $result['steps'][0]['validated'][7]['district_label'] );
        $this->assertSame( array( '54321' ), $result['lookups'] );
        $destination['district_label'] = 'Canonical district';
        $this->assertSame( $destination, $result['destination'] );
        $this->assertSame( array(), $result['notices'] );
        $this->assertSame( $address, $result['meta'][7]['_kiriof_buyer_destination_address'] );
        $this->assertSame( array( 'latitude' => '0', 'longitude' => '0' ), $result['meta'][7]['_kiriof_buyer_destination_coordinates'] );
        $this->assertSame( '222', $result['meta'][7]['_wc_shipping/kiriminaja-official/kiriof_destination_area'] );
        foreach ( $result['writes'] as $write ) { $this->assertSame( 7, $write[0] ); $this->assertStringNotContainsString( 'billing', $write[1] ); }
        $this->assertSame( self::profile()[8], $result['meta'][8] );
        $this->assertSame( 'Billing untouched', $result['meta'][7]['billing_address_1'] );
    }

    #[Test]
    public function nonce_auth_and_shipping_isolation_prevent_writes(): void {
        foreach ( array( array( 'current_user' => 0 ), array( 'current_user' => 8 ), array( 'user_id' => 8 ), array( 'type' => 'billing' ), array( 'post' => array_replace( self::post(), array( 'kiriof_account_destination_nonce' => 'invalid' ) ) ) ) as $case ) {
            $result = $this->runFixture( $case + array( 'post' => self::post(), 'meta' => self::profile( true ), 'session' => self::staleSession() ) );
            $this->assertSame( array(), $result['writes'] );
            $this->assertSame( array(), $result['lookups'] );
            $this->assertSame( array(), $result['steps'][0]['validated'] );
            $this->assertSame( self::profile( true ), $result['meta'] );
            $this->assertSame( self::staleSession(), $result['session'] );
        }
    }

    #[Test]
    public function invalid_api_identity_snapshot_and_stale_address_never_queue_or_mutate_saved_profile(): void {
        $cases = array();
        foreach ( array( 'unknown', 'failure', 'throw', 'malformed' ) as $mode ) { $cases[] = array( 'lookup_mode' => $mode ); }
        foreach ( array( 'district_id' => '0', 'destination_latitude' => 'NaN', 'destination_longitude' => '181', 'postcode' => '99999', 'country' => 'US' ) as $field => $value ) {
            $destination = self::destination(); $destination[$field] = $value; $cases[] = array( 'post' => self::post( $destination ) );
        }
        foreach ( array_keys( self::address() ) as $field ) {
            $address = self::address(); $address[$field] = 'country' === $field ? 'US' : ( 'postcode' === $field ? '54321' : 'Changed' );
            if ( 'country' !== $field ) { $cases[] = array( 'candidate' => $address ); }
        }
        $cases[] = array( 'post' => array_replace( self::post(), array( 'kiriof_account_district' => '333' ) ) );
        $cases[] = array( 'post' => array_replace( self::post(), array( 'kiriof_account_destination' => '{broken' ) ) );
        $cases[] = array( 'rows' => array( array( 'id' => 222, 'text' => '<script>alert(1)</script>' ) ) );
        foreach ( $cases as $case ) {
            $result = $this->runFixture( $case + array( 'meta' => self::profile( true ), 'post' => self::post(), 'session' => self::staleSession() ) );
            $this->assertSame( array(), $result['writes'], json_encode( $case ) );
            $this->assertSame( array(), $result['steps'][0]['validated'], json_encode( $case ) );
            $this->assertSame( self::profile( true ), $result['meta'] );
            $this->assertSame( self::staleSession(), $result['session'] );
            $this->assertNotEmpty( $result['notices'] );
        }
    }

    #[Test]
    public function failed_revalidation_discards_previously_queued_destination(): void {
        $bad = self::post(); $bad['kiriof_account_destination_nonce'] = 'invalid';
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'post' => self::post(), 'operations' => array( 'validate', array( 'post' => $bad ), 'clear_errors', 'saved' ) ) );
        $this->assertNotEmpty( $result['steps'][0]['validated'] );
        $this->assertSame( array(), $result['steps'][1]['validated'] );
        $this->assertSame( array(), $result['writes'] );
    }

    #[Test]
    public function no_javascript_native_select_with_empty_hidden_snapshot_saves_api_canonical_identity(): void {
        $post = self::post(); $post['kiriof_account_destination'] = '';
        $result = $this->runFixture( array( 'meta' => self::profile(), 'post' => $post ) );
        $this->assertSame( array(), $result['steps'][0]['writes'] );
        $this->assertSame( self::destination( false ), $result['destination'] );
        $this->assertSame( array(), $result['notices'] );
        $this->assertArrayNotHasKey( '_kiriof_buyer_destination_coordinates', $result['meta'][7] );
    }

    #[Test]
    public function no_javascript_changed_select_rejects_existing_hidden_identity_mismatch(): void {
        $post = self::post(); $post['kiriof_account_district'] = '333';
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'post' => $post ) );
        $this->assertSame( array(), $result['writes'] );
        $this->assertSame( array(), $result['lookups'] );
        $this->assertSame( array(), $result['steps'][0]['validated'] );
        $this->assertNotEmpty( $result['notices'] );
    }

    #[Test]
    public function non_indonesian_shipping_save_clears_district_and_pin_without_api_lookup(): void {
        $address = self::address(); $address['country'] = 'US'; $address['postcode'] = '90210';
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'candidate' => $address, 'post' => self::post(), 'session' => self::staleSession(), 'operations' => array( 'validate', 'persist_address', 'saved' ) ) );
        $this->assertSame( array(), $result['lookups'] );
        $this->assertSame( array( 'district_id' => '', 'district_label' => '', 'postcode' => '90210', 'country' => 'US', 'address_type' => 'shipping', 'version' => 1 ), $result['destination'] );
        $this->assertArrayNotHasKey( '_kiriof_buyer_destination_coordinates', $result['meta'][7] );
        $this->assertSame( '', $result['meta'][7]['shipping_kiriof_destination_area'] );
        $this->assertNull( $result['session']['kiriof_buyer_destination_coordinates'] );
        $this->assertArrayNotHasKey( 'kiriof_destination_postcode_map', $result['session'] );
    }

    #[Test]
    public function successful_account_save_replaces_all_stale_session_aliases(): void {
        $result = $this->runFixture( array( 'meta' => self::profile(), 'post' => self::post(), 'session' => self::staleSession() ) );
        $this->assertSame( self::destination(), $result['session']['kiriof_buyer_destination'] );
        $this->assertSame( array( 'latitude' => '0', 'longitude' => '0' ), $result['session']['kiriof_buyer_destination_coordinates'] );
        foreach ( array( 'destination_id', 'shipping_destination_id', 'kiriof_destination_area' ) as $key ) { $this->assertSame( '222', $result['session'][$key] ); }
        foreach ( array( 'destination_name', 'shipping_destination_name', 'kiriof_destination_area_name' ) as $key ) { $this->assertSame( 'Canonical district', $result['session'][$key] ); }
        $this->assertSame( '12345', $result['session']['kiriof_checkout_postcode'] );
        $this->assertSame( '1', $result['session']['kiriof_checkout_token'] );
        $this->assertSame( array( '12345' => array( 'destination_id' => '222', 'destination_name' => 'Canonical district' ) ), $result['session']['kiriof_destination_postcode_map'] );
        $this->assertSame( 'keep', $result['session']['unrelated'] );
    }

    #[Test]
    public function account_save_never_resets_another_wc_customers_session(): void {
        $result = $this->runFixture( array( 'meta' => self::profile(), 'post' => self::post(), 'customer_id' => 8, 'session' => self::staleSession() ) );
        $this->assertSame( self::destination(), $result['destination'] );
        $this->assertSame( self::staleSession(), $result['session'] );
    }

    #[Test]
    public function other_woo_validation_errors_prevent_persistence_and_session_changes(): void {
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'post' => self::post(), 'session' => self::staleSession(), 'operations' => array( 'validate', 'other_error', 'saved' ) ) );
        $this->assertSame( array(), $result['writes'] );
        $this->assertSame( self::profile( true ), $result['meta'] );
        $this->assertSame( self::staleSession(), $result['session'] );
    }

    #[Test]
    public function absent_account_fields_degrade_safely_without_plugin_writes(): void {
        $result = $this->runFixture( array( 'meta' => self::profile( true ) ) );
        $this->assertSame( array(), $result['writes'] );
        $this->assertSame( array(), $result['lookups'] );
        $this->assertSame( array(), $result['notices'] );
        $this->assertSame( array(), $result['steps'][0]['validated'] );
    }

    #[Test]
    public function failed_submission_redisplays_posted_json_safely_instead_of_saved_selection(): void {
        $destination = self::destination( false ); $destination['district_id'] = '333'; $destination['district_label'] = 'District " onfocus="alert(1) & Co';
        $post = self::post( $destination ); $post['kiriof_account_district'] = '333';
        // Model PHP's slashed request strings, so wp_unslash reproduces the actual JSON.
        $post['kiriof_account_destination'] = addslashes( $post['kiriof_account_destination'] );
        $result = $this->runFixture( array( 'meta' => self::profile( true ), 'post' => $post, 'lookup_mode' => 'failure', 'operations' => array( 'validate', 'form' ) ) );
        $this->assertSame( '333', $result['fields'][0][2] );
        $this->assertSame( $destination['district_label'], $result['fields'][0][1]['options'][333] );
        $this->assertStringContainsString( '&quot;', $result['html'] );
        $this->assertStringContainsString( '&amp; Co', $result['html'] );
        $this->assertStringNotContainsString( ' onfocus="alert(1)', $result['html'] );
        $this->assertStringNotContainsString( '<script', $result['html'] );
        $this->assertSame( array(), $result['writes'] );
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
