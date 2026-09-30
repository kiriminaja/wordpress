<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionDetailSectionResilienceTest extends TestCase
{
    #[Test]
    public function optional_detail_sections_fail_independently_instead_of_replacing_the_whole_payload(): void
    {
        $service = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );

        foreach ( array( 'wc_order', 'recipient', 'origin', 'courier', 'items', 'notes', 'actions', 'shipment_locations' ) as $section ) {
            $this->assertStringContainsString( "'{$section}'", $service );
        }
        $this->assertStringContainsString( 'Transaction detail section failed.', $service );
        $this->assertStringContainsString( 'safe_shipment_locations', $service );
        $this->assertStringContainsString( 'empty( $warnings )', $service );
    }
}
