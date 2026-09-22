<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OnboardingFeatureTest extends TestCase
{
    #[Test]
    public function onboarding_registers_a_hidden_full_screen_admin_page(): void
    {
        $page = file_get_contents(PLUGIN_DIR . '/inc/Pages/Onboarding.php');
        $enqueue = file_get_contents(PLUGIN_DIR . '/inc/Base/Enqueue.php');
        $css = file_get_contents(PLUGIN_DIR . '/assets/admin/css/kj-onboarding.css');
        $template = file_get_contents(PLUGIN_DIR . '/templates/onboarding/index.php');

        $this->assertStringContainsString("'kiriminaja-onboarding'", $page);
        $this->assertStringContainsString("'manage_woocommerce'", $page);
        $this->assertStringContainsString('remove_submenu_page', $page);
		$this->assertStringContainsString("add_action( 'admin_head', array( \$this, 'hide_page' ) )", $page);
		$this->assertStringContainsString("add_action( 'current_screen', array( \$this, 'suppress_admin_notices' ), 1 )", $page);
		$this->assertStringContainsString("remove_all_actions( 'admin_notices' )", $page);
		$this->assertStringContainsString("remove_all_actions( 'all_admin_notices' )", $page);
		$this->assertStringNotContainsString("add_action( 'admin_menu', array( \$this, 'hide_page' )", $page);
        $this->assertStringContainsString("'kiriminaja-onboarding' === \$page", $enqueue);
        $this->assertStringContainsString('enqueueOnboarding', $enqueue);
		$this->assertStringContainsString("wp_enqueue_style( 'woocommerce_admin_styles' )", $enqueue);
		$this->assertStringContainsString('#adminmenumain', $css);
		$this->assertStringContainsString('#wpadminbar', $css);
		$this->assertStringContainsString('position: sticky', $css);
		$this->assertStringContainsString('top: 0;', $css);
		$this->assertStringContainsString('bottom: 0;', $css);
		$this->assertStringContainsString('z-index: 20;', $css);
		$this->assertStringContainsString('left: 50%;', $css);
		$this->assertStringContainsString('transform: translateX(-50%);', $css);
		$this->assertStringContainsString('width: min(760px, calc(100% - 420px));', $css);
		$this->assertFileExists(PLUGIN_DIR . '/assets/admin/img/logo-tagline.svg');
		$this->assertStringContainsString('assets/admin/img/logo-tagline.svg', $template);
		$this->assertStringContainsString('kiriof-onboarding__header-actions', $template);
		$this->assertStringContainsString('https://kiriminaja.com/solusi/plugin-woocommerce', $template);
		$this->assertStringContainsString('dashicons-editor-help', $template);
		$this->assertStringContainsString('dashicons-no-alt', $template);
		$this->assertStringNotContainsString('kiriof-onboarding__mark', $template);
		$this->assertStringContainsString('.kiriof-onboarding__brand img', $css);
		$this->assertStringContainsString('width: 132px;', $css);
		$this->assertStringContainsString('background: #fff;', $css);
		$this->assertStringContainsString('min-height: 34px;', $css);
		$this->assertStringContainsString('border-top: 1px solid #e3ddf6;', $css);
		$this->assertStringContainsString('env(safe-area-inset-bottom)', $css);
		$this->assertStringContainsString('line-height: 1;', $css);
		$this->assertStringContainsString('display: block;', $css);
		$this->assertStringContainsString('max-height: none;', $css);
		$this->assertStringContainsString('overflow: visible;', $css);
		$this->assertStringContainsString('.kiriof-onboarding__map', $css);
		$this->assertStringContainsString('max-width: none;', $css);
		$this->assertStringContainsString('padding: 14px 0 152px;', $css);
	}

	#[Test]
	public function onboarding_subdistrict_lookup_surfaces_failures_and_encodes_the_query(): void
	{
		$controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/GeneralAjaxController.php');
		$repository = file_get_contents(PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php');

		$this->assertStringContainsString("'subdistrict_lookup_failed'", $controller);
		$this->assertStringContainsString("wp_send_json_error(", $controller);
		$this->assertStringContainsString("'Subdistrict lookup failed.'", $controller);
		$this->assertStringNotContainsString('wp_send_json_success([])', $controller);
		$this->assertStringContainsString('KiriminAja::getDistrictByName( (string) $search )', $repository);
	}

    #[Test]
    public function onboarding_uses_the_shadcn_svelte_application(): void
    {
        $app = file_get_contents( PLUGIN_DIR . '/src/lib/onboarding/OnboardingApp.svelte' );
        $entry = file_get_contents( PLUGIN_DIR . '/src/entries/onboarding-progress.ts' );
        $enqueue = file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' );
        $components = file_get_contents( PLUGIN_DIR . '/components.json' );

        $this->assertStringContainsString( 'from \'$lib/components/ui/button\'', $app );
        $this->assertStringContainsString( 'from \'$lib/components/ui/card\'', $app );
        $this->assertStringContainsString( 'from \'$lib/components/ui/field\'', $app );
        $this->assertStringContainsString( 'from \'$lib/components/ui/switch\'', $app );
        $this->assertStringContainsString( 'kiriof_store_origin_data', $app );
        $this->assertStringContainsString( 'kiriof_store_courier_whitelist', $app );
        $this->assertStringContainsString( 'kiriof_enable_shipping_method', $app );
        $this->assertStringContainsString( 'kiriminaja_subdistrict_search', $app );
        $this->assertStringContainsString( 'OnboardingApp', $entry );
        $this->assertStringNotContainsString( "'kiriof-onboarding-script'", substr( $enqueue, strpos( $enqueue, 'private function enqueueOnboarding' ) ) );
        $this->assertStringContainsString( 'filter_module_script_tag', $enqueue );
        $this->assertStringContainsString( 'shadcn-svelte.com/schema.json', $components );
        $this->assertStringContainsString( 'iconLibrary', $components );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/button/index.ts' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/card/index.ts' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/field/index.ts' );
    }

    #[Test]
    public function activation_redirect_requires_flag_and_exempts_background_requests(): void
    {
        $page = file_get_contents(PLUGIN_DIR . '/inc/Pages/Onboarding.php');

		$this->assertStringContainsString('get_option( self::REDIRECT_OPTION, false )', $page);
		$this->assertStringContainsString('UPDATE_REDIRECT_OPTION', $page);
		$this->assertStringContainsString('should_consume_activation_redirect', $page);
		$this->assertStringContainsString('is_kiriminaja_admin_page', $page);
		$this->assertStringContainsString("'kiriminaja-konfigurasi'", $page);
		$this->assertStringContainsString("'kiriminaja-transaction-process'", $page);
		$this->assertStringContainsString("array( 'shipping', 'kiriminaja_warehouses' )", $page);
		$this->assertStringNotContainsString('should_gate_request', $page);

        foreach (['wp_doing_ajax()', 'wp_doing_cron()', 'REST_REQUEST', 'WP_CLI', "'admin-post.php'", "'plugins.php'", "'update.php'", 'is_network_admin()'] as $exception) {
            $this->assertStringContainsString($exception, $page);
        }
    }

    #[Test]
    public function products_and_tracking_are_optional_native_notices(): void
    {
        $state = file_get_contents(PLUGIN_DIR . '/inc/Services/OnboardingSetupStateService.php');
        $admin = file_get_contents(PLUGIN_DIR . '/inc/Pages/Admin.php');

        $this->assertMatchesRegularExpression("/'products'.{0,300}'required'\s*=>\s*false/s", $state);
        $this->assertMatchesRegularExpression("/'tracking'.{0,300}'required'\s*=>\s*false/s", $state);
        $this->assertStringContainsString('notice notice-warning is-dismissible', $admin);
        $this->assertStringContainsString('notice notice-info is-dismissible', $admin);
    }

    #[Test]
    public function activation_redirect_is_deferred_and_skips_bulk_activation(): void
    {
        $plugin = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');

		$this->assertStringContainsString('kiriof_onboarding_activation_redirect', $plugin);
		$this->assertStringContainsString('kiriof_onboarding_update_redirect', $plugin);
		$this->assertStringContainsString("'activate-selected'", $plugin);
		$this->assertStringNotContainsString('wp_safe_redirect', substr($plugin, strpos($plugin, 'function kiriof_activate_plugin'), 2500));
		$this->assertStringContainsString('upgrader_process_complete', $plugin);
    }

    #[Test]
    public function onboarding_mounts_a_single_svelte_root(): void
    {
        $template = file_get_contents( PLUGIN_DIR . '/templates/onboarding/index.php' );
        $this->assertStringContainsString( 'data-kiriof-onboarding-app', $template );
        $this->assertStringContainsString( 'data-kiriof-onboarding-payload', $template );
        $this->assertFileDoesNotExist( PLUGIN_DIR . '/assets/admin/js/kj-onboarding.js' );
    }

    #[Test]
    public function settings_root_uses_the_shared_svelte_admin_foundation(): void
    {
        $controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/SettingController.php' );
        $configured = file_get_contents( PLUGIN_DIR . '/templates/setting/setuped/index.php' );
        $setup      = file_get_contents( PLUGIN_DIR . '/templates/setting/unsetuped/index.php' );
        $webhooks   = file_get_contents( PLUGIN_DIR . '/templates/setting/setuped/section-webhooks.php' );
        $technical  = file_get_contents( PLUGIN_DIR . '/templates/setting/setuped/section-technical.php' );
        $vite       = file_get_contents( PLUGIN_DIR . '/vite.config.ts' );
        $gitignore  = file_get_contents( PLUGIN_DIR . '/.gitignore' );

        $this->assertFileExists( PLUGIN_DIR . '/src/entries/settings-root.ts' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/SettingsRoot.svelte' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/wordpress/ajax.ts' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/ui/SettingSwitch.svelte' );
        $this->assertStringContainsString( 'data-kiriof-settings-root', $configured );
        $this->assertStringContainsString( 'data-kiriof-settings-payload', $configured );
        $this->assertStringContainsString( 'data-kiriof-settings-fallback', $configured );
        $this->assertStringContainsString( 'data-kiriof-settings-root', $setup );
        $this->assertStringContainsString( 'data-kiriof-settings-root', $webhooks );
        $this->assertStringContainsString( 'data-kiriof-settings-root', $technical );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/WebhooksSection.svelte' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/TechnicalSection.svelte' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/AccountSection.svelte' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/CouriersSection.svelte' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/TrackingSection.svelte' );
        $this->assertStringContainsString( "'settings-root': 'src/entries/settings-root.ts'", $vite );
        $this->assertStringContainsString( "array( '', 'account', 'couriers', 'tracking', 'webhooks', 'technical' )", $controller );
        $this->assertStringContainsString( "wp_script_add_data( 'kiriof-settings-root', 'type', 'module' )", $controller );
        $this->assertStringContainsString( 'assets/admin/dist', $gitignore );
    }
}
