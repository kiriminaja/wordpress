<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/helpers/legacy-checkout-source.php';

final class LegacyCheckoutModulesTest extends TestCase
{
    #[Test]
    public function module_registration_chains_real_files_before_the_ready_entry(): void
    {
        $enqueue = file_get_contents(PLUGIN_DIR . '/inc/Base/Enqueue.php');
        $this->assertStringContainsString("foreach ( array( 'state', 'blocks-compatibility', 'classic-district', 'shipping-payment' ) as \$module )", $enqueue);
        $this->assertStringContainsString("\$handle = 'kiriof-checkout-' . \$module;", $enqueue);
        $this->assertStringContainsString("\$relative_path = 'assets/wp/js/checkout/' . \$module . '.js';", $enqueue);
        $this->assertStringContainsString("wp_register_script( \$handle, \$this->plugin_url . \$relative_path, \$legacy_dependencies, (string) filemtime( KIRIOF_DIR . \$relative_path ), array( 'in_footer' => true ) );", $enqueue);
        $this->assertStringContainsString("\$legacy_dependencies = array( \$handle );", $enqueue);
        $this->assertStringContainsString("'kiriof-form-billing-address',\n            \$this->plugin_url . 'assets/wp/js/form-billing-address.js',\n            \$legacy_dependencies,\n            (string) filemtime( KIRIOF_DIR . 'assets/wp/js/form-billing-address.js' )", $enqueue);

        $source = kiriof_legacy_checkout_source();
        $previous = -1;
        foreach (array('state', 'blocks-compatibility', 'classic-district', 'shipping-payment') as $module) {
            $path = PLUGIN_DIR . '/assets/wp/js/checkout/' . $module . '.js';
            $this->assertFileExists($path);
            $moduleSource = file_get_contents($path);
            $this->assertStringNotContainsString('jQuery(document).ready', $moduleSource);
            $position = strpos($source, $moduleSource);
            $this->assertNotFalse($position);
            $this->assertGreaterThan($previous, $position, 'Source inspection must follow the real dependency order');
            $previous = $position;
        }
        $ready = strpos($source, 'jQuery(document).ready(function($)');
        $this->assertNotFalse($ready);
        $this->assertGreaterThan($previous, $ready);
    }

    #[Test]
    public function configuration_is_localized_before_state_initialization_not_on_the_entry(): void
    {
        $template = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');
        $state = file_get_contents(PLUGIN_DIR . '/assets/wp/js/checkout/state.js');
        $entry = file_get_contents(PLUGIN_DIR . '/assets/wp/js/form-billing-address.js');
        $this->assertStringContainsString("wp_localize_script(\n            'kiriof-checkout-state',\n            'kiriofBillingAddressConfig',\n            \$kiriof_billing_address_config", $template);
        $this->assertStringNotContainsString("wp_localize_script(\n            'kiriof-form-billing-address'", $template);
        $this->assertStringContainsString('var kiriofBillingAddressConfig = window.kiriofBillingAddressConfig || {};', $state);
        $this->assertStringContainsString('var kiriofSavedDistrictByPostcode = kiriofBillingAddressConfig.savedDistrictByPostcode || {};', $state);
        $this->assertStringNotContainsString('var kiriofBillingAddressConfig', $entry);
        $this->assertStringNotContainsString('function kiriofCodInsurance', $entry);
        $this->assertStringNotContainsString('function changeDistrict', $entry);
        $this->assertStringContainsString('kiriofInitBlockCheckoutCompatibility();', $entry);
    }
}
