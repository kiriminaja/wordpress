<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderMetaboxDetailActionsTest extends TestCase {
    #[Test]
    public function metabox_actions_link_to_transaction_detail_and_open_deficit_dialog_only_when_allowed(): void {
        $service = file_get_contents( PLUGIN_DIR . '/inc/Services/OrderEditPageServices/ShippingInfoServices.php' );
        $controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/EditOrderController.php' );
        $metabox = file_get_contents( PLUGIN_DIR . '/templates/order/metabox-shipping.php' );
        $detail = file_get_contents( PLUGIN_DIR . '/src/lib/transaction-detail/TransactionDetail.svelte' );
        $bootstrap = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );

        $this->assertStringContainsString( "'transaction_id'     => (int) ( \$repo->id ?? 0 )", $service );
        $this->assertStringContainsString( "admin.php?page=kiriminaja-transaction-detail", $controller );
        $this->assertStringContainsString( "absint( \$data['transaction_id'] ?? 0 )", $controller );
        $this->assertStringContainsString( "add_query_arg( 'adjust_deficit', '1', \$detail_url )", $metabox );
        $this->assertStringContainsString( 'href="<?php echo esc_url( $kiriof_adjust_url ); ?>"', $metabox );
        $this->assertStringNotContainsString( 'kiriof-open-cod-adjustment', $metabox );
        $this->assertStringContainsString( 'View & Track in KiriminAja', $metabox );
        $this->assertStringNotContainsString( 'href="<?php echo esc_url($tracking_url); ?>"', $metabox );
        $workspace = file_get_contents( PLUGIN_DIR . '/src/entries/admin-workspace.ts' );
        $this->assertStringContainsString( "openAdjustDeficit: url.searchParams.get('adjust_deficit') === '1'", $workspace );
        $this->assertStringContainsString( 'render(document, route, url, navigationController.signal)', $workspace );
        $this->assertStringContainsString( "url.searchParams.delete('adjust_deficit')", $workspace );
        $this->assertStringContainsString( 'history.pushState({ kiriofWorkspace: true }, \'\', url)', $workspace );
        $this->assertStringContainsString( 'history.replaceState(history.state, \'\', url)', $workspace );
        $this->assertStringContainsString( 'openAdjustDeficit && transaction.actions.adjustDeficit', $detail );
        $this->assertStringContainsString( "kind: 'adjust-deficit', data: transaction.actions.data", $detail );
        $this->assertStringContainsString( '"supportsLiveTracking" => "" !== $awb && "-" !== $awb', $bootstrap );
    }
}
