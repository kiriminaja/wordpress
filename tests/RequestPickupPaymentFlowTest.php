<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestPickupPaymentFlowTest extends TestCase
{
    #[Test]
    public function request_pickup_list_uses_search_params_from_url_object(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/request-pickup/view/index.php');

        $this->assertStringContainsString(
            'new URL(window.location.href).searchParams',
            $content,
            'Request pickup page should parse pickup_number from URL.searchParams to avoid URLSearchParams(full href) parsing bugs'
        );

        $this->assertStringNotContainsString(
            'new URLSearchParams(window.location.href)',
            $content,
            'URLSearchParams should not be created from full href string because pickup_number can fail to resolve'
        );
    }

    #[Test]
    public function request_pickup_detail_page_includes_payment_modal_and_auto_open_logic(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/request-pickup-detail/view/index.php');

        $this->assertStringContainsString(
            "request-pickup/view/modal-payment.php",
            $content,
            'Detail page must include the Scan to Pay modal so redirect target can continue the payment flow'
        );

        $this->assertStringContainsString(
            'action: "kiriof_get_payment_form"',
            $content,
            'Detail page should request payment form data to render QR Scan to Pay modal'
        );

        $this->assertStringContainsString(
            'const kiriofDetailUrlParams = new URL(window.location.href).searchParams;',
            $content,
            'Detail page should read pickup_number from URL search params'
        );

        $this->assertStringContainsString(
            'showPaymentForm(pickupNumberToLoad);',
            $content,
            'Detail page must auto-open payment modal after redirect using pickup_number'
        );
    }
}

