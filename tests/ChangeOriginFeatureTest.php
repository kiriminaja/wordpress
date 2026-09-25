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
		$dialog = $this->read( __DIR__ . '/../src/lib/transactions/TransactionActionDialogs.svelte' );

		$this->assertStringContainsString( 'TransactionActionDialogs', $template );
		$this->assertStringContainsString( "actionDialog = { kind: 'origin', data: row.actionData }", $template );
		$this->assertStringContainsString( "'currentLocationId'", $factory );
		$this->assertStringContainsString( "'currentOrigin'", $factory );
		$this->assertStringContainsString( '{#if row.actions.changeOrigin}', $template );
		$this->assertStringContainsString( '<IconMapPin />', $template );
		$this->assertStringContainsString( "action: 'kiriof_change_origin_check'", $dialog );
		$this->assertStringContainsString( "action: 'kiriof_change_origin'", $dialog );
    }

    public function testChangeOriginUsesSvelteDialogWithoutLegacyModalAssets(): void {
        $source  = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );
        $dialog  = $this->read( __DIR__ . '/../src/lib/transactions/TransactionActionDialogs.svelte' );
        $list    = $this->read( __DIR__ . '/../src/lib/transactions/TransactionsApp.svelte' );
        $detail  = $this->read( __DIR__ . '/../src/lib/transaction-detail/TransactionDetail.svelte' );
        $enqueue = $this->read( __DIR__ . '/../inc/Base/Enqueue.php' );
        $css     = $this->read( __DIR__ . '/../assets/admin/css/kj-admin-style.css' );

        $this->assertStringContainsString( "action: 'kiriof_change_origin_check'", $dialog );
        $this->assertStringContainsString( "action: 'kiriof_change_origin'", $dialog );
        $this->assertStringContainsString( '<ShipmentLocationCombobox', $dialog );
        $this->assertStringContainsString( '<CourierOptionCombobox', $dialog );
        $this->assertStringContainsString( 'replacementConsent', $dialog );
        $this->assertStringContainsString( "actionDialog = { kind: 'origin', data: row.actionData }", $list );
        $this->assertStringContainsString( 'kind: "origin"', $detail );
        $this->assertStringNotContainsString( 'tmpl-kiriof-modal-change-origin', $source );
        $this->assertStringNotContainsString( 'kiriof-change-origin-modal', $css );
        $this->assertStringNotContainsString( 'assets/js/kiriof-change-origin.js', $enqueue );
        $this->assertFileDoesNotExist( __DIR__ . '/../assets/js/kiriof-change-origin.js' );
        $this->assertFileDoesNotExist( __DIR__ . '/../build/kiriminaja-official/assets/js/kiriof-change-origin.js' );
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
        $dialog = $this->read( __DIR__ . '/../src/lib/transactions/TransactionActionDialogs.svelte' );

        foreach ( array( 'previous_courier', 'new_courier', 'previous_paid_shipping', 'new_paid_shipping', 'previous_discount', 'new_discount', 'previous_total', 'new_total', 'total_delta' ) as $field ) {
            $this->assertStringContainsString( "'{$field}'", $controller );
        }

        $this->assertStringContainsString( 'originCheck.comparison.previous_paid_shipping', $dialog );
        $this->assertStringContainsString( 'originCheck.comparison.new_paid_shipping', $dialog );
        $this->assertStringContainsString( 'originCheck.comparison.is_total_blocked', $dialog );
        $this->assertStringContainsString( 'requiresCourierConsent', $dialog );
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
        $dialog = $this->read( __DIR__ . '/../src/lib/transactions/TransactionActionDialogs.svelte' );

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
        $this->assertStringContainsString( 'originCheck.comparison.is_total_blocked', $dialog );
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
		$this->assertStringContainsString( "'codValue'         => \$cod_value", $transaction );
		$this->assertStringContainsString( '{bootstrap.i18n.actualShipping}:', $app );
        $this->assertStringContainsString( "__('Actual Shipping', 'kiriminaja-official')", $preview );
        $this->assertStringContainsString( "__('Shipping Discount', 'kiriminaja-official')", $preview );
        $this->assertStringContainsString( "__('Shipping', 'kiriminaja-official'), wc_price(\$paid_shipping", $preview );
        $this->assertStringContainsString( "__('Sub Total', 'kiriminaja-official')", $preview );
        $this->assertStringNotContainsString( "__('Discounted Shipping', 'kiriminaja-official')", $preview );
        $this->assertStringNotContainsString( "__('Total Shipping', 'kiriminaja-official')", $preview );
    }
}
