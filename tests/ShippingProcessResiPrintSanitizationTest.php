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
}

