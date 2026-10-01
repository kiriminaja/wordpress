<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantCheckoutDiagnosticsRuntimeTest extends TestCase {
    #[Test]
    public function diagnostics_are_registered_and_exported_under_the_normalized_log_source(): void {
        $init = file_get_contents(PLUGIN_DIR . '/inc/Init.php');
        $settings = file_get_contents(PLUGIN_DIR . '/inc/Controllers/SettingController.php');
        $this->assertStringContainsString('Services\\InstantCheckoutDiagnosticsService::class', $init);
        $this->assertStringContainsString("'kiriminaja_instant'", $settings);
    }

    private function runFixture(string $scenario = '', array $extra = []): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-checkout-diagnostics-runtime.php') . ' ' . escapeshellarg(json_encode(['scenario' => $scenario] + $extra, JSON_THROW_ON_ERROR)));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function composition_is_lazy_and_registers_read_only_lifecycle_hooks(): void {
        $r = $this->runFixture();
        $this->assertSame([0, 0], $r['lazy_counts']);
        $this->assertSame([10, 1], $r['hooks']['wp']);
        $this->assertSame([100, 0], $r['hooks']['woocommerce_checkout_update_order_review']);
        $this->assertSame([100, 1], $r['hooks']['woocommerce_after_calculate_totals']);
    }

    #[Test]
    public function incomplete_order_integration_never_claims_readiness(): void {
        $r = $this->runFixture();
        $this->assertFalse($r['snapshot']['ready']);
        $this->assertSame(['checkout_integration_not_ready'], $r['snapshot']['reasons']);
        $this->assertSame('warning', $r['logs'][0]['level']);
        $this->assertFalse($r['snapshot']['live_quote_checked']);
        $this->assertSame('configured_store_readiness', $r['snapshot']['code']);
    }

    #[Test]
    public function complete_local_configuration_is_info_not_live_availability(): void {
        $r = $this->runFixture('ready');
        $this->assertTrue($r['snapshot']['ready']);
        $this->assertSame('info', $r['logs'][0]['level']);
        $this->assertFalse($r['snapshot']['live_quote_checked']);
    }

    #[Test]
    public function missing_and_legacy_pin_fail_closed(): void {
        foreach (['no_pin', 'v1'] as $scenario) {
            $r = $this->runFixture($scenario);
            $this->assertFalse($r['snapshot']['destination_pin_valid']);
            $this->assertContains('destination_pin_missing_or_invalid', $r['snapshot']['reasons']);
            $this->assertNotContains('destination_address_changed_or_invalid', $r['snapshot']['reasons']);
            $this->assertFalse($r['snapshot']['coordinate_session_present']);
            $this->assertSame('v1' === $scenario ? 1 : 0, $r['snapshot']['destination_version']);
        }
    }

    #[Test]
    public function pin_is_bound_to_all_six_customer_address_fields(): void {
        foreach (['address_1', 'address_2', 'city', 'state', 'postcode', 'country'] as $field) {
            $r = $this->runFixture('stale', ['field' => $field]);
            $this->assertFalse($r['snapshot']['destination_address_matches'], $field);
        }
    }

    #[Test]
    public function zero_coordinates_are_valid_and_literal_null_is_not(): void {
        $r = $this->runFixture('zero');
        $this->assertTrue($r['snapshot']['destination_pin_valid']);
        $this->assertTrue($r['snapshot']['default_origin_coordinates_valid']);
        $this->assertFalse($this->runFixture('null')['snapshot']['default_origin_coordinates_valid']);
    }

    #[Test]
    public function missing_origin_and_invalid_contact_details_are_reported(): void {
        $this->assertContains('default_origin_missing', $this->runFixture('origin_missing')['snapshot']['reasons']);
        $this->assertContains('default_origin_name_invalid', $this->runFixture('name')['snapshot']['reasons']);
        $this->assertContains('default_origin_phone_invalid', $this->runFixture('phone')['snapshot']['reasons']);
        $this->assertTrue($this->runFixture('street')['snapshot']['default_origin_address_valid']);
    }

    #[Test]
    public function only_explicit_supported_instant_services_are_enabled(): void {
        $r = $this->runFixture('disabled');
        $this->assertSame([], $r['snapshot']['service_keys']);
        $this->assertContains('services_disabled', $r['snapshot']['reasons']);
        $this->assertSame(['gosend', 'grab_express'], array_keys($this->runFixture()['snapshot']['service_keys']));
    }

    #[Test]
    public function cod_and_missing_credentials_block_but_global_insurance_does_not(): void {
        foreach (['cod' => 'cod_unsupported', 'credentials' => 'account_unavailable'] as $scenario => $reason) {
            $this->assertContains($reason, $this->runFixture($scenario)['snapshot']['reasons']);
        }
    }

    #[Test]
    public function timezone_support_tracks_indonesian_regions(): void {
        $this->assertTrue($this->runFixture('timezone')['snapshot']['timezone_supported']);
        $this->assertSame('WIB', $this->runFixture('timezone')['snapshot']['instant_timezone']);
        $this->assertSame('WITA', $this->runFixture('timezone_lower')['snapshot']['instant_timezone']);
        $this->assertFalse($this->runFixture('timezone_invalid')['snapshot']['timezone_supported']);
        foreach (['makassar', 'jayapura'] as $scenario) {
            $this->assertTrue($this->runFixture($scenario)['snapshot']['timezone_supported']);
        }
    }

    #[Test]
    public function registration_requires_hook_and_callback_not_a_loaded_shipping_class(): void {
        foreach (['filter_missing'] as $scenario) {
            $this->assertContains('method_not_registered', $this->runFixture($scenario)['snapshot']['reasons']);
        }
    }

    #[Test]
    public function exceptions_use_fixed_error_reason_and_never_leak_contents(): void {
        $r = $this->runFixture('throw');
        $this->assertSame('error', $r['logs'][0]['level']);
        $this->assertSame(['diagnostics_unavailable'], $r['logs'][0]['context']['reasons']);
        $this->assertStringNotContainsString('private', json_encode($r));
    }

    #[Test]
    public function logs_are_private_safe_deduplicated_and_bounded_without_network(): void {
        $r = $this->runFixture();
        $this->assertCount(1, $r['logs']);
        $this->assertSame('kiriminaja_instant', $r['logs'][0]['source']);
        $this->assertFalse($r['logs'][0]['context']['backtrace']);
        $this->assertSame(0, $r['network']);
        $encoded = json_encode($r);
        foreach (['Private', 'private-api-key', '081234567890', '-6.2', '106.8'] as $private) {
            $this->assertStringNotContainsString($private, $encoded);
        }
        $this->assertCount(2, $this->runFixture('change')['logs']);
        $this->assertCount(8, $this->runFixture('bound')['logs']);
    }

    #[Test]
    public function only_physical_buyer_cart_checkout_or_store_api_requests_emit_reports(): void {
        foreach (['admin', 'virtual', 'unrelated', 'received', 'other_rest'] as $scenario) {
            $this->assertSame([], $this->runFixture($scenario)['logs'], $scenario);
        }
        foreach (['cart', 'rest'] as $scenario) {
            $this->assertCount(1, $this->runFixture($scenario)['logs'], $scenario);
        }
    }
    #[Test]
    public function global_insurance_is_reported_but_instant_opts_out(): void {
        $r = $this->runFixture('insurance')['snapshot'];
        $this->assertTrue($r['insurance_enabled']);
        $this->assertFalse($r['instant_insurance_enabled']);
        $this->assertFalse($r['instant_insurance_supported']);
        $this->assertTrue($r['ready']);
        $this->assertNotContains('insurance_unsupported', $r['reasons']);
        $this->assertTrue($this->runFixture('method_missing')['snapshot']['method_registered']);
    }

    #[Test]
    public function zone_disable_reason_requires_a_known_matching_zone(): void {
        $unknown = $this->runFixture('ready')['snapshot'];
        $this->assertFalse($unknown['matching_zone_known']);
        $this->assertNotContains('method_not_enabled_in_matching_zone', $unknown['reasons']);
        $enabled = $this->runFixture('zone_enabled');
        $this->assertTrue($enabled['snapshot']['matching_zone_known']);
        $this->assertTrue($enabled['snapshot']['method_zone_enabled']);
        $this->assertTrue($enabled['snapshot']['ready']);
        $disabled = $this->runFixture('zone_disabled');
        $this->assertContains('method_not_enabled_in_matching_zone', $disabled['snapshot']['reasons']);
        $this->assertSame(0, $disabled['network']);
    }

}
