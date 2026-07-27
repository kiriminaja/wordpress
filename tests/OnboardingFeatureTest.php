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
    public function onboarding_contains_four_interactive_required_steps(): void
    {
        $template = file_get_contents(PLUGIN_DIR . '/templates/onboarding/index.php');
        $account = file_get_contents(PLUGIN_DIR . '/templates/onboarding/steps/account.php');
        $address = file_get_contents(PLUGIN_DIR . '/templates/onboarding/steps/address.php');
        $css = file_get_contents(PLUGIN_DIR . '/assets/admin/css/kj-onboarding.css');
        $script = file_get_contents(PLUGIN_DIR . '/assets/admin/js/kj-onboarding.js');

        foreach (['account.php', 'address.php', 'couriers.php', 'shipping.php'] as $step) {
            $this->assertStringContainsString($step, $template);
        }

        foreach (['kiriof_store_integration_data', 'kiriof_store_origin_data', 'kiriof_store_courier_whitelist', 'kiriof_enable_shipping_method'] as $action) {
            $this->assertStringContainsString($action, $script);
        }

		$this->assertStringContainsString('&#10003;', $template);
		$this->assertStringContainsString('woocommerce-input-toggle', $script);
		$this->assertStringContainsString('data-account-complete', $template);
		$this->assertStringContainsString('accountComplete', $script);
		$this->assertStringContainsString('canVisit(target)', $script);
		$this->assertStringContainsString('isStepDone(\'couriers\')', $script);
		$this->assertStringContainsString('blockNavigation(target)', $script);
		$this->assertStringContainsString('Save at least one courier service before continuing.', $script);
		$this->assertStringContainsString('Complete previous required steps before finishing.', $script);
		$this->assertStringContainsString("$('[data-step-target=\"couriers\"]').removeClass('is-done')", $script);
		$this->assertStringContainsString('accountRequired', $script);
		$this->assertStringContainsString('accountRequired', file_get_contents(PLUGIN_DIR . '/inc/Base/Enqueue.php'));
		$this->assertStringContainsString('templates/setting/partials/account-connection-status.php', $account);
		$this->assertStringContainsString('Connection', $account);
		$this->assertStringContainsString('get_integration_values', file_get_contents(PLUGIN_DIR . '/inc/Services/OnboardingSetupStateService.php'));
		$this->assertStringContainsString('nav_title', file_get_contents(PLUGIN_DIR . '/inc/Services/OnboardingSetupStateService.php'));
		$this->assertStringContainsString('get_connection_state', file_get_contents(PLUGIN_DIR . '/inc/Services/OnboardingSetupStateService.php'));
		$this->assertStringContainsString('kiriof-onboarding__field', $address);
		$this->assertStringContainsString('kiriof-onboarding__locate', $script);
		$this->assertStringContainsString('navigator.geolocation.getCurrentPosition', $script);
		$this->assertStringContainsString('currentLocation', file_get_contents(PLUGIN_DIR . '/inc/Base/Enqueue.php'));
		$this->assertStringContainsString('disconnectConfirm', file_get_contents(PLUGIN_DIR . '/inc/Base/Enqueue.php'));
		$this->assertStringContainsString('post(\'kiriof_disconnect_integration\')', $script);
		$this->assertStringNotContainsString("hasClass('is-done') ||", $script);
		$this->assertStringNotContainsString('kiriof-onboarding__intro', $template);
		$this->assertStringNotContainsString('Set up shipping for your store', $template);
		$this->assertStringContainsString('class="form-table"', $account);
		$this->assertStringContainsString('class="form-table"', $address);
		$this->assertStringContainsString('scope="row"', $address);
		$this->assertStringContainsString('class="regular-text kiriof-onboarding__field"', $address);
		$this->assertStringContainsString('class="large-text kiriof-onboarding__field"', $address);
		$this->assertStringContainsString('.kiriof-onboarding__field', $css);
		$this->assertStringContainsString('white-space: nowrap;', $css);
		$this->assertStringContainsString('border: 0;', $css);
		$this->assertStringContainsString('background: #f5f0ff;', $css);
		$this->assertStringContainsString("Account Connection", file_get_contents(PLUGIN_DIR . '/inc/Services/OnboardingSetupStateService.php'));
		$this->assertStringContainsString("'nav_title' => __( 'Account', 'kiriminaja-official' )", file_get_contents(PLUGIN_DIR . '/inc/Services/OnboardingSetupStateService.php'));
		$this->assertStringContainsString('get_connection_state', file_get_contents(PLUGIN_DIR . '/inc/Services/OnboardingSetupStateService.php'));
		$this->assertStringContainsString('KiriminajaApiService::class', file_get_contents(PLUGIN_DIR . '/inc/Services/OnboardingSetupStateService.php'));
		$this->assertStringContainsString('kiriof-onboarding__check', file_get_contents(PLUGIN_DIR . '/templates/onboarding/steps/shipping.php'));
		$this->assertStringContainsString('dashicons-clock', file_get_contents(PLUGIN_DIR . '/templates/onboarding/steps/shipping.php'));
		$this->assertStringContainsString('setCheck', $script);
		$this->assertStringContainsString('updateContinueState', $script);
		$this->assertStringContainsString('hasSelectedCouriers', $script);
		$this->assertStringContainsString("current === 'couriers' && !hasSelectedCouriers()", $script);
		$this->assertStringContainsString('kiriof-onboarding__segmented-actions', file_get_contents(PLUGIN_DIR . '/templates/onboarding/steps/couriers.php'));
		$this->assertStringContainsString('.kiriof-onboarding__segmented-actions', $css);
		$this->assertStringContainsString('is-complete', $script);
		$this->assertStringContainsString('.kiriof-onboarding.is-complete .kiriof-onboarding__header', $css);
		$this->assertStringContainsString('[data-step-panel="complete"].is-active', $css);
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
}
