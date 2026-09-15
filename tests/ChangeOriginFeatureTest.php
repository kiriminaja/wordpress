<?php

use PHPUnit\Framework\TestCase;

class ChangeOriginFeatureTest extends TestCase {
    private function read( string $path ): string {
        $content = file_get_contents( $path );
        $this->assertIsString( $content );

        return $content;
    }

    public function testControllerRegistersChangeOriginAjaxEndpoints(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );

        $this->assertStringContainsString( "add_action('wp_ajax_kiriof_change_origin_check', array(\$this, 'changeOriginCheck'));", $source );
        $this->assertStringContainsString( "add_action('wp_ajax_kiriof_change_origin', array(\$this, 'changeOrigin'));", $source );
        $this->assertStringContainsString( 'public function changeOriginCheck()', $source );
        $this->assertStringContainsString( 'public function changeOrigin()', $source );
    }

    public function testChangeOriginEndpointsAreGuardedByNonceAndCapability(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );

        foreach ( array( 'changeOriginCheck', 'changeOrigin' ) as $method ) {
            $this->assertMatchesRegularExpression(
                '/public function ' . $method . '\(\)\s*\{\s*if \(! current_user_can\( \'manage_woocommerce\' \)\)/',
                $source
            );
            $this->assertMatchesRegularExpression(
                '/public function ' . $method . '\(\)[\s\S]{0,600}wp_verify_nonce\(/',
                $source
            );
        }

        $this->assertStringContainsString( "Origin can only be changed before pickup is requested.", $source );
    }

    public function testShippingCheckUsesTransactionPackageAndSelectedOrigin(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );

        $this->assertStringContainsString( "'origin_sub_district_id' => (int) \$kiriof_origin['origin_sub_district_id'],", $source );
        $this->assertStringContainsString( "'destination_area_id'    => (int) \$kiriof_transaction->destination_sub_district_id,", $source );
        $this->assertStringContainsString( "'package_overrides'      => array(", $source );
        $this->assertStringContainsString( "'weight'     => (int) \$kiriof_transaction->weight,", $source );
    }

    public function testChangeOriginPersistsLocationSnapshotOnTransaction(): void {
        $controller   = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );
        $repository   = $this->read( __DIR__ . '/../inc/Repositories/TransactionRepository.php' );

        $this->assertStringContainsString( 'updateTransactionShipmentLocation( $order_id, $location_id, $kiriof_snapshot, $kiriof_courier_update )', $controller );
        $this->assertStringContainsString( 'locationToOrigin( $kiriof_location )', $controller );
        $this->assertStringNotContainsString( '$kiriof_location->sender_name', $controller );
        $this->assertStringContainsString( 'public function updateTransactionShipmentLocation( string $kaOrderId, int $locationId, string $snapshot, array $courier = array() ): bool {', $repository );
        $this->assertStringContainsString( "'shipment_location_id'       => \$locationId,", $repository );
        $this->assertStringContainsString( "'shipment_location_snapshot' => \$snapshot,", $repository );
    }

    public function testChangeOriginAddsPrivateWooCommerceOrderNote(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );

        $this->assertStringContainsString( 'wc_get_order( (int) $kiriof_transaction->wp_wc_order_stat_order_id )', $source );
        $this->assertStringContainsString( '$kiriof_note_id = $kiriof_wc_order->add_order_note(', $source );
        $this->assertSame( 1, substr_count( $source, '$kiriof_wc_order->add_order_note(' ) );
        $this->assertStringContainsString( 'Shipment fulfillment updated by %s.', $source );
        $this->assertStringContainsString( 'Origin: %1$s → %2$s', $source );
        $this->assertStringContainsString( 'Courier: %1$s → %2$s', $source );
        $this->assertStringContainsString( 'Shipping cost: %1$s → %2$s (%3$s)', $source );
        $this->assertStringContainsString( 'Calculated order total: %1$s → %2$s (%3$s)', $source );
        $this->assertStringNotContainsString( 'Courier changed with seller consent to %s.', $source );
        $this->assertStringContainsString( 'wp_get_current_user()', $source );
        $this->assertStringContainsString( "update_meta_data( '_kiriof_expedition_code'", $source );
        $this->assertStringContainsString( '$kiriof_wc_order->save()', $source );
    }

    public function testTransactionsListRendersChangeOriginButtonForProcessableRows(): void {
        $template = $this->read( __DIR__ . '/../templates/transaction-process/view/index.php' );

        $this->assertStringContainsString( 'kiriof-change-origin-button', $template );
        $this->assertStringContainsString( 'data-ka-order-id="', $template );
        $this->assertStringContainsString( 'data-current-origin="', $template );
        $this->assertStringContainsString( 'data-current-origin-address="', $template );
        $this->assertStringContainsString( 'data-current-location-id="', $template );
        $this->assertStringContainsString( '$kiriof_origin_location ? (int) $kiriof_origin_location->id : 0', $template );
        $this->assertStringContainsString( "\$kiriof_origin_snapshot['origin_name'] ?? \$kiriof_origin_snapshot['location_name']", $template );
        $this->assertStringContainsString( 'data-nonce="', $template );
        $this->assertMatchesRegularExpression( '/\$kiriof_isProcessable\s*\?[^;]*kiriof-change-origin-button/s', $template );
        $this->assertStringContainsString( 'dashicons-location', $template );
    }

    public function testChangeOriginModalTemplateUsesSelect2AndAutomaticCheckFlow(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );

        $this->assertStringContainsString( 'tmpl-kiriof-modal-change-origin', $source );
        $this->assertStringContainsString( 'Change Shipment Origin', $source );
		$this->assertStringContainsString( 'esc_html_e( \'Current shipment origin\', \'kiriminaja-official\' )', $source );
		$this->assertStringContainsString( 'esc_html_e( \'New shipment origin\', \'kiriminaja-official\' )', $source );
        $this->assertStringContainsString( 'name="location_id" class="wc-enhanced-select"', $source );
        $this->assertStringContainsString( '<option value=""></option>', $source );
        $this->assertStringNotContainsString( 'id="kiriof-change-origin-check"', $source );
        $this->assertStringContainsString( 'id="kiriof-change-origin-confirm" disabled', $source );
        $this->assertStringContainsString( 'getAll( true )', $source );
        $this->assertStringContainsString( 'data-current-location-id="{{ data.current_location_id }}"', $source );
        $this->assertStringContainsString( '{{ data.current_origin_address }}', $source );
        $this->assertStringContainsString( 'Manage shipment locations', $source );
        $this->assertStringContainsString( 'No alternative shipment locations are available.', $source );
        $this->assertStringContainsString( 'formatAddress( $kiriof_location_option )', $source );
        $this->assertStringContainsString( 'admin.php?page=wc-settings&tab=kiriminaja_warehouses', $source );
        $this->assertStringNotContainsString( 'tab=general#kiriof-shipment-locations', $source );
    }

    public function testChangeOriginScriptIsEnqueuedOnTransactionPage(): void {
        $enqueue = $this->read( __DIR__ . '/../inc/Base/Enqueue.php' );
        $js      = $this->read( __DIR__ . '/../assets/js/kiriof-change-origin.js' );
        $css     = $this->read( __DIR__ . '/../assets/admin/css/kj-admin-style.css' );
		$source  = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );

        $this->assertStringContainsString( "'kiriof-change-origin',", $enqueue );
        $this->assertStringContainsString( 'assets/js/kiriof-change-origin.js', $enqueue );
        $this->assertFileExists( __DIR__ . '/../build/kiriminaja-official/assets/js/kiriof-change-origin.js' );
        $this->assertSame(
            hash_file( 'sha256', __DIR__ . '/../assets/js/kiriof-change-origin.js' ),
            hash_file( 'sha256', __DIR__ . '/../build/kiriminaja-official/assets/js/kiriof-change-origin.js' ),
            'Packaged Change Origin JavaScript must match source.'
        );
        $this->assertStringContainsString( "'kiriminaja-transaction-process' === \$page", $enqueue );

        $this->assertStringContainsString( "action: 'kiriof_change_origin_check',", $js );
        $this->assertStringContainsString( "action: 'kiriof_change_origin',", $js );
        $this->assertStringContainsString( ".kiriof-change-origin-button", $js );
        $this->assertStringContainsString( "select2", $js );
        $this->assertStringContainsString( 'change.kiriofChangeOrigin', $js );
        $this->assertStringContainsString( "var hasAlternatives = \$select.find('option[value!=\"\"]')", $js );
        $this->assertStringContainsString( "String(\$(this).val()) === currentLocationId", $js );
        $this->assertStringNotContainsString( 'optionName.indexOf(currentName)', $js );
        $this->assertStringContainsString( "dropdownParent: \$modal.find('.kiriof-change-origin-modal-content')", $js );
        $this->assertStringContainsString( 'comparison.available', $js );
		$this->assertStringContainsString( '$kiriof_is_replacement', $source );
		$this->assertStringContainsString( '$kiriof_selected_service !== $kiriof_previous_service', $source );
		$this->assertStringContainsString( '$kiriof_is_replacement && ! $courier_consent', $source );
		$this->assertStringContainsString( ".kiriof-replacement-consent').prop('checked') ? 1 : 0", $js );
        $this->assertStringContainsString( "typeof response.data === 'object'", $js );
        $this->assertStringContainsString( 'Array.isArray(payload.replacement_options)', $js );
        $this->assertStringContainsString( 'courier_discount: selectedDiscount', $js );
        $this->assertSame( 1, substr_count( $js, 'template = template.replace(/\\{\\{ data\\.current_location_id \\}\\}/g, String(currentLocationId));' ) );
        $this->assertStringContainsString( 'try {', $js );
        $this->assertStringContainsString( 'JSON.parse', $js );
        $this->assertStringContainsString( '} catch (error) {', $js );
        $this->assertStringContainsString( 'kiriof-replacement-courier', $js );
        $this->assertStringContainsString( 'kiriof-replacement-consent', $js );
        $this->assertStringContainsString( "$('#tmpl-kiriof-modal-change-origin').html()", $js );
        $this->assertStringContainsString( "'wc-backbone-modal'", $enqueue );
        $this->assertStringContainsString( "action: 'woocommerce_get_order_details'", $js );
        $this->assertStringContainsString( "template: 'wc-modal-view-order'", $js );
        $this->assertStringContainsString( '$(document.body).WCBackboneModal', $js );
        $this->assertStringContainsString( 'function lockPageScroll()', $js );
        $this->assertStringContainsString( "body.style.position = 'fixed'", $js );
        $this->assertStringContainsString( 'body.style.top =', $js );
        $this->assertStringContainsString( 'function unlockPageScroll()', $js );
        $this->assertStringContainsString( 'window.scrollTo(state.scrollLeft, state.scrollTop)', $js );
        $this->assertStringContainsString( 'function closeModal($modal)', $js );
        $this->assertStringContainsString( "$(this).select2('destroy')", $js );
        $this->assertStringContainsString( "kiriof-change-origin-empty-state", $js );
        $this->assertStringContainsString( 'height: auto !important;', $css );
        $this->assertStringContainsString( 'min-height: 0 !important;', $css );
        $this->assertStringContainsString( 'padding: 12px 28px !important;', $css );
    }

    public function testPickupRequestFallsBackToStoredTransactionOrigin(): void {
        $service = $this->read( __DIR__ . '/../inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php' );

        $this->assertStringContainsString( 'getTransactionByOrderIds($this->orderIds)', $service );
        $this->assertStringContainsString( '$transaction->shipment_location_id', $service );
        $this->assertStringContainsString( 'count($savedLocationIds) > 1', $service );
        $this->assertStringContainsString( 'getDefaultLocation()', $service );
    }

    public function testOngkirPricingServiceSupportsOriginAndPackageOverrides(): void {
        $service = $this->read( __DIR__ . '/../inc/Services/CheckoutServices/OngkirPricingService.php' );
        $controller = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );

        $this->assertStringContainsString( 'private int $origin_sub_district_id = 0;', $service );
        $this->assertStringContainsString( 'private array $package_overrides = [];', $service );
        $this->assertStringContainsString( "\$payload['origin_sub_district_id']", $service );
        $this->assertStringContainsString( "\$payload['package_overrides']", $service );
        $this->assertStringContainsString( "? \$payload['wc_cart_contents']", $service );
        $this->assertStringContainsString( ': [];', $service );
        $this->assertStringContainsString( '$kiriof_pricing->status()', $controller );
        $this->assertStringContainsString( '$kiriof_pricing->data()', $controller );
        $this->assertStringContainsString( "'price' => \$kiriof_price", $service );
        $this->assertStringContainsString( '$kiriof_display_name = kiriof_helper()->formatServiceName', $service );
        $this->assertStringContainsString( "'courier' => \$kiriof_display_name", $service );
        $this->assertStringNotContainsString( '$kiriof_pricing[\'error\']', $controller );
    }

    public function testShippingCheckExposesCourierAndOrderPriceImpact(): void {
        $controller = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );
        $script     = $this->read( __DIR__ . '/../assets/js/kiriof-change-origin.js' );

        foreach ( array( 'previous_courier', 'new_courier', 'previous_paid_shipping', 'new_paid_shipping', 'previous_discount', 'new_discount', 'previous_total', 'new_total', 'total_delta' ) as $field ) {
            $this->assertStringContainsString( "'{$field}'", $controller );
        }

        foreach ( array( 'Courier', 'Shipping', 'Shipping discount', 'Order total', '↑', '↓', '→' ) as $label ) {
            $this->assertStringContainsString( $label, $script );
        }

        $this->assertStringNotContainsString( 'Order price impact', $script );
        $this->assertStringNotContainsString( 'renderImpactDetails', $script );
        $this->assertStringContainsString( 'if (previousDiscount !== 0 || newDiscount !== 0)', $script );
        $this->assertStringContainsString( 'discountRow =', $script );
        $styles = $this->read( __DIR__ . '/../assets/admin/css/kj-admin-style.css' );
        $this->assertStringContainsString( 'height: fit-content !important;', $styles );
        $this->assertMatchesRegularExpression(
            '/\.wc-backbone-modal-main\s*\{[^}]*margin:\s*0\s*!important;[^}]*padding:\s*0\s*!important;/s',
            $styles
        );

        $this->assertStringNotContainsString( "text('previousShipping'", $script );
        $this->assertStringNotContainsString( "text('newShipping'", $script );
    }

    public function testShippingRateDataKeepsRawCostAndApiDiscountSeparated(): void {
        $service    = $this->read( __DIR__ . '/../inc/Services/CheckoutServices/OngkirPricingService.php' );
        $controller = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );
        $repository = $this->read( __DIR__ . '/../inc/Repositories/TransactionRepository.php' );

        $this->assertStringContainsString( "'raw_price' => \$kiriof_raw_price", $service );
        $this->assertStringContainsString( "'discount_amount' => \$kiriof_discount", $service );
        $this->assertStringContainsString( "'service_name' => \$kiriof_service_type", $service );
        $this->assertStringContainsString( "\$kiriof_option['raw_price'] ?? \$kiriof_option['price']", $controller );
        $this->assertStringContainsString( "'discount_amount' => \$courier_discount", $controller );
        $this->assertStringContainsString( "array( 'service', 'service_name', 'shipping_cost', 'discount_amount' )", $repository );
        $this->assertStringContainsString( 'updateTransactionByCallbackVerified(', $repository );
    }

    public function testServerRejectsSelectingTheCurrentOriginAndUnvalidatedConfirm(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );

        $this->assertSame( 2, substr_count( $source, 'Please select a different shipment origin.' ) );
        $this->assertStringContainsString( 'Please run the shipping check again before confirming.', $source );
        $this->assertStringContainsString( 'getVerifiedChangeOriginRate(', $source );
        $this->assertStringContainsString( 'Client-provided prices are intentionally ignored.', $source );
        $this->assertStringContainsString( 'The selected courier is no longer available. Please run the shipping check again.', $source );
        $this->assertStringNotContainsString( "\$courier_price        = isset( \$_POST['courier_price'] )", $source );
        $this->assertStringNotContainsString( "\$courier_discount     = isset( \$_POST['courier_discount'] )", $source );
        $this->assertStringContainsString( "\$courier_price        = (float) \$kiriof_verified_rate['raw_price'];", $source );
        $this->assertStringContainsString( "\$courier_discount     = min( \$courier_price, max( 0, (float) \$kiriof_verified_rate['discount_amount'] ) );", $source );
        $this->assertStringContainsString( '$wpdb->query( \'START TRANSACTION\' )', $source );
        $this->assertStringContainsString( '$wpdb->query( \'ROLLBACK\' )', $source );
        $this->assertStringContainsString( '$wpdb->query( \'COMMIT\' )', $source );
        $this->assertStringContainsString( 'WooCommerce order not found. No shipment data was changed.', $source );
        $this->assertStringContainsString( 'Failed to update the shipment origin. No shipment data was changed.', $source );
    }

    public function testPackageDetailsUsePersistedKaDiscountInsteadOfInferringPriceIncreaseAsDiscount(): void {
        $transaction = $this->read( __DIR__ . '/../templates/transaction-process/view/index.php' );
        $preview     = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );
        $metabox     = $this->read( __DIR__ . '/../templates/order/metabox-shipping.php' );

        $this->assertStringContainsString( '$kiriof_colShipDiscount = max(0.0, $kiriof_discountAmount);', $transaction );
        $this->assertStringNotContainsString( '$kiriof_shippingCost - (float) $kiriof_wcOrder->get_shipping_total()', $transaction );
        $this->assertStringContainsString( '$wc_shipping_discount = max(0.0, (float) ($transaction->discount_amount ?? 0));', $preview );
        $this->assertStringNotContainsString( '$shipping_cost - (float) $order->get_shipping_total()', $preview );
        $this->assertStringContainsString( '$kiriof_wc_shipping_discount = max(0.0, $kiriof_discount_raw);', $metabox );
        $this->assertStringNotContainsString( 'max($kiriof_discount_raw, $wc_discount_total)', $metabox );
    }
}
