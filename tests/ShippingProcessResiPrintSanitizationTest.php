<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShippingProcessResiPrintSanitizationTest extends TestCase
{
    #[Test]
    public function resi_print_normalizes_oids_without_forcing_array_through_sanitize_text_field(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingProcessController.php');
        $start = strpos($content, 'function resiPrint');
        $this->assertNotFalse($start, 'resiPrint() method must exist');
        $methodBody = substr($content, $start, 2200);

        $this->assertStringContainsString(
            'wp_unslash( $_REQUEST[\'oids\'] )',
            $methodBody,
            'resiPrint() should accept oids as raw string/array before normalization'
        );

        $this->assertStringContainsString(
            '$this->sanitizeResiPrintOrderIds( $rawOrderIds )',
            $methodBody,
            'resiPrint() must normalize order IDs via shared sanitizer to support both CSV and array forms'
        );

        $this->assertStringNotContainsString(
            'sanitize_text_field( wp_unslash( $_REQUEST[\'oids\'] ) )',
            $methodBody,
            'Forcing sanitize_text_field on array-form oids turns it into an empty scalar and breaks IDs like YGG-6315937014'
        );
    }

    #[Test]
    public function resi_print_redirects_to_api_label_url_after_marking_transactions_printed(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingProcessController.php');
        $start = strpos($content, 'private function outputResiPrint');
        $this->assertNotFalse($start, 'outputResiPrint() method must exist');
        $methodBody = substr($content, $start, 3600);

        $this->assertStringContainsString(
            '$this->markTransactionsPrinted( $printedOrderIds );',
            $methodBody,
            'Successful label resolution should mark selected transactions as printed'
        );

        $this->assertStringContainsString(
            '$printAwbUrl = $this->resolvePrintAwbUrl( $getAwbData );',
            $methodBody,
            'Print should resolve the label URL through the shared API response helper'
        );

        $this->assertStringContainsString(
            'wp_redirect( esc_url_raw( $printAwbUrl ) )',
            $methodBody,
            'Print should send the browser to the resolved label URL instead of failing on a server-side PDF fetch'
        );

        $this->assertStringNotContainsString(
            'wp_remote_get( $pdfUrl',
            $methodBody,
            'Print should not redirect users to /404 just because the WordPress server cannot fetch the PDF URL'
        );
    }

    #[Test]
    public function resi_print_logs_failure_reasons_and_accepts_multiple_api_url_shapes(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingProcessController.php');

        $this->assertStringContainsString(
            'private function logResiPrintFailure',
            $content,
            'Print failures should be logged with a concrete reason before redirecting to /404'
        );

        $this->assertStringContainsString(
            'print_awb_url_missing',
            $content,
            'Missing print URL responses should be distinguishable in WooCommerce logs'
        );

        $this->assertStringContainsString(
            '$data->data->url ?? null',
            $content,
            'Print URL resolver should support nested API response URLs'
        );

        $this->assertStringContainsString(
            '$data->url ?? null',
            $content,
            'Print URL resolver should support flat API response URLs'
        );

        $this->assertStringContainsString(
            "is_string( \$data ) ? \$data : null",
            $content,
            'Print URL resolver should support string URL API responses'
        );

        $this->assertStringContainsString(
            "'api_response'",
            $content,
            'Missing print URL logs should include the API error string'
        );

        $this->assertStringContainsString(
            "'api_attempts'",
            $content,
            'Missing print URL logs should include every attempted print payload shape'
        );
    }

    #[Test]
    public function print_awb_repository_tries_fallback_payload_shapes(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php');
        $start = strpos($content, 'public function getPrintAwb');
        $this->assertNotFalse($start, 'getPrintAwb() method must exist');
        $methodBody = substr($content, $start, 2600);

        $this->assertStringContainsString(
            "\$payloads[] = array( 'awb' => \$awbs );",
            $methodBody,
            'Print AWB should first use the legacy known-good awb array payload'
        );

        $this->assertStringContainsString(
            "\$payloads[] = array( 'awb' => \$awbs[0] );",
            $methodBody,
            'Single per-row prints should fall back to one AWB as a scalar string'
        );

        $this->assertStringContainsString(
            "\$payloads[] = array( 'awbs' => \$awbs );",
            $methodBody,
            'Print AWB should fall back to a plural awbs array payload'
        );

        $this->assertStringContainsString(
            "\$payloads[] = array( 'tracking_number' => \$awbs[0] );",
            $methodBody,
            'Print AWB should fall back to tracking_number for API variants'
        );

        $this->assertStringContainsString(
            "\$response['attempts'] = \$attempts;",
            $methodBody,
            'Print AWB response should expose attempted payload shapes for failure logging'
        );

        $this->assertStringNotContainsString(
            "\$this->base_url = 'https://client.kiriminaja.com';",
            $methodBody,
            'Print AWB must not hardcode the base URL; it should inherit the env-aware base URL resolved by KiriminAjaApi'
        );

        $this->assertStringNotContainsString(
            "\$this->base_url =",
            $methodBody,
            'Print AWB must not override base_url at all; the constructor already resolves the env-aware base URL'
        );
    }

    #[Test]
    public function transaction_list_exposes_print_controls_for_all_tab_and_unprinted_badge(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/view/index.php');

        $this->assertStringContainsString(
            "\$kiriof_is_all_tab = ('all' === \$kiriof_status_filter);",
            $content,
            'The All tab should be treated as a print-capable tab'
        );

        $this->assertStringContainsString(
            'if ($kiriof_is_processed_tab || $kiriof_is_all_tab)',
            $content,
            'Bulk Print button should be rendered on both Processed and All tabs'
        );

        $this->assertStringContainsString(
            "esc_html__('Unprinted', 'kiriminaja-official')",
            $content,
            'Expedition & Service column should show Unprinted status for labels that have not been printed'
        );

        $this->assertStringNotContainsString(
            '$kiriof_statusUpper',
            $content,
            'Expedition & Service column should not render the transaction status twice'
        );
    }
}
