<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionActionDialogsContractTest extends TestCase
{
    #[Test]
    public function svelte_action_dialogs_cover_origin_and_cod_adjustment_request_contracts(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );

        $this->assertStringContainsString( "action: 'kiriof_change_origin_check'", $dialog );
        $this->assertStringContainsString( "action: 'kiriof_change_origin'", $dialog );
        $this->assertStringContainsString( 'courier_consent:', $dialog );
        $this->assertStringContainsString( "action: 'kiriof_cod_adjust'", $dialog );
        $this->assertStringContainsString( "'data[order_package_id]': action.data.kaOrderId", $dialog );
        $this->assertStringContainsString( "'data[new_total_cod]': String(Math.round(Number(codValue)))", $dialog );
        $this->assertStringContainsString( "action: 'kiriof_cancel_deficit'", $dialog );
        $this->assertStringContainsString( "action: 'kiriof_cancel_transaction'", $dialog );
        $this->assertStringContainsString( 'requiresCourierConsent', $dialog );
        $this->assertStringContainsString( 'codIsValid', $dialog );
        $this->assertStringNotContainsString( 'WCBackboneModal', $dialog );
    }

    #[Test]
    public function servers_enforce_origin_and_cod_adjustment_eligibility(): void
    {
        $transaction_controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php' );
        $cod_controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php' );
        $detail_data = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );

        $this->assertStringContainsString( "'new' !== (string) \$kiriof_transaction->status || ! empty( \$kiriof_transaction->awb )", $transaction_controller );
        $this->assertStringContainsString( 'Origin can only be changed before pickup is requested.', $transaction_controller );
        $this->assertStringContainsString( 'Order is not flagged as deficit', $cod_controller );
        $this->assertStringContainsString( 'Must not be less than Rp%s to avoid deficit', $cod_controller );
        $this->assertStringContainsString( 'Must not exceed Rp%s', $cod_controller );
        $this->assertStringContainsString( "'codMaximum' => defined( 'KIRIOF_MAX_COD_AMOUNT' )", $detail_data );
    }
}

final class WorkspaceDialogStackTest extends TestCase
{
    #[Test]
    public function svelte_dialog_content_is_layered_above_its_overlay_in_wordpress_admin(): void
    {
        $overlay = file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/dialog/dialog-overlay.svelte' );
        $content = file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/dialog/dialog-content.svelte' );

        $this->assertStringContainsString( '!z-[100000]', $overlay );
        $this->assertStringContainsString( '!z-[100001]', $content );
    }
}

final class TransactionActionDialogsParityTest extends TestCase
{
    #[Test]
    public function change_origin_dialog_shows_current_context_management_and_courier_comparison(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );
        $list = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionListRenderService.php' );
        $detail = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );

        $this->assertStringContainsString( 'currentShipmentOrigin', $dialog );
        $this->assertStringContainsString( 'action.data.currentOriginAddress', $dialog );
        $this->assertStringContainsString( 'href={locationsUrl}', $dialog );
        $this->assertStringContainsString( 'selectedCourier', $dialog );
        $this->assertStringContainsString( 'requiresCourierConsent', $dialog );
        $this->assertStringContainsString( "'locationsUrl' => admin_url( 'admin.php?page=wc-settings&tab=kiriminaja_warehouses' )", $list );
        $this->assertStringContainsString( "'locationsUrl' => admin_url( 'admin.php?page=wc-settings&tab=kiriminaja_warehouses' )", $detail );
    }

    #[Test]
    public function cod_adjustment_dialog_shows_financial_summary_and_live_payout(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );

        $this->assertStringContainsString( 'totalShipping', $dialog );
        $this->assertStringContainsString( 'estimatedPayout', $dialog );
        $this->assertStringContainsString( 'action.data.itemPrice', $dialog );
        $this->assertStringContainsString( 'action.data.shippingCost', $dialog );
        $this->assertStringContainsString( 'action.data.itemDiscount', $dialog );
        $this->assertStringContainsString( 'action.data.shippingDiscount', $dialog );
        $this->assertStringContainsString( 'COD Paid By Buyer', $dialog );
        $this->assertStringContainsString( 'Estimated COD Payout', $dialog );
    }
}

final class ShipmentLocationComboboxContractTest extends TestCase
{
    #[Test]
    public function list_action_data_includes_the_ka_order_id_required_by_change_origin(): void
    {
        $factory = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionListViewModelFactory.php' );

        $this->assertStringContainsString( "'kaOrderId'            => \$order_id", $factory );
    }

    #[Test]
    public function change_origin_uses_the_shared_combobox_and_excludes_the_current_location(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );
        $combobox = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/ShipmentLocationCombobox.svelte' );

        $this->assertStringContainsString( 'ShipmentLocationCombobox', $dialog );
        $this->assertStringContainsString( 'currentOriginLocationId', $dialog );
        $this->assertStringContainsString( 'locations.filter((location) => location.id !== currentLocationId)', $combobox );
        $this->assertStringContainsString( "action: 'kiriof_change_origin_check'", $dialog );
        $this->assertStringContainsString( 'order_id: action.data.kaOrderId', $dialog );
    }
}

final class ShipmentLocationComboboxLayerTest extends TestCase
{
    #[Test]
    public function location_combobox_popover_renders_above_the_kiriof_dialog_content(): void
    {
        $combobox = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/ShipmentLocationCombobox.svelte' );

        $this->assertStringContainsString( '!z-[100002]', $combobox );
        $this->assertStringContainsString( 'w-[var(--bits-popover-anchor-width)]', $combobox );
    }
}

final class CourierOptionComboboxContractTest extends TestCase
{
    #[Test]
    public function change_origin_uses_compact_courier_combobox_above_the_dialog_layer(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );
        $combobox = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/CourierOptionCombobox.svelte' );

        $this->assertStringContainsString( 'CourierOptionCombobox', $dialog );
        $this->assertStringContainsString( 'selectedCourier', $dialog );
        $this->assertStringContainsString( 'requiresCourierConsent', $dialog );
        $this->assertStringContainsString( '!z-[100002]', $combobox );
        $this->assertStringContainsString( 'Command.Input', $combobox );
        $this->assertStringContainsString( 'max-h-64', $combobox );
    }
}

final class TransactionActionDialogSelectionAndSpacingTest extends TestCase
{
    #[Test]
    public function origin_dialog_prefers_the_checked_current_courier_and_resets_wordpress_paragraph_margins(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );

        $this->assertStringContainsString( 'const current = options.find((option) => courierKey(option) ===', $dialog );
        $this->assertStringContainsString( 'result.comparison.service_code', $dialog );
        $this->assertStringContainsString( 'm-0 text-sm font-medium text-foreground', $dialog );
        $this->assertStringContainsString( 'm-0 text-sm text-muted-foreground', $dialog );
        $this->assertStringContainsString( '!grid gap-2.5', $dialog );
    }
}

final class ChangeOriginCompactTriggerTest extends TestCase
{
    #[Test]
    public function origin_and_courier_context_render_inside_their_combobox_triggers(): void
    {
        $location = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/ShipmentLocationCombobox.svelte' );
        $courier = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/CourierOptionCombobox.svelte' );
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );

        $this->assertStringContainsString( 'selected?.address', $location );
        $this->assertStringContainsString( 'selected.price', $courier );
        $this->assertStringNotContainsString( 'selectedLocation.address', $dialog );
        $this->assertStringNotContainsString( 'i18n.selectedCourier', $dialog );
    }
}

final class ChangeOriginOrderSummaryParityTest extends TestCase
{
    #[Test]
    public function svelte_change_origin_renders_the_legacy_before_after_order_summary(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );
        $controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php' );

        $this->assertStringContainsString( 'previous_courier?: string;', $dialog );
        $this->assertStringContainsString( 'previous_paid_shipping?: number;', $dialog );
        $this->assertStringContainsString( 'new_paid_shipping?: number;', $dialog );
        $this->assertStringContainsString( 'previous_discount?: number;', $dialog );
        $this->assertStringContainsString( 'new_discount?: number;', $dialog );
        $this->assertStringContainsString( 'previous_total?: number;', $dialog );
        $this->assertStringContainsString( 'new_total?: number;', $dialog );
        $this->assertStringContainsString( "i18n.orderBreakdown ?? 'Order summary'", $dialog );
        $this->assertStringContainsString( 'changeValue(originCheck.comparison.previous_paid_shipping, originCheck.comparison.new_paid_shipping)', $dialog );
        $this->assertStringContainsString( 'changeValue(originCheck.comparison.previous_total, originCheck.comparison.new_total)', $dialog );
        $this->assertStringContainsString( "'previous_paid_shipping' =>", $controller );
        $this->assertStringContainsString( "'new_paid_shipping' =>", $controller );
        $this->assertStringContainsString( "'previous_total' =>", $controller );
        $this->assertStringContainsString( "'new_total' =>", $controller );
    }
}
