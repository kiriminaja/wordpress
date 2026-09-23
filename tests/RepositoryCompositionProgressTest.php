<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RepositoryCompositionProgressTest extends TestCase
{
    #[Test]
    public function setting_controller_reuses_one_setting_repository_dependency(): void
    {
        $source = file_get_contents( PLUGIN_DIR . '/inc/Controllers/SettingController.php' );

        $this->assertStringContainsString( 'private SettingRepository $setting_repository;', $source );
        $this->assertStringContainsString( '$this->setting_repository       = $setting_repository;', $source );
        $this->assertSame( 0, substr_count( $source, 'new SettingRepository()' ) );
        $this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\SettingRepository()', $source );
    }

    #[Test]
    public function onboarding_state_reuses_injected_dependencies(): void
    {
        $source = file_get_contents( PLUGIN_DIR . '/inc/Services/OnboardingSetupStateService.php' );

        $this->assertStringContainsString( 'private SettingRepository $setting_repository;', $source );
        $this->assertStringContainsString( 'private ?WooCommerceShippingMethodRegistrationService $shipping_method_service;', $source );
        $this->assertStringContainsString( 'private ?KiriminajaApiService $api_service;', $source );
        $this->assertSame( 1, substr_count( $source, 'new SettingRepository()' ) );
        $this->assertSame( 1, substr_count( $source, 'new WooCommerceShippingMethodRegistrationService()' ) );
        $this->assertSame( 1, substr_count( $source, 'new KiriminajaApiService()' ) );
		$this->assertStringContainsString( '$this->get_api_service()->getProfile()', $source );
		$this->assertStringNotContainsString( '( new KiriminajaApiService() )->getProfile()', $source );
    }
}
