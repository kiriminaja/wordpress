<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestPickupPaymentFlowTest extends TestCase
{
    #[Test]
    public function request_pickup_list_auto_opens_payment_only_with_explicit_flag(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/request-pickup/view/index.php');

        $this->assertStringContainsString(
            'new URLSearchParams(window.location.search)',
            $content,
            'Request pickup page should parse query params from location.search to avoid URLSearchParams(full href) parsing bugs'
        );

        $this->assertStringContainsString(
            "shouldOpenPayment === '1' || shouldOpenPayment === 'true'",
            $content,
            'Request pickup page should only auto-open payment modal when open_payment explicitly opts in'
        );

        $this->assertStringContainsString(
            'paymentButton.click();',
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
    }

    #[Test]
    public function pick_schedule_redirect_adds_open_payment_only_when_backend_opt_in_exists(): void
    {
        $requestPickupContent = file_get_contents(PLUGIN_DIR . '/templates/request-pickup/view/index.php');
        $transactionProcessContent = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/view/index.php');

        $this->assertStringContainsString(
            "const shouldOpenPayment = resp?.data?.open_payment === true || resp?.data?.open_payment === 1 || resp?.data?.open_payment === '1';",
            $requestPickupContent,
            'Request pickup flow should only append open_payment when backend marks payment modal as required'
        );

        $this->assertStringContainsString(
            'window.location.href = shouldOpenPayment ? `${redirectBase}&open_payment=1` : redirectBase;',
            $requestPickupContent,
            'Request pickup flow should avoid opening Scan to Pay automatically for COD-only pickups'
        );

        $this->assertStringContainsString(
            "const shouldOpenPayment = resp?.data?.open_payment === true || resp?.data?.open_payment === 1 || resp?.data?.open_payment === '1';",
            $transactionProcessContent,
            'Transaction process flow should only append open_payment when backend marks payment modal as required'
        );

        $this->assertStringContainsString(
            'window.location.href = shouldOpenPayment ? `${redirectBase}&open_payment=1` : redirectBase;',
            $transactionProcessContent,
            'Transaction process flow should avoid opening Scan to Pay automatically for COD-only pickups'
        );
    }

    #[Test]
    public function top_payment_rows_do_not_render_scan_to_pay_actions(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/request-pickup/view/index.php');

        $this->assertStringContainsString(
            "\$kiriof_is_top_method = 'top' === \$kiriof_method;",
            $content,
            'Request pickup list should classify TOP rows before deciding payment actions'
        );

        $this->assertStringContainsString(
            '@$kiriof_row->status!=="paid" && ! $kiriof_is_top_method',
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
        $requestPickupTemplate = file_get_contents(PLUGIN_DIR . '/templates/request-pickup/view/index.php');

        $this->assertStringContainsString(
            "if (\$paymentMethod !== 'qris' || \$paymentStatus === 'paid')",
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
            "const remoteStatusCode = String(remotePayment?.status_code ?? '').trim();",
            $requestPickupTemplate,
            'Payment modal should use KiriminAja payment status_code mapping instead of HTTP-like status codes'
        );

        $this->assertStringContainsString(
            "remoteStatusCode === '0'",
            $requestPickupTemplate,
            'Payment modal should only use status_code 0 for non-QRIS paid flows'
        );

        $this->assertStringContainsString(
            "localMethod === 'qris'",
            $requestPickupTemplate,
            'Payment modal should not close QRIS just because status_code is 0 without paid timestamp/status label'
        );

        $this->assertStringContainsString(
            'const remoteHasPaidTimestamp = !!remotePayment?.paid_at;',
            $requestPickupTemplate,
            'Payment modal should not close QRIS just because pay_time exists on a newly generated QR'
        );

        $this->assertStringContainsString(
            "const localIsPaid = String(localPayment?.status || '').toLowerCase() === 'paid';",
            $requestPickupTemplate,
            'Refresh button should reload after QRIS has actually been marked paid'
        );
    }

    #[Test]
    public function cod_only_pickup_does_not_fall_back_to_qris(): void
    {
        $requestPickupContent = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php');
        $requestPickupTemplate = file_get_contents(PLUGIN_DIR . '/templates/request-pickup/view/index.php');

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
            "elseif (\$kiriof_method === 'cod')",
            $requestPickupTemplate,
            'Request pickup list should not display COD-only payment rows as QRIS'
        );
    }

    #[Test]
    public function payment_list_fees_column_subtracts_platform_shipping_discount(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/request-pickup/index.php');

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
            'private function readOrderMetaValue($order, array $keys): string',
            $requestPickupService,
            'Request pickup destination zipcode must support WooCommerce order meta fallbacks'
        );

        $this->assertStringContainsString(
            "\$shippingPostcode = \$this->readOrderMetaValue(\$order, ['_shipping_postcode', 'shipping_postcode', '_billing_postcode', 'billing_postcode', '_kiriof_checkout_postcode', 'kiriof_checkout_postcode']);",
            $requestPickupService,
            'Request pickup destination zipcode must fall back to WooCommerce shipping and billing postcode meta'
        );

        $this->assertStringContainsString(
            "\$shippingPostcode = \$this->extractPostcodeFromDestinationText(\$transaction->destination_sub_district ?? '');",
            $requestPickupService,
            'Existing request pickup transactions must recover destination zipcode from trailing postal code in district text'
        );

        $this->assertStringContainsString(
            '"destination_zipcode"       => $destinationData[\'zipcode\']',
            $requestPickupService,
            'Request pickup package payload must send destination_zipcode from resolved destination data'
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
    }

    #[Test]
    public function deficit_rows_with_non_negative_effective_cod_payout_remain_pickup_processable(): void
    {
        $transactionProcessView = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/view/index.php');

        $this->assertStringContainsString(
            '$kiriof_effectiveShippingCost = max(0.0, $kiriof_shippingCost - $kiriof_wcShippingDiscount);',
            $transactionProcessView,
            'Request pickup eligibility must use discounted shipping when evaluating COD payout'
        );

        $this->assertStringContainsString(
            '$kiriof_effectiveCodPayout    = $kiriof_wcTotal - $kiriof_effectiveShippingCost - $kiriof_insuranceCost - $kiriof_codFee;',
            $transactionProcessView,
            'Request pickup eligibility must evaluate the effective COD payout'
        );

        $this->assertStringContainsString(
            '$kiriof_canRequestPickup      = $kiriof_isProcessable && (! $kiriof_isDeficitRow || $kiriof_effectiveCodPayout >= 0);',
            $transactionProcessView,
            'Deficit rows should remain pickup-processable when the effective COD payout is non-negative'
        );

        $this->assertStringContainsString(
            'data-can-pickup="\' . ($kiriof_canRequestPickup ? \'1\' : \'0\')',
            $transactionProcessView,
            'Request pickup checkbox must use effective processability instead of the raw deficit flag'
        );
    }
}
