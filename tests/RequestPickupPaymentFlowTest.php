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
    public function awb_assignment_marks_pickup_payment_paid(): void
    {
        $callbackContent = file_get_contents(PLUGIN_DIR . '/inc/Services/CallbackHandlerService.php');
        $requestPickupContent = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php');
        $paymentRefreshContent = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingProcessServices/GetShippingProcessPayment.php');

        $this->assertStringContainsString(
            "'status'=>'paid'",
            $callbackContent,
            'processed_packages webhook should mark payment paid once AWB is stored'
        );

        $this->assertStringNotContainsString(
            'if ($paymentMethod === \'qris\')',
            $callbackContent,
            'QRIS payment payload should not override the AWB-implies-paid contract'
        );

        $this->assertStringContainsString(
            '$hasAwbAfterPickup = $this->hasAwbInTransactions($pickupTransactions);',
            $requestPickupContent,
            'Request pickup creation should detect AWB that arrived before the payment row was created'
        );

        $this->assertStringContainsString(
            '$localPaymentStatus !== \'paid\' && $hasNonCodPackage && $normalizedPaymentMethod === \'qris\'',
            $requestPickupContent,
            'Request pickup response should only open QRIS when local payment is still unpaid'
        );

        $this->assertStringContainsString(
            '$remoteIsPaid = $remoteStatusCode >= 100 || $remotePayTime !== \'\' || $hasAwbForPickup;',
            $paymentRefreshContent,
            'Payment refresh should keep AWB-backed pickups paid even if QRIS status metadata lags'
        );

        $this->assertStringContainsString(
            '$localMethod === \'qris\' && !$remoteIsPaid && ! $hasAwbForPickup',
            $paymentRefreshContent,
            'Payment refresh should not downgrade QRIS rows that already have AWB'
        );
    }
}
