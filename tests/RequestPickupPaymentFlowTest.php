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
            'showPaymentForm(pickupNumberToLoad);',
            $content,
            'Request pickup page should auto-open payment modal from pickup_number when open_payment opt-in exists'
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
}
