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
        $css = file_get_contents(PLUGIN_DIR . '/src/styles/kj-onboarding-svelte.css');
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
		$this->assertStringNotContainsString("wp_enqueue_style( 'woocommerce_admin_styles' )", substr( $enqueue, strpos( $enqueue, 'private function enqueueOnboarding' ) ) );
		$this->assertStringNotContainsString('kj-onboarding.css', $enqueue);
		$this->assertStringContainsString('#adminmenumain', $css);
		$this->assertStringContainsString('#wpadminbar', $css);
		$this->assertStringContainsString('[data-kiriof-onboarding-app]', $css);
		$this->assertStringContainsString('position: fixed;', $css);
		$this->assertStringContainsString('overflow: hidden !important;', $css);
		$this->assertFileExists(PLUGIN_DIR . '/assets/admin/img/logo-tagline.svg');
		$this->assertStringContainsString("'logoUrl'", $page);
		$this->assertStringContainsString("'helpUrl'", $page);
		$this->assertStringContainsString("'initialStep'", $page);
		$this->assertStringContainsString("'steps'", $page);
		$this->assertStringContainsString('aria-busy="true"', $template);
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
		$this->assertStringContainsString( 'SubdistrictCombobox', $app );
		$this->assertStringContainsString( 'variant="destructive"', $app );
		$this->assertStringContainsString( 'class="min-h-20 resize-none"', $app );
		$this->assertStringContainsString( "{#if current !== 'complete'}", $app );
		$this->assertStringContainsString( 'class="m-0 max-w-md text-sm leading-6 text-muted-foreground"', $app );
		$this->assertStringContainsString( 'no-underline hover:no-underline focus:no-underline', $app );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/command/index.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/popover/index.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/onboarding/SubdistrictCombobox.svelte' );
        $this->assertStringContainsString( 'const canSubmitAccount = $derived', $app );
		$this->assertStringContainsString( 'disabled={busy || !canSubmitAccount}', $app );
		$this->assertStringContainsString( 'KiriminAja account connected', $app );
		$this->assertStringContainsString( 'Profile details are temporarily unavailable.', $app );
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
		$this->assertSame( 1, substr_count( $template, 'data-kiriof-onboarding-app' ) );
		$this->assertStringNotContainsString( 'data-kiriof-onboarding-fallback', $template );
		$this->assertStringNotContainsString( 'data-step-panel', $template );
		$this->assertStringNotContainsString( 'include __DIR__ . \'/steps/', $template );
		$this->assertDirectoryDoesNotExist( PLUGIN_DIR . '/templates/onboarding/steps' );
		$this->assertFileDoesNotExist( PLUGIN_DIR . '/assets/admin/css/kj-onboarding.css' );
        $this->assertFileDoesNotExist( PLUGIN_DIR . '/assets/admin/js/kj-onboarding.js' );
    }

    #[Test]
    public function settings_root_uses_the_shared_svelte_admin_foundation(): void
    {
        $controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/SettingController.php' );
        $configured = file_get_contents( PLUGIN_DIR . '/templates/setting/setuped/index.php' );
        $setup      = file_get_contents( PLUGIN_DIR . '/templates/setting/unsetuped/index.php' );
		$app         = file_get_contents( PLUGIN_DIR . '/templates/setting/app.php' );
		$entry       = file_get_contents( PLUGIN_DIR . '/src/entries/settings-root.ts' );
		$styles      = file_get_contents( PLUGIN_DIR . '/src/styles/settings-root.css' );
        $vite       = file_get_contents( PLUGIN_DIR . '/vite.config.ts' );
        $gitignore  = file_get_contents( PLUGIN_DIR . '/.gitignore' );

        $this->assertFileExists( PLUGIN_DIR . '/src/entries/settings-root.ts' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/SettingsRoot.svelte' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/wordpress/ajax.ts' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/ui/SettingSwitch.svelte' );
		$this->assertStringContainsString( "include KIRIOF_DIR . 'templates/setting/app.php'", $configured );
		$this->assertStringContainsString( "include KIRIOF_DIR . 'templates/setting/app.php'", $setup );
		$this->assertStringContainsString( 'data-kiriof-settings-root', $app );
		$this->assertStringContainsString( 'data-kiriof-settings-payload', $app );
		$this->assertStringNotContainsString( 'data-kiriof-settings-fallback', $app );
		$this->assertStringNotContainsString( 'data-kiriof-settings-fallback', $configured );
		$this->assertStringNotContainsString( 'data-kiriof-settings-fallback', $setup );
		$this->assertStringNotContainsString( 'querySelectorAll', $entry );
		$this->assertStringContainsString( 'margin: 0 auto;', $styles );
		$this->assertStringContainsString( "[data-slot='switch-thumb'][data-state='checked']", $styles );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/WebhooksSection.svelte' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/TechnicalSection.svelte' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/AccountSection.svelte' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/CouriersSection.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/TrackingSection.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/SettingsToolbar.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/assets/admin/img/icon-128x128.png' );
		$this->assertStringContainsString( 'kiriof-app-toolbar', $styles );
		$this->assertStringContainsString( 'kiriof-app-toolbar__actions', $styles );
		$this->assertStringContainsString( 'padding: 8px 0;', $styles );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/settings/navigation.ts' );
		$this->assertStringContainsString( 'history.pushState', $entry );
		$this->assertStringContainsString( "window.addEventListener('popstate'", $entry );
		$this->assertStringContainsString( 'screen_options_show_screen', file_get_contents( PLUGIN_DIR . '/inc/Pages/Admin.php' ) );
		$this->assertStringContainsString( "remove_all_actions( 'admin_notices' )", file_get_contents( PLUGIN_DIR . '/inc/Pages/Admin.php' ) );
        $this->assertStringContainsString( "'settings-root': 'src/entries/settings-root.ts'", $vite );
        $this->assertStringContainsString( "array( '', 'account', 'couriers', 'tracking', 'webhooks', 'technical' )", $controller );
        $this->assertStringContainsString( "wp_script_add_data( 'kiriof-settings-root', 'type', 'module' )", $controller );
        $this->assertStringContainsString( 'assets/admin/dist', $gitignore );
    }
}
