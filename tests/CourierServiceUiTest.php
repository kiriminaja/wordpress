<?php

use PHPUnit\Framework\TestCase;

final class CourierServiceUiTest extends TestCase {
    public function test_both_contexts_use_the_shared_picker_and_payload(): void {
        $settings = file_get_contents( PLUGIN_DIR . '/templates/setting/setuped/section-couriers.php' );
        $onboarding = file_get_contents( PLUGIN_DIR . '/assets/admin/js/kj-onboarding.js' );
        foreach ( array( $settings, $onboarding ) as $adapter ) {
            $this->assertStringContainsString( 'window.kiriofCourierServices.create', $adapter );
            $this->assertStringContainsString( '.getPayload()', $adapter );
            $this->assertStringContainsString( 'success === false', $adapter );
            $this->assertStringContainsString( '.setDisabled(true)', $adapter );
        }
        $this->assertStringContainsString( 'picker.setState(savedState)', $settings );
        $this->assertStringContainsString( 'courierPicker.hasSelection()', $onboarding );
        $this->assertStringContainsString( 'onChange: courierChanged', $onboarding );
    }

    public function test_picker_preserves_state_and_uses_native_accessible_controls(): void {
        $script = file_get_contents( PLUGIN_DIR . '/assets/admin/js/kj-courier-services.js' );
        $style = file_get_contents( PLUGIN_DIR . '/assets/admin/css/kj-courier-services.css' );
        foreach ( array( 'getSelection:', 'getPayload:', 'hasSelection:', 'setAll:', 'service_selection: JSON.stringify(selection)', "input.type = 'checkbox'", 'wrapper.htmlFor = input.id', 'textContent', 'remembered[courier.code]', 'row.parent.indeterminate', 'service.aliases.some' ) as $contract ) {
            $this->assertStringContainsString( $contract, $script );
        }
        $this->assertStringNotContainsString( 'innerHTML', $script );
        $this->assertStringContainsString( 'repeat(auto-fit, minmax(min(100%, 280px), 1fr))', $style );
        $this->assertStringContainsString( ':focus-visible', $style );
        $this->assertStringContainsString( 'min-height: 44px', $style );
    }
}
