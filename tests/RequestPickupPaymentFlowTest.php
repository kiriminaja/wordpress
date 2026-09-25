<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestPickupPaymentFlowTest extends TestCase
{
    #[Test]
    public function scan_to_pay_uses_svelte_qr_instead_of_legacy_assets(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/payments/ScanToPayDialog.svelte' );
        $enqueue = file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' );

        $this->assertStringContainsString( "import { qr } from '@svelte-put/qr/svg'", $dialog );
        $this->assertStringContainsString( '<KiriofDialog', $dialog );
        $this->assertStringContainsString( 'use:qr={{ data: qrContent', $dialog );
        $this->assertStringContainsString( "postWordPressAction<PaymentData>('kiriof_get_payment_form'", $dialog );
        $this->assertStringNotContainsString( 'wc-qrcode', $enqueue );
        $this->assertStringNotContainsString( 'qr-code-styling', $enqueue );
        $this->assertFileDoesNotExist( PLUGIN_DIR . '/assets/lib/qr-code-styling/qr-code-styling.min.js' );
    }

    #[Test]
    public function request_pickup_list_auto_opens_payment_only_with_explicit_flag(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte');

        $this->assertStringContainsString(
            'new URLSearchParams(window.location.search)',
            $content,
            'Request pickup page should parse query params from location.search to avoid URLSearchParams(full href) parsing bugs'
        );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/payments/ScanToPayDialog.svelte' );
		$this->assertStringContainsString( '<ScanToPayDialog', $content );
		$this->assertStringNotContainsString( 'data-kiriof-payments-modals-root', file_get_contents( PLUGIN_DIR . '/templates/request-pickup/view/index.php' ) );

        $this->assertStringContainsString(
            "open !== '1' && open !== 'true'",
            $content,
            'Request pickup page should only auto-open payment modal when open_payment explicitly opts in'
        );

        $this->assertStringContainsString(
            "row.actions.some((action) => action.type === 'pay')",
            $content,
            'Request pickup page should auto-open payment only through an available payment action'
        );

        $this->assertStringNotContainsString(
            'showPaymentForm(pickupNumberToLoad);',
            $content,
            'Request pickup page should not fall back to Scan to Pay when the payment action is absent'
        );

        $this->assertStringNotContainsString(
            'new URLSearchParams(window.location.href)',
            $content,
            'URLSearchParams should not be created from full href string because pickup_number can fail to resolve'
        );
    }

	#[Test]
	public function pickup_dialog_uses_radio_cards_only_when_multiple_payment_methods_exist(): void
	{
		$dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/RequestPickupDialog.svelte' );
		$styles = file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' );

		$this->assertStringContainsString( '<RadioGroup.Root bind:value={paymentMethod}', $dialog );
		$this->assertStringContainsString( '{#if paymentRequired && paymentOptions.length >= 1}', $dialog );
		$this->assertStringContainsString( "paymentMethod = creditEnabled && creditAvailable ? 'credit'", $dialog );
		$this->assertStringContainsString( 'creditEnabled', $dialog );
		$this->assertStringContainsString( 'has_pin', $dialog );
		$this->assertStringContainsString( 'Remaining Credit', $dialog );
		$this->assertStringContainsString( 'Maximum Transaction Rp10.000.000', $dialog );
		$this->assertStringContainsString( 'Continue to Payment', $dialog );
		$this->assertStringContainsString( 'disabled={option.disabled}', $dialog );
		$this->assertStringContainsString( 'kiriof-payment-method-card', $dialog );
		$this->assertStringContainsString( '!border border-border', $styles );
		$this->assertStringContainsString( '![font-size:17px]', $styles );
	}

    #[Test]
    public function request_pickup_detail_page_does_not_auto_open_payment_modal(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/request-pickup-detail/view/index.php');

        $this->assertStringNotContainsString(
            "request-pickup/view/modal-payment.php",
            $content,
            'Detail page should not include the Scan to Pay modal; payments should be opened from the request pickup list'
        );

        $this->assertStringNotContainsString(
            'action: "kiriof_get_payment_form"',
            $content,
            'Detail page should not request payment form data or render the payment QR modal'
        );

        $this->assertStringNotContainsString(
            'kiriofDetailUrlParams',
            $content,
            'Detail page should not read pickup_number to auto-open payment modal'
        );

        $this->assertStringNotContainsString(
            'showPaymentForm(pickupNumberToLoad);',
            $content,
            'Detail page should not auto-open payment modal after redirect'
        );

		$this->assertStringContainsString( 'data-kiriof-pickup-detail-root', $content );
		$this->assertStringContainsString( 'kiriof_pickup_detail_bootstrap', $content );
		$this->assertStringContainsString( 'kiriof-workspace-shell', $content );
		$this->assertStringNotContainsString( 'data-kiriof-pickup-detail-fallback', $content );
		$this->assertStringNotContainsString( 'wp-list-table', $content );
		$this->assertFileDoesNotExist( PLUGIN_DIR . '/src/entries/pickup-detail.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/pickup-detail/PickupDetail.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/inc/Services/PickupDetailPageData.php' );
		$this->assertStringContainsString( 'wp_safe_redirect', file_get_contents( PLUGIN_DIR . '/templates/request-pickup-detail/index.php' ) );
		$this->assertStringNotContainsString( 'PickupDetailPageData', file_get_contents( PLUGIN_DIR . '/templates/request-pickup-detail/index.php' ) );
		$this->assertStringNotContainsString( "route: 'pickup-detail'", file_get_contents( PLUGIN_DIR . '/src/entries/admin-workspace.ts' ) );
		$this->assertStringNotContainsString( 'kiriminaja-request-pickup-detail', file_get_contents( PLUGIN_DIR . '/src/entries/admin-workspace.ts' ) );
		$this->assertStringNotContainsString( "'pickup-detail': 'src/entries/pickup-detail.ts'", file_get_contents( PLUGIN_DIR . '/vite.config.ts' ) );
		$this->assertStringNotContainsString( 'kiriminaja-pickup-detail.js', file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' ) );
		$this->assertStringContainsString( '<Toolbar toolbar={bootstrap.toolbar}', file_get_contents( PLUGIN_DIR . '/src/lib/pickup-detail/PickupDetail.svelte' ) );
		$this->assertStringContainsString( 'kiriof-admin-list-table', file_get_contents( PLUGIN_DIR . '/src/lib/pickup-detail/PickupDetail.svelte' ) );
		$this->assertStringContainsString( '<Card.Root size="sm" class="kiriof-pickup-summary-card">', file_get_contents( PLUGIN_DIR . '/src/lib/pickup-detail/PickupDetail.svelte' ) );
		$this->assertStringContainsString( 'kiriof-pickup-detail-print-all__icon', file_get_contents( PLUGIN_DIR . '/src/lib/pickup-detail/PickupDetail.svelte' ) );
		$this->assertStringContainsString( '.kiriof-pickup-summary-card', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
    }

    #[Test]
    public function pick_schedule_redirect_adds_open_payment_only_when_backend_opt_in_exists(): void
    {
        $transactionProcessContent = file_get_contents(PLUGIN_DIR . '/assets/admin/js/kj-transaction-process.js');
		$this->assertStringContainsString( 'open_payment', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );

        $this->assertStringContainsString(
            'const shouldOpenPayment',
            $transactionProcessContent,
            'Transaction process flow should only append open_payment when backend marks payment modal as required'
        );

        $this->assertStringContainsString(
            'resp?.data?.open_payment === true',
            $transactionProcessContent,
            'Transaction process flow should read the backend open_payment flag'
        );

        $this->assertStringContainsString(
            'window.location.href = shouldOpenPayment',
            $transactionProcessContent,
            'Transaction process flow should avoid opening Scan to Pay automatically for COD-only pickups'
        );
    }

    #[Test]
    public function top_payment_rows_do_not_render_scan_to_pay_actions(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/PaymentListRenderService.php');

        $this->assertStringContainsString(
            "if ( 'paid' !== \$status && 'top' !== \$method )",
            $content,
            'Request pickup list should classify TOP rows before deciding payment actions'
        );

        $this->assertStringContainsString(
            "if ( 'paid' !== \$status && 'top' !== \$method )",
            $content,
            'TOP rows should not enter the unpaid action branch that renders Scan to Pay'
        );
    }

    #[Test]
    public function qris_payment_stays_waiting_until_remote_payment_is_paid(): void
    {
        $callbackContent = file_get_contents(PLUGIN_DIR . '/inc/Services/CallbackHandlerService.php');
        $requestPickupContent = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php');
        $paymentRefreshContent = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingProcessServices/GetShippingProcessPayment.php');
        $requestPickupTemplate = file_get_contents(PLUGIN_DIR . '/src/lib/payments/ScanToPayDialog.svelte');

        $this->assertStringContainsString(
            "if ( \$paymentMethod !== 'qris' || \$paymentStatus === 'paid' )",
            $callbackContent,
            'processed_packages webhook must not mark unpaid QRIS payment paid just because AWB exists'
        );

        $this->assertStringContainsString(
            "\$localPaymentStatus = 'unpaid';",
            $requestPickupContent,
            'Non-TOP QRIS pickups should start as waiting for payment'
        );

        $this->assertStringContainsString(
            "if (\$normalizedPaymentMethod !== 'qris' && \$apiPaymentStatus === 'paid')",
            $requestPickupContent,
            'Request pickup must not mark QRIS paid from pickup response status alone'
        );

        $this->assertStringContainsString(
            "\$remoteIsPaid = \$localMethod === 'qris'",
            $paymentRefreshContent,
            'Payment refresh should branch QRIS paid detection away from non-QRIS auto-paid rules'
        );

        $this->assertStringContainsString(
            "\$remotePaidAt = (string) (\$remotePayment->paid_at ?? '');",
            $paymentRefreshContent,
            'Payment refresh should also treat paid_at as a successful payment signal'
        );

        $this->assertStringContainsString(
            "\$remoteHasPaidTimestamp = \$remotePaidAt !== '';",
            $paymentRefreshContent,
            'QRIS must not treat pay_time as paid because pay_time exists when the payment QR is generated'
        );

        $this->assertStringContainsString(
            "? (\$remoteHasPaidTimestamp || \$remoteHasPaidStatus)",
            $paymentRefreshContent,
            'QRIS should only be marked paid when KiriminAja returns a paid timestamp or paid status label'
        );

        $this->assertStringContainsString(
            ": (\$remoteStatusCode === '0' || \$remotePayTime !== '' || \$remoteHasPaidTimestamp || \$remoteHasPaidStatus || \$hasAwbForPickup);",
            $paymentRefreshContent,
            'Non-QRIS auto-paid flows can still use status_code 0 or AWB'
        );

        $this->assertStringContainsString(
            '$localMethod === \'qris\' && !$remoteIsPaid',
            $paymentRefreshContent,
            'Payment refresh should keep unpaid QRIS rows waiting when remote payment is not paid'
        );

        $this->assertStringContainsString(
            '$localPaymentStatus !== \'paid\' && $hasNonCodPackage && $normalizedPaymentMethod === \'qris\'',
            $requestPickupContent,
            'Non-TOP pickups with any non-COD package should open Scan to Pay while payment is waiting'
        );

        $this->assertStringContainsString(
            "String(remote.status_code || '').trim()",
            $requestPickupTemplate,
            'Payment modal should use KiriminAja payment status_code mapping instead of HTTP-like status codes'
        );

        $this->assertStringContainsString(
            "String(remote.status_code || '').trim() === '0'",
            $requestPickupTemplate,
            'Payment modal should only use status_code 0 for non-QRIS paid flows'
        );

        $this->assertStringContainsString(
            "String(local.method || '').toLowerCase() === 'qris'",
            $requestPickupTemplate,
            'Payment modal should not close QRIS just because status_code is 0 without paid timestamp/status label'
        );

        $this->assertStringContainsString(
            '!!remote.paid_at',
            $requestPickupTemplate,
            'Payment modal should not close QRIS just because pay_time exists on a newly generated QR'
        );

        $this->assertStringContainsString(
            "String(local.status || '').toLowerCase() === 'paid'",
            $requestPickupTemplate,
            'Refresh button should reload after QRIS has actually been marked paid'
        );
    }

    #[Test]
    public function cod_only_pickup_does_not_fall_back_to_qris(): void
    {
        $requestPickupContent = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php');
        $requestPickupTemplate = file_get_contents(PLUGIN_DIR . '/inc/Services/PaymentListRenderService.php');

        $this->assertStringContainsString(
            "if (isset(\$package['is_cod']))",
            $requestPickupContent,
            'Local COD marker should be removed before sending packages to KiriminAja API'
        );

        $this->assertStringContainsString(
            '$paymentMethod = $hasNonCodPackage ? \'qris\' : \'cod\';',
            $requestPickupContent,
            'COD-only pickups should not default their local payment method to QRIS'
        );

        $this->assertStringContainsString(
            '$normalizedPaymentMethod === \'cod\'',
            $requestPickupContent,
            'COD-only local payment rows should be marked paid and avoid QRIS actions'
        );

        $this->assertStringContainsString(
            "\$isCodOrder = 'cod' === strtolower((string) \$order->get_payment_method());",
            $requestPickupContent,
            'Pickup package COD amount should follow the WooCommerce order payment method, not only cod_fee > 0'
        );

        $this->assertStringContainsString(
            "'method'       => '' !== \$method ? strtoupper( \$method ) : 'QRIS'",
            $requestPickupTemplate,
            'Request pickup list should not display COD-only payment rows as QRIS'
        );
    }

    #[Test]
    public function request_pickup_credit_pin_supports_temporary_encrypted_browser_cache(): void
    {
        $transactionProcessContent = file_get_contents(PLUGIN_DIR . '/assets/admin/js/kj-transaction-process.js');
        $controllerContent = file_get_contents(PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php');

        $this->assertStringContainsString(
            "const kjPinCacheConfig = {",
            $transactionProcessContent,
            'Transaction process should expose browser PIN cache config for the request pickup flow'
        );

        $this->assertStringContainsString(
            'window.crypto.subtle.encrypt',
            $transactionProcessContent,
            'Temporary PIN storage should encrypt the PIN before writing to browser storage'
        );

        $this->assertStringContainsString(
            'window.localStorage.setItem(kjPinCacheConfig.key, JSON.stringify(payload));',
            $transactionProcessContent,
            'Temporary PIN cache should persist encrypted browser state per user key'
        );

        $this->assertStringContainsString(
            'kjClearCachedPin($modal, "invalid");',
            $transactionProcessContent,
            'Invalid or outdated PIN responses should clear the saved browser PIN cache'
        );

        $this->assertStringContainsString(
            'id="kiriof-pin-remember"',
            $controllerContent,
            'Credit PIN modal should render a remember PIN checkbox for temporary browser cache opt-in'
        );

        $this->assertStringContainsString(
            "'Remember PIN on this browser for %d minutes'",
            $controllerContent,
            'Credit PIN modal renderer must create its own checkbox label instead of relying on template scope'
        );

        $this->assertStringContainsString(
            'esc_html($kiriof_pin_cache_label)',
            $controllerContent,
            'Credit PIN modal should render the generated remember PIN label beside the checkbox'
        );

        $adminCss = file_get_contents(PLUGIN_DIR . '/assets/admin/css/kj-admin-style.css');
        $this->assertStringContainsString(
            '.wc-backbone-modal.kiriof-backbone-modal .kiriof-pin-remember',
            $adminCss,
            'Credit PIN checkbox label should have explicit readable modal styling'
        );
    }

    #[Test]
    public function payment_list_fees_column_subtracts_platform_shipping_discount(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Queries/WordPressPaymentListQuery.php');

        $this->assertStringContainsString(
            'kiriminaja_transactions.shipping_cost - COALESCE(kiriminaja_transactions.discount_amount, 0) + kiriminaja_transactions.insurance_cost',
            $content,
            'Payments list Fees column must subtract platform shipping discount (discount_amount), not show raw shipping + insurance'
        );

        $this->assertStringNotContainsString(
            'kiriminaja_transactions.shipping_cost + kiriminaja_transactions.insurance_cost ELSE 0 END) AS cost',
            $content,
            'Payments list Fees column must not ignore discount_amount when computing non-COD cost'
        );
    }

    #[Test]
    public function request_pickup_preserves_checkout_postcode_for_destination_zipcode(): void
    {
        $checkoutController = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $createTransactionService = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CreateTransactionService.php');
        $requestPickupService = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php');

        $this->assertStringContainsString(
            "\$order->update_meta_data( '_kiriof_checkout_postcode', \$kiriof_checkout_postcode );",
            $checkoutController,
            'Checkout must persist the buyer postcode on the order before transaction creation'
        );

        $this->assertStringContainsString(
            "\$order->set_billing_postcode( \$kiriof_checkout_postcode );",
            $checkoutController,
            'Checkout must mirror the resolved postcode into WooCommerce billing postcode'
        );

        $this->assertStringContainsString(
            "\$order->set_shipping_postcode( \$kiriof_checkout_postcode );",
            $checkoutController,
            'Checkout must mirror the resolved postcode into WooCommerce shipping postcode'
        );

        $this->assertStringContainsString(
            'private function kiriof_extract_postcode_from_destination_name( $destination_name ): string',
            $checkoutController,
            'Classic checkout must extract postcode from KiriminAja district labels when no postcode field exists'
        );

        $this->assertStringContainsString(
            "\$kiriof_checkout_postcode = \$this->kiriof_extract_postcode_from_destination_name( \$destinasi_name );",
            $checkoutController,
            'Checkout postcode must fall back to the trailing postal code in the selected district name'
        );

        $this->assertStringContainsString(
            "'destination_zipcode'       => \$order ? (string) \$order->get_meta( '_kiriof_checkout_postcode', true ) : '',",
            $checkoutController,
            'Checkout must pass the persisted postcode into transaction creation'
        );

        $this->assertStringContainsString(
            "\$requiredPostMeta['data']['_kiriof_checkout_postcode'] = sanitize_text_field( (string) \$this->payload['destination_zipcode'] );",
            $createTransactionService,
            'Transaction shipping_info must keep checkout postcode for later request pickup payloads'
        );

        $this->assertStringContainsString(
            'RecipientDataResolver',
            $requestPickupService,
            'Request pickup must resolve recipient data from the current WooCommerce order before using the transaction snapshot'
        );

        $this->assertStringContainsString(
            'get_order_postcode_meta',
            file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionProcessServices/RecipientDataResolver.php'),
            'Recipient resolver must retain WooCommerce order-meta postcode fallbacks for legacy orders'
        );

        $this->assertStringContainsString(
            '"destination_zipcode"       => $destinationData[\'zipcode\']',
            $requestPickupService,
            'Request pickup package payload must send destination_zipcode from resolved destination data'
        );
    }

    #[Test]
    public function request_pickup_sends_the_resolved_destination_phone_to_the_api(): void
    {
        $requestPickupService = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php');
        $apiRepository = file_get_contents(PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php');

        $this->assertStringContainsString(
            '"destination_phone"         => $destinationData[\'phone\']',
            $requestPickupService,
            'Each pickup package must include the phone resolved from the current WooCommerce recipient data'
        );

        $this->assertStringNotContainsString(
            "unset(\$package['destination_phone'])",
            $requestPickupService,
            'Destination phone must not be removed while preparing the request-pickup API payload'
        );

        $this->assertStringContainsString(
            'sendPickupRequest($payload)',
            $requestPickupService,
            'Pickup service must send the prepared package payload through request-pickup API v6.1'
        );

        $this->assertStringNotContainsString(
            'sendPickupRequestWithFeatureFlag',
            $apiRepository,
            'Request-pickup API repository must not conditionally switch API versions'
        );
    }

    #[Test]
    public function platform_shipping_discount_label_distinguishes_from_user_coupon(): void
    {
        $checkoutController = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $transactionProcessController = file_get_contents(PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php');
        $metabox = file_get_contents(PLUGIN_DIR . '/templates/order/metabox-shipping.php');

        $this->assertStringContainsString(
            "Shipping Discount (from KiriminAja)",
            $checkoutController,
            'Order received page should label platform-covered shipping discount as "Shipping Discount (from KiriminAja)"'
        );

        $this->assertStringContainsString(
            '$is_platform_discount',
            $checkoutController,
            'Order received page should distinguish platform discount from user coupon discount before choosing label'
        );

        $this->assertStringContainsString(
            "Shipping Discount (from KiriminAja)",
            $transactionProcessController,
            'Admin order preview should label platform-covered shipping discount as "Shipping Discount (from KiriminAja)"'
        );

        $this->assertStringContainsString(
            "Shipping Discount (from KiriminAja)",
            $metabox,
            'Admin order metabox should label platform-covered shipping discount as "Shipping Discount (from KiriminAja)"'
        );
		$this->assertStringContainsString( 'data-kiriof-order-metabox-root', $metabox );
		$this->assertFileExists( PLUGIN_DIR . '/src/entries/order-metabox.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/order-metabox/OrderMetabox.svelte' );
		$this->assertStringContainsString( "'order-metabox': 'src/entries/order-metabox.ts'", file_get_contents( PLUGIN_DIR . '/vite.config.ts' ) );
    }

    #[Test]
    public function deficit_rows_with_non_negative_effective_cod_payout_remain_pickup_processable(): void
    {
		$transactionProcessView = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionListViewModelFactory.php');
		$app = file_get_contents(PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte');

        $this->assertStringContainsString(
			'$effective_payout',
            $transactionProcessView,
            'Request pickup eligibility must use discounted shipping when evaluating COD payout'
        );

        $this->assertStringContainsString(
			'$wc_total - max( 0.0, $shipping_cost - $shipping_discount ) - $insurance_cost - $cod_fee',
            $transactionProcessView,
            'Request pickup eligibility must evaluate the effective COD payout'
        );

        $this->assertStringContainsString(
			'$can_request_pickup    = $is_processable && ( ! $is_deficit || $effective_payout >= 0 );',
            $transactionProcessView,
            'Deficit rows should remain pickup-processable when the effective COD payout is non-negative'
        );

        $this->assertStringContainsString(
			"data-can-pickup={row.selection.canPickup ? '1' : '0'}",
			$app,
            'Request pickup checkbox must use effective processability instead of the raw deficit flag'
        );
    }
}

final class RequestPickupScheduleInputStyleTest extends TestCase
{
    #[Test]
    public function pickup_schedule_selects_use_the_shadcn_background_and_input_border(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/RequestPickupDialog.svelte' );

        $this->assertStringContainsString( 'id="kiriof-pickup-date" class="!bg-background !border-border"', $dialog );
        $this->assertStringContainsString( 'id="kiriof-pickup-time" class="!bg-background !border-border"', $dialog );
    }
}
