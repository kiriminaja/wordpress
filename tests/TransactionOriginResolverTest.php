<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionOriginResolverTest extends TestCase
{
    #[Test]
    public function resolver_merges_partial_snapshots_over_the_default_or_selected_location(): void
    {
        $resolver = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionOriginResolver.php' );
        $list = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionListViewModelFactory.php' );
        $detail = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );

        $this->assertStringContainsString( '$this->location_service->getLocationOrDefault( $location_id )', $resolver );
        $this->assertStringContainsString( '$this->location_service->locationToOrigin( $location )', $resolver );
        $this->assertStringContainsString( '$source      = $this->mergeSnapshot( $source, $snapshot );', $resolver );
        $this->assertStringContainsString( '$this->location_service->formatAddress( $source )', $resolver );
        $this->assertStringContainsString( "'addressLines' => $address_lines", $resolver );
        $this->assertStringContainsString( '$this->origin_resolver->resolve( $row )', $list );
        $this->assertStringContainsString( "'currentLocationId'    => $origin_location_id", $list );
        $this->assertStringContainsString( '$this->origin_resolver->resolve($transaction)', $detail );
        $this->assertStringContainsString( '"address" => $origin["addressLines"]', $detail );
    }
}
