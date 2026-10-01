<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Subprocesses isolate WordPress/Woo test doubles from the shared suite. */
final class InstantShippingZoneProvisioningRuntimeTest extends TestCase {
    private function runScenario(string $scenario): void {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-shipping-zone-provisioning-runtime.php') . ' ' . escapeshellarg($scenario) . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        $this->assertSame('ok', implode("\n", $output));
    }

    public function test_hooks_use_woo_argument_order_and_ignore_disabled_or_unrelated_methods(): void { $this->runScenario('hooks'); }
    public function test_enabled_express_zones_and_existing_rest_of_world_are_provisioned_once_without_mutation(): void { $this->runScenario('sync'); }
    public function test_only_explicit_enabled_instant_policy_provisions_without_network(): void { $this->runScenario('policy'); }
    public function test_unauthorized_requests_never_write_methods_or_locks(): void { $this->runScenario('unauthorized'); }
    public function test_unregistered_instant_method_never_acquires_locks(): void { $this->runScenario('unregistered'); }
    public function test_false_add_releases_lock_and_allows_retry(): void { $this->runScenario('false'); }
    public function test_exception_releases_lock_allows_retry_and_logs_only_fixed_private_safe_fields(): void { $this->runScenario('throw'); }
    public function test_disabled_woo_default_is_reported_not_silently_enabled(): void { $this->runScenario('disabled_default'); }
    public function test_live_lock_prevents_duplicate_work(): void { $this->runScenario('busy'); }
    public function test_expired_lock_is_atomically_deleted_and_retry_succeeds(): void { $this->runScenario('expired'); }
    public function test_expired_owner_cannot_delete_or_take_over_a_successor(): void { $this->runScenario('stale_race'); }
    public function test_finally_cleanup_cannot_delete_a_successor_lock(): void { $this->runScenario('successor'); }
    public function test_zone_is_rechecked_after_lock_and_disabled_companion_is_preserved(): void { $this->runScenario('recheck'); }
    public function test_authorized_courier_save_dispatches_sync_after_commit_before_response(): void { $this->runScenario('controller'); }
    public function test_unauthorized_courier_save_cannot_dispatch_sync_or_write(): void { $this->runScenario('controller-denied'); }
    public function test_invalid_courier_save_does_not_dispatch_sync_or_write(): void { $this->runScenario('controller-invalid'); }
}
