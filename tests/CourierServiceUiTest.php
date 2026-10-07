<?php

use PHPUnit\Framework\TestCase;

final class CourierServiceUiTest extends TestCase {
    public function test_picker_shows_all_delivery_types_in_one_searchable_grid(): void {
        $picker = file_get_contents( PLUGIN_DIR . '/src/lib/couriers/CourierServicePicker.svelte' );
        foreach ( array( 'sortCouriersByName(couriers)', 'rows.filter(({ courier }) => matchesCourierSearch(courier, search))', '{#each visibleRows as { courier, index, status } (courier.code)}', "search = \$bindable('')", 'showSearch = true', '{#if showSearch}', "search = '';" ) as $contract ) {
            $this->assertStringContainsString( $contract, $picker );
        }
        foreach ( array( '<Tabs.', "from '\$lib/components/ui/tabs'", 'deliveryType', 'courierDeliveryType', 'instantSetupHint', 'not available yet', 'not yet available', 'noInstantCouriers' ) as $removed ) {
            $this->assertStringNotContainsString( $removed, $picker );
        }
    }

    public function test_search_is_adjacent_to_bulk_controls_and_bound_to_shared_picker(): void {
        foreach ( array( 'src/lib/settings/CouriersSection.svelte', 'src/lib/onboarding/OnboardingApp.svelte' ) as $file ) {
            $adapter = file_get_contents( PLUGIN_DIR . '/' . $file );
            $this->assertStringContainsString( 'type="search"', $adapter );
            $this->assertStringContainsString( 'bind:search', $adapter );
            $this->assertStringContainsString( 'showSearch={false}', $adapter );
            $this->assertStringContainsString( 'aria-label=', $adapter );
            $this->assertStringNotContainsString( 'deliveryType', $adapter );
            $this->assertStringNotContainsString( 'courierDeliveryType', $adapter );
            $this->assertStringNotContainsString( 'tabCouriers', $adapter );
            $enable = str_contains( $file, '/settings/' ) ? 'onclick={() => setAll(true)}' : 'onclick={enableAllCouriers}';
            $this->assertLessThan( strpos( $adapter, $enable ), strpos( $adapter, 'type="search"' ) );
        }
    }

    public function test_courier_picker_does_not_depend_on_removed_legacy_assets(): void {
        $enqueue = file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' );
        $this->assertStringNotContainsString( 'enqueueCourierServices', $enqueue );
        $this->assertStringNotContainsString( 'kj-courier-services', $enqueue );
        foreach ( array( 'src/lib/settings/CouriersSection.svelte', 'src/lib/onboarding/OnboardingApp.svelte' ) as $modern ) {
            $this->assertFileExists( PLUGIN_DIR . '/' . $modern );
        }
    }

    public function test_both_contexts_use_the_shared_picker_and_payload(): void {
        $settings = file_get_contents( PLUGIN_DIR . '/src/lib/settings/CouriersSection.svelte' );
        $onboarding = file_get_contents( PLUGIN_DIR . '/src/lib/onboarding/OnboardingApp.svelte' );
        foreach ( array( $settings, $onboarding ) as $adapter ) {
            foreach ( array( "from '\$lib/couriers/CourierServicePicker.svelte'", "from '\$lib/couriers/selection'", '<CourierServicePicker', 'initializeSelection(', 'selectionPayload(', 'kiriof_store_courier_whitelist', 'disabled=', 'onChange=', 'catch (requestError)' ) as $contract ) {
                $this->assertStringContainsString( $contract, $adapter );
            }
        }
        $this->assertStringContainsString( 'selectionState = previous', $settings );
        $this->assertStringContainsString( 'if (!loaded || loading || saving) return', $settings );
        $this->assertStringContainsString( '!hasSelection(courierState.selection)', $onboarding );
        $this->assertStringContainsString( 'done.couriers = true', $onboarding );
        $this->assertLessThan( strpos( $onboarding, 'done.couriers = true' ), strpos( $onboarding, "await post(\n        'kiriof_store_courier_whitelist'" ) );
        $ajax = file_get_contents( PLUGIN_DIR . '/src/lib/wordpress/ajax.ts' );
        $this->assertStringContainsString( 'payload.success === false', $ajax );
        $this->assertStringContainsString( 'Number(result.status ?? 0) !== 200', $ajax );
    }

    public function test_onboarding_rejects_failed_saves_and_requires_loaded_nonempty_selection(): void {
        $onboarding = file_get_contents( PLUGIN_DIR . '/src/lib/onboarding/OnboardingApp.svelte' );
        foreach ( array(
            'if (!response.ok || payload.success === false || !result || status !== 200)',
            'if (busy || couriersLoading || !courierLoaded || courierLoadError) return',
            'if (!hasSelection(courierState.selection))',
            'setMessage(bootstrap.i18n.courierRequired)',
            "if (step === 'shipping') return Boolean(done.address && done.couriers)",
            "if (step === 'complete') return Boolean(done.shipping)",
            'busy = false',
        ) as $contract ) {
            $this->assertStringContainsString( $contract, $onboarding );
        }
    }

    public function test_picker_preserves_state_and_uses_accessible_controls(): void {
        $picker = file_get_contents( PLUGIN_DIR . '/src/lib/couriers/CourierServicePicker.svelte' );
        $selection = file_get_contents( PLUGIN_DIR . '/src/lib/couriers/selection.ts' );
        foreach ( array( 'courierSelection(', 'toggleCourier(', 'toggleService(', '<SettingSwitch', 'checked={status.checked}', 'aria-label=', 'aria-labelledby=', 'for={`${prefix}-service-', 'if (!disabled)', 'sm:grid-cols-2', '2xl:grid-cols-4', '<CourierLogo', 'class="kiriof-shadcn !grid min-w-0 gap-3"', 'service.name', '{service.code}' ) as $contract ) {
            $this->assertStringContainsString( $contract, $picker );
        }
        $this->assertStringNotContainsString( '{@html', $picker );
        $this->assertStringNotContainsString( 'activeOnly', $picker );
        foreach ( array( 'remembered[courier.code]', 'row.aliases?.some', 'service_selection: JSON.stringify(selection)', 'export function hasSelection(', 'export function setAllServices(', 'unavailable: true' ) as $contract ) {
            $this->assertStringContainsString( $contract, $selection );
        }
        $switch = file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/switch/switch.svelte' );
        $this->assertStringContainsString( 'focus-visible:', $switch );
        $this->assertStringNotContainsString( '<Checkbox', $picker );
        $settings = file_get_contents( PLUGIN_DIR . '/src/lib/settings/CouriersSection.svelte' );
        $this->assertStringContainsString( 'setAllServices(selectionState, couriers, enabled)', $settings );
        $this->assertStringContainsString( 'bind:search', $settings );
        $onboarding = file_get_contents( PLUGIN_DIR . '/src/lib/onboarding/OnboardingApp.svelte' );
        $this->assertStringContainsString( 'setAllServices(courierState, couriers, true)', $onboarding );
        $this->assertStringContainsString( 'setAllServices(courierState, couriers, false)', $onboarding );
        $this->assertStringContainsString( 'bind:search={courierSearch}', $onboarding );
        $transactions = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' );
        $this->assertStringContainsString( '<CourierLogo', $transactions );
        $logo = file_get_contents( PLUGIN_DIR . '/src/lib/ui/CourierLogo.svelte' );
        $this->assertStringContainsString( 'courierImage(', $logo );
    }
}
