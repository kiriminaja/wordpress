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
        $this->assertStringContainsString( '$kiriof_wc_order->get_items( \'shipping\' )', $source );
        $this->assertStringContainsString( '$kiriof_shipping_item->set_total( $kiriof_new_paid )', $source );
        $this->assertStringContainsString( '$kiriof_shipping_item->calculate_taxes()', $source );
        $this->assertStringContainsString( '$kiriof_wc_order->calculate_totals( false )', $source );
    }

    public function testTransactionsListRendersChangeOriginButtonForProcessableRows(): void {
		$template = $this->read( __DIR__ . '/../src/lib/transactions/TransactionsApp.svelte' );
		$factory = $this->read( __DIR__ . '/../inc/Services/TransactionListViewModelFactory.php' );

        $this->assertStringContainsString( 'kiriof-change-origin-button', $template );
		$this->assertStringContainsString( 'data-ka-order-id={row.kaOrderId}', $template );
		$this->assertStringContainsString( 'data-current-origin={row.actionData.currentOrigin}', $template );
		$this->assertStringContainsString( 'data-current-origin-address={row.actionData.currentOriginAddress}', $template );
		$this->assertStringContainsString( 'data-current-location-id={row.actionData.currentLocationId}', $template );
		$this->assertStringContainsString( "'currentLocationId'", $factory );
		$this->assertStringContainsString( "'currentOrigin'", $factory );
		$this->assertStringContainsString( 'data-nonce={row.actionData.nonce}', $template );
		$this->assertStringContainsString( '{#if row.actions.changeOrigin}', $template );
		$this->assertStringContainsString( '<IconMapPin />', $template );
    }

    public function testChangeOriginModalTemplateUsesRadioCardsAndAutomaticCheckFlow(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );

        $this->assertStringContainsString( 'tmpl-kiriof-modal-change-origin', $source );
        $this->assertStringContainsString( 'Change Shipment Origin', $source );
		$this->assertStringNotContainsString( 'esc_html_e( \'Current shipment origin\', \'kiriminaja-official\' )', $source );
		$this->assertStringContainsString( 'esc_html_e( \'Shipment origin\', \'kiriminaja-official\' )', $source );
        $this->assertStringContainsString( 'kiriof-origin-radio-group', $source );
        $this->assertStringContainsString( 'kiriof-origin-selection-summary', $source );
        $this->assertStringContainsString( 'kiriof-origin-field-header', $source );
        $this->assertSame( 1, substr_count( $source, 'Manage shipment locations' ) );
        $this->assertStringContainsString( 'kiriof-origin-toggle', $source );
        $this->assertStringContainsString( 'kiriof-origin-collapse', $source );
        $this->assertStringContainsString( 'type="radio" name="location_id" value="{{ data.current_location_id }}" checked', $source );
        $this->assertStringContainsString( 'data-location-id="<?php echo esc_attr( $kiriof_location_option->id ); ?>"', $source );
        $this->assertStringNotContainsString( '<select id="kiriof-change-origin-to"', $source );
        $this->assertStringNotContainsString( 'id="kiriof-change-origin-check"', $source );
        $this->assertStringContainsString( 'id="kiriof-change-origin-confirm" disabled', $source );
        $this->assertStringContainsString( 'getAll( true )', $source );
        $this->assertStringContainsString( '{{ data.current_origin_address }}', $source );
        $this->assertSame( 2, substr_count( $source, '{{ data.current_origin_address }}' ) );
        $this->assertStringNotContainsString( 'kiriof-change-origin-current', $source );
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
        $this->assertStringContainsString( '$loading.show()', $js );
        $this->assertStringContainsString( '$spinner.addClass(\'is-active\')', $js );
        $this->assertStringNotContainsString( ".html('<p>' + text('checkingShipping'", $js );
        $this->assertStringContainsString( "action: 'kiriof_change_origin',", $js );
        $this->assertStringContainsString( ".kiriof-change-origin-button", $js );
        $this->assertStringNotContainsString( "select2", $js );
        $this->assertStringContainsString( 'change.kiriofChangeOrigin', $js );
        $this->assertStringContainsString( '.kiriof-radio-card:not(.kiriof-radio-card-current)', $js );
        $this->assertStringContainsString( 'input[name="location_id"]:checked', $js );
        $this->assertStringContainsString( 'function resetShippingResult()', $js );
        $this->assertStringContainsString( 'resetShippingResult();', $js );
        $this->assertStringContainsString( '$modal.data(\'shipping-check\', { comparison: null })', $js );
        $this->assertStringNotContainsString( 'optionName.indexOf(currentName)', $js );
        $this->assertStringContainsString( 'function applyCourierSelection($modal)', $js );
        $this->assertStringContainsString( 'renderOrderBreakdown(option)', $js );
        $this->assertStringContainsString( '$modal.data(\'courier-comparisons\', courierComparisons)', $js );
        $this->assertStringContainsString( 'var optionMap = $modal.data(\'courier-comparisons\') || {}', $js );
        $this->assertStringNotContainsString( 'data-option=', $js );
        $this->assertStringContainsString( 'input[name="courier_option"]:checked', $js );
        $this->assertStringContainsString( 'kiriof-courier-radio-group', $js );
        $this->assertStringContainsString( '<h2 class="kiriof-courier-radio-title">', $source );
        $this->assertStringNotContainsString( '<h2 class="kiriof-courier-radio-title">', $js );
        $this->assertStringContainsString( 'kiriof-courier-radio-card', $js );
        $this->assertStringContainsString( 'kiriof-courier-radio-row', $js );
        $this->assertStringContainsString( 'kiriof-courier-radio-name', $js );
        $this->assertStringContainsString( 'kiriof-courier-radio-price', $js );
        $this->assertStringContainsString( 'kiriof-courier-selection-summary', $js );
        $this->assertStringContainsString( 'kiriof-courier-toggle', $js );
        $this->assertStringContainsString( 'kiriof-courier-collapse', $js );
        $this->assertStringContainsString( 'kiriof-pricing-impact', $js );
        $this->assertStringContainsString( "var tone = next > previous ? 'increase'", $js );
        $this->assertStringContainsString( "next < previous ? 'decrease'", $js );
        $this->assertStringContainsString( 'total_was_clamped', $js );
        $this->assertStringContainsString( 'is_total_blocked', $js );
        $this->assertStringContainsString( 'required_refund', $js );
        $this->assertStringContainsString( 'kiriof-change-value-blocked', $js );
        $this->assertStringContainsString( "text('changeBlocked'", $js );
        $this->assertStringContainsString( "text('refundRequired'", $js );
        $this->assertStringContainsString( 'Math.max(0, rawNewTotal)', $js );
        $this->assertStringNotContainsString( "courier-consent-granted", $js );
        $this->assertStringContainsString( '$modal.find(\'.kiriof-replacement-consent\').prop(\'checked\', false)', $js );
        $this->assertStringContainsString( '$replacement.toggle(!comparison.available)', $js );
        $this->assertStringContainsString( "var checked = index === 0 ? ' checked' : '';", $js );
        $this->assertStringNotContainsString( "var checked = comparison.available && index === 0 ? ' checked' : '';", $js );
        $this->assertStringContainsString( 'Array.isArray(payload.options)', $js );
        $this->assertStringContainsString( 'courierOptions.unshift(courierOptions.splice(matchedIndex, 1)[0])', $js );
        $this->assertStringContainsString( 'comparison.available', $js );
		$this->assertStringContainsString( '$kiriof_is_replacement', $source );
		$this->assertStringContainsString( '$kiriof_selected_service !== $kiriof_previous_service', $source );
		$this->assertStringContainsString( '$kiriof_is_replacement && ! $courier_consent', $source );
		$this->assertStringContainsString( "isReplacement && \$modal.find('.kiriof-replacement-consent').prop('checked') ? 1 : 0", $js );
        $this->assertStringContainsString( "typeof response.data === 'object'", $js );
        $this->assertStringContainsString( 'Array.isArray(payload.replacement_options)', $js );
        $this->assertStringContainsString( "'options' => array_values( \$kiriof_normalized )", $source );
        $this->assertStringContainsString( 'courier_discount: selectedDiscount', $js );
        $this->assertSame( 1, substr_count( $js, 'template = template.replace(/\\{\\{ data\\.current_location_id \\}\\}/g, String(currentLocationId));' ) );
        $this->assertStringNotContainsString( 'data-option=', $js );
        $this->assertStringContainsString( '($modal.data(\'courier-comparisons\') || {})', $js );
        $this->assertStringNotContainsString( 'kiriof-replacement-courier', $js );
        $this->assertStringContainsString( 'kiriof-replacement-consent', $js );
        $this->assertStringContainsString( "$('#tmpl-kiriof-modal-change-origin').html()", $js );
        $this->assertStringContainsString( "'wc-backbone-modal'", $enqueue );
        $this->assertStringContainsString( "action: 'woocommerce_get_order_details'", $js );
        $this->assertStringContainsString( "template: 'wc-modal-view-order'", $js );
        $this->assertStringContainsString( '$(document.body).WCBackboneModal', $js );
        $this->assertStringContainsString( 'function lockPageScroll()', $js );
        $this->assertStringNotContainsString( "body.style.overflow = 'hidden'", $js );
        $this->assertStringNotContainsString( "html.style.overflow = 'hidden'", $js );
        $this->assertStringNotContainsString( "addClass('kiriof-change-origin-scroll-locked')", $js );
        $this->assertStringNotContainsString( "body.style.position = 'fixed'", $js );
        $this->assertStringNotContainsString( 'body.style.top =', $js );
        $this->assertStringContainsString( 'function unlockPageScroll()', $js );
        $this->assertStringContainsString( 'window.scrollTo(state.scrollLeft, state.scrollTop)', $js );
        $this->assertStringContainsString( 'function closeModal($modal)', $js );
        $this->assertStringContainsString( "kiriof-change-origin-empty-state", $js );
        $this->assertStringContainsString( 'height: auto !important;', $css );
        $this->assertStringContainsString( 'min-height: 0 !important;', $css );
        $this->assertStringContainsString( 'padding: 12px 28px !important;', $css );
        $this->assertStringContainsString( '.kiriof-radio-card-group', $css );
        $this->assertStringContainsString( '.kiriof-compact-selection', $css );
        $this->assertStringContainsString( '.kiriof-origin-field-header', $css );
        $this->assertStringContainsString( '.kiriof-choice-panel', $css );
        $this->assertStringContainsString( '.kiriof-pricing-impact', $css );
        $this->assertStringContainsString( '.kiriof-change-value-increase strong', $css );
        $this->assertStringContainsString( '.kiriof-change-value-decrease strong', $css );
        $this->assertStringContainsString( '.kiriof-total-anomaly', $css );
        $this->assertStringContainsString( '.kiriof-change-value-blocked strong', $css );
        $this->assertStringContainsString( '.kiriof-radio-card:has(input:checked)', $css );
        $this->assertStringContainsString( '.kiriof-courier-radio-row', $css );
        $this->assertStringContainsString( '.kiriof-change-origin-replacement', $css );
        $this->assertStringContainsString( 'background: transparent;', $css );
        $this->assertStringContainsString( 'justify-content: space-between;', $css );
        $this->assertStringContainsString( 'margin-left: auto;', $css );
        $this->assertStringContainsString( 'aria-live="polite"', $source );
        $this->assertStringContainsString( 'kiriof-replacement-consent-wrap', $source );
        $this->assertStringContainsString( '<div class="kiriof-replacement-consent-wrap"', $source );
        $this->assertStringNotContainsString( '<p class="kiriof-replacement-consent-wrap"', $source );
        $this->assertStringContainsString( '.kiriof-replacement-consent-wrap label', $css );
        $this->assertStringContainsString( '.kiriof-replacement-consent-wrap input[type="checkbox"]', $css );
        $this->assertStringContainsString( 'position: static !important;', $css );
        $this->assertStringContainsString( 'float: none !important;', $css );
        $this->assertStringContainsString( 'clear: both;', $css );
        $this->assertStringNotContainsString( 'body > .select2-container--open', $css );
		$this->assertStringContainsString( '.kiriof-change-origin-backdrop', $css );
		$this->assertStringContainsString( 'background: transparent !important;', $css );
		$this->assertSame( 1, substr_count( $source, 'kiriof-change-origin-backdrop' ) );
    }

    public function testPickupRequestFallsBackToStoredTransactionOrigin(): void {
        $service = $this->read( __DIR__ . '/../inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php' );

        $this->assertStringContainsString( 'getTransactionByOrderIds($this->orderIds)', $service );
        $this->assertStringContainsString( '$transaction->shipment_location_id', $service );
        $this->assertStringContainsString( '$transaction->shipment_location_snapshot', $service );
        $this->assertStringContainsString( '$snapshotLocationId > 0', $service );
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
        $js     = $this->read( __DIR__ . '/../assets/js/kiriof-change-origin.js' );

        $this->assertSame( 2, substr_count( $source, 'Please select a different shipment origin.' ) );
        $this->assertStringContainsString( 'Please run the shipping check again before confirming.', $source );
        $this->assertStringContainsString( 'getVerifiedChangeOriginRate(', $source );
        $this->assertStringContainsString( 'Client-provided prices are intentionally ignored.', $source );
        $this->assertStringContainsString( 'The selected courier is no longer available. Please run the shipping check again.', $source );
        $this->assertStringNotContainsString( "\$courier_price        = isset( \$_POST['courier_price'] )", $source );
        $this->assertStringNotContainsString( "\$courier_discount     = isset( \$_POST['courier_discount'] )", $source );
        $this->assertStringContainsString( "\$courier_price        = (float) \$kiriof_verified_rate['raw_price'];", $source );
        $this->assertStringContainsString( "\$courier_discount     = min( \$courier_price, max( 0, (float) \$kiriof_verified_rate['discount_amount'] ) );", $source );
        $this->assertStringContainsString( '$this->transactionManager->begin()', $source );
        $this->assertSame( 2, substr_count( $source, '$this->transactionManager->rollback()' ) );
        $this->assertStringContainsString( '$this->transactionManager->commit()', $source );
        $this->assertStringContainsString( 'WooCommerce order not found. No shipment data was changed.', $source );
        $this->assertStringContainsString( 'Failed to update the shipment origin. No shipment data was changed.', $source );
        $this->assertStringContainsString( '$kiriof_raw_new_total', $source );
        $this->assertStringContainsString( "'total_was_clamped'", $source );
        $this->assertStringContainsString( "'is_total_blocked'", $source );
        $this->assertStringContainsString( "'required_refund'", $source );
        $this->assertStringContainsString( 'This courier change requires buyer refund reconciliation before it can be processed.', $source );
        $this->assertStringContainsString( '$kiriof_previous_non_shipping_total', $source );
        $this->assertStringContainsString( '$kiriof_order->get_shipping_total()', $source );
        $this->assertStringContainsString( 'nonShippingTotal + newPaidShipping', $js );
        $this->assertStringContainsString( '$kiriof_validated_non_shipping_total + $kiriof_validated_paid_shipping', $source );
        $this->assertStringContainsString( '$kiriof_shipping_item->set_total( $kiriof_new_paid )', $source );
        $this->assertStringContainsString( '$kiriof_new_total   = (float) $kiriof_wc_order->get_total()', $source );
    }

    public function testUncoveredOriginUsesActionableErrorMessage(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );

        $this->assertStringContainsString( 'The selected origin is not covered by KiriminAja yet. Please choose another shipment origin.', $source );
        $this->assertStringContainsString( 'The selected origin is not covered for this destination. Please choose another shipment origin.', $source );
        $this->assertStringNotContainsString( 'Route is not serviceable from the selected origin:', $source );
        $this->assertStringNotContainsString( 'Selected shipment location has no serviceable area yet.', $source );
        $this->assertStringContainsString( 'if ( empty( $kiriof_options ) )', $source );
        $this->assertStringContainsString( 'if ( empty( $options ) )', $source );
    }

    public function testPackageDetailsShowBuyerShippingCouponWithoutMisreportingPriceChanges(): void {
		$transaction = $this->read( __DIR__ . '/../inc/Services/TransactionListViewModelFactory.php' );
		$app = $this->read( __DIR__ . '/../src/lib/transactions/TransactionsApp.svelte' );
        $preview     = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );
        $metabox     = $this->read( __DIR__ . '/../templates/order/metabox-shipping.php' );

		$this->assertStringContainsString( '$shipping_cost - (float) $wc_order->get_shipping_total()', $transaction );
        $this->assertStringContainsString( '$shipping_cost - $paid_shipping', $preview );
        $this->assertStringContainsString( '$kiriof_ship_coupon', $metabox );
        $this->assertStringContainsString( '(float) $wc_shipping_discount - $kiriof_platform_shipping_discount', $metabox );
        $this->assertStringNotContainsString( 'max($kiriof_discount_raw, $wc_discount_total)', $metabox );
		$this->assertStringContainsString( '$paid_shipping', $transaction );
		$this->assertStringContainsString( '$wc_order->get_shipping_total()', $transaction );
		$this->assertStringContainsString( 'currency(row.package.paidShipping)', $app );
		$this->assertStringContainsString( 'currency(row.package.actualShipping)', $app );
		$this->assertStringContainsString( "'actualShipping'   => \$shipping_cost", $transaction );
		$this->assertStringContainsString( '{bootstrap.i18n.actualShipping}:', $app );
        $this->assertStringContainsString( "__('Actual Shipping', 'kiriminaja-official')", $preview );
        $this->assertStringContainsString( "__('Shipping Discount', 'kiriminaja-official')", $preview );
        $this->assertStringContainsString( "__('Shipping', 'kiriminaja-official'), wc_price(\$paid_shipping", $preview );
        $this->assertStringContainsString( "__('Sub Total', 'kiriminaja-official')", $preview );
        $this->assertStringNotContainsString( "__('Discounted Shipping', 'kiriminaja-official')", $preview );
        $this->assertStringNotContainsString( "__('Total Shipping', 'kiriminaja-official')", $preview );
    }
}
