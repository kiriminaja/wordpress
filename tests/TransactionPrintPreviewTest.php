<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionPrintPreviewTest extends TestCase
{
    #[Test]
    public function print_preview_endpoint_returns_json_instead_of_redirecting_errors(): void
    {
        $controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/ShippingProcessController.php' );

        $this->assertStringContainsString( "wp_ajax_kiriof_print_label_preview", $controller );
        $this->assertStringContainsString( 'public function previewResiPrint(): void', $controller );
        $this->assertStringContainsString( "wp_send_json_success( array( 'url' => esc_url_raw( \$url ) ) )", $controller );
        $this->assertStringContainsString( "wp_send_json_error( array( 'message' =>", $controller );
        $this->assertStringContainsString( "preview_missing_print_url", $controller );
        $this->assertStringContainsString( "preview_empty_awb", $controller );
        $this->assertStringNotContainsString( 'redirectResiPrintFailure(', substr( $controller, strpos( $controller, 'public function previewResiPrint' ), strpos( $controller, 'private function outputResiPrint' ) - strpos( $controller, 'public function previewResiPrint' ) ) );
    }

    #[Test]
    public function workspace_printing_uses_shared_svelte_pdf_preview_dialog(): void
    {
        $preview = file_get_contents( PLUGIN_DIR . '/src/lib/ui/PrintPreviewDialog.svelte' );
        $list = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' );
        $detail = file_get_contents( PLUGIN_DIR . '/src/lib/transaction-detail/TransactionDetail.svelte' );
        $template = file_get_contents( PLUGIN_DIR . '/templates/transaction-process/app.php' );

        $this->assertStringContainsString( '<iframe', $preview );
        $this->assertStringContainsString( "kiriof:print-preview-error", $preview );
        $this->assertStringNotContainsString( 'onPrinted', $preview );
        $this->assertStringNotContainsString( 'svelte-pdf', $preview );
        $this->assertStringContainsString( "action: 'kiriof_print_label_preview'", $preview );
        $this->assertStringContainsString( 'KiriofDialog', $preview );
        $this->assertStringContainsString( 'PrintPreviewDialog', $list );
        $this->assertStringContainsString( 'PrintPreviewDialog', $detail );
        $this->assertStringNotContainsString( 'kiriof-print-bulk-form', $template );
        $this->assertStringContainsString( 'onclick={() => openPrintPreview([row.kaOrderId])}', $list );
    }
}
