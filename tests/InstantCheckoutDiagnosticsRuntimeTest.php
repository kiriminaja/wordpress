<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantCheckoutDiagnosticsRuntimeTest extends TestCase {
    #[Test]
    public function diagnostics_remain_available_with_the_normalized_log_source(): void {
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
    public function composition_is_lazy_without_automatic_readiness_hooks(): void {
        $r = $this->runFixture();
        $this->assertSame([0, 0], $r['lazy_counts']);
        $this->assertSame([], $r['hooks']);
    }

    #[Test]
    public function incomplete_order_integration_never_claims_readiness(): void {
        $r = $this->runFixture();
        $this->assertFalse($r['snapshot']['ready']);
        $this->assertSame(['checkout_integration_not_ready'], $r['snapshot']['reasons']);
        $this->assertSame([], $r['logs']);
        $this->assertFalse($r['snapshot']['live_quote_checked']);
        $this->assertSame('configured_store_readiness', $r['snapshot']['code']);
    }

    #[Test]
    public function complete_local_configuration_is_silent_and_not_live_availability(): void {
        $r = $this->runFixture('ready');
        $this->assertTrue($r['snapshot']['ready']);
        $this->assertSame([], $r['logs']);
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
        $this->assertNull($r['snapshot']);
        $this->assertSame([], $r['logs']);
        $this->assertStringNotContainsString('private', json_encode($r));
    }

    #[Test]
    public function snapshots_are_private_safe_without_logging_or_network(): void {
        foreach (['', 'ready', 'no_pin', 'disabled', 'cod', 'change', 'bound'] as $scenario) {
            $r = $this->runFixture($scenario);
            $this->assertSame([], $r['logs']);
            $this->assertSame(0, $r['network']);
            foreach (['Private', 'private-api-key', '081234567890', '-6.2', '106.8'] as $private) {
                $this->assertStringNotContainsString($private, json_encode($r));
            }
        }
    }

    #[Test]
    public function no_checkout_lifecycle_emits_readiness_reports(): void {
        foreach (['admin', 'virtual', 'unrelated', 'received', 'other_rest'] as $scenario) {
            $this->assertSame([], $this->runFixture($scenario)['logs'], $scenario);
        }
        foreach (['cart', 'rest'] as $scenario) {
            $this->assertSame([], $this->runFixture($scenario)['logs'], $scenario);
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
    public function matching_zone_details_distinguish_zero_disabled_missing_and_registration(): void {
        foreach ([0, 1] as $zoneId) {
            $enabled = $this->runFixture('zone_enabled', ['zone_id' => $zoneId]);
            $this->assertSame($zoneId, $enabled['snapshot']['matching_zone_id']);
            $this->assertSame([['method_id' => 'kiriminaja-instant', 'instance_id' => 73, 'enabled' => true, 'stored_enabled' => true, 'enabled_settings_conflict' => false]], $enabled['snapshot']['matching_zone_methods']);
            $this->assertTrue($enabled['snapshot']['ready']);
            $disabled = $this->runFixture('zone_disabled', ['zone_id' => $zoneId]);
            $this->assertSame($zoneId, $disabled['snapshot']['matching_zone_id']);
            $this->assertFalse($disabled['snapshot']['matching_zone_methods'][0]['enabled']);
            $this->assertContains('method_not_enabled_in_matching_zone', $disabled['snapshot']['reasons']);
            $this->assertNotContains('method_not_registered', $disabled['snapshot']['reasons']);
            $missing = $this->runFixture('zone_missing', ['zone_id' => $zoneId]);
            $this->assertSame('kiriminaja-official', $missing['snapshot']['matching_zone_methods'][0]['method_id']);
            $this->assertContains('method_not_enabled_in_matching_zone', $missing['snapshot']['reasons']);
            $unregistered = $this->runFixture('zone_unregistered', ['zone_id' => $zoneId]);
            $this->assertContains('method_not_registered', $unregistered['snapshot']['reasons']);
            $this->assertNotContains('method_not_enabled_in_matching_zone', $unregistered['snapshot']['reasons']);
            foreach ([$enabled, $disabled, $missing, $unregistered] as $result) {
                $this->assertSame(0, $result['network']);
                $this->assertStringNotContainsString('Private merchant title', json_encode($result));
            }
        }
        $conflict = $this->runFixture('zone_conflict')['snapshot'];
        $this->assertTrue($conflict['matching_zone_methods'][0]['stored_enabled']);
        $this->assertFalse($conflict['matching_zone_methods'][0]['enabled']);
        $this->assertTrue($conflict['matching_zone_methods'][0]['enabled_settings_conflict']);
        $this->assertFalse($conflict['method_zone_enabled']);
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
