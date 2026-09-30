<?php

use PHPUnit\Framework\TestCase;

final class CourierServiceUiTest extends TestCase {
    public function test_instant_picker_tab_is_enabled_and_has_selectable_content(): void {
        $picker = file_get_contents( PLUGIN_DIR . '/src/lib/couriers/CourierServicePicker.svelte' );
        $this->assertSame( 1, preg_match( '/<Tabs\.Trigger\b(?=[^>]*\bvalue="instant")([^>]*)>/', $picker, $trigger ) );
        $this->assertDoesNotMatchRegularExpression( '/\b(?:disabled|aria-disabled)\b/', $trigger[1] );
        $this->assertStringContainsString( 'bind:value={deliveryType}', $picker );
        $this->assertStringContainsString( "const deliveryTypes: DeliveryType[] = ['express', 'instant']", $picker );
        $this->assertStringContainsString( '{#each deliveryTypes as tabType (tabType)}', $picker );
        $this->assertMatchesRegularExpression( '/<Tabs\.Content\s+value=\{tabType\}/', $picker );
        $this->assertStringContainsString( 'courierDeliveryType(courier) === tabType', $picker );
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
        foreach ( array( 'courierSelection(', 'toggleCourier(', 'toggleService(', '<SettingSwitch', 'checked={status.checked}', 'aria-label=', 'aria-labelledby=', 'for={`${prefix}-service-', 'if (!disabled)', 'sm:grid-cols-2', '2xl:grid-cols-4', '<CourierLogo', '<Tabs.Trigger', 'value="instant"', 'value="international"', 'bind:value={deliveryType}', 'courierDeliveryType(courier) === tabType', 'class="kiriof-shadcn !grid min-w-0 gap-3"', 'service.name', '{service.code}' ) as $contract ) {
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
        $this->assertDoesNotMatchRegularExpression( '/value="instant"\s+disabled/', $picker );
        $this->assertMatchesRegularExpression( '/value="international"\s+disabled/', $picker );
        $settings = file_get_contents( PLUGIN_DIR . '/src/lib/settings/CouriersSection.svelte' );
        $this->assertStringContainsString( 'setAllServices(selectionState, tabCouriers, enabled)', $settings );
        $this->assertStringContainsString( 'bind:deliveryType', $settings );
        $onboarding = file_get_contents( PLUGIN_DIR . '/src/lib/onboarding/OnboardingApp.svelte' );
        $this->assertStringContainsString( 'setAllServices(courierState, tabCouriers, true)', $onboarding );
        $this->assertStringContainsString( 'setAllServices(courierState, tabCouriers, false)', $onboarding );
        $this->assertStringContainsString( 'bind:deliveryType', $onboarding );
        $transactions = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' );
        $this->assertStringContainsString( '<CourierLogo', $transactions );
        $logo = file_get_contents( PLUGIN_DIR . '/src/lib/ui/CourierLogo.svelte' );
        $this->assertStringContainsString( 'courierImage(', $logo );
    }
}
