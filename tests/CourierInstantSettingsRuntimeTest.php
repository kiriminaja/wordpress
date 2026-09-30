<?php

use PHPUnit\Framework\TestCase;

/** Real PHP courier settings flow, isolated from other WordPress test stubs. */
final class CourierInstantSettingsRuntimeTest extends TestCase {
    private function runScenario( string $scenario ): void {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/courier-instant-settings-runtime.php' ) . ' ' . escapeshellarg( $scenario ) . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $this->assertSame( 'ok', implode( "\n", $output ) );
    }

    public function test_live_discovery_uses_separate_caches_and_keeps_express_default(): void { $this->runScenario( 'live' ); }
    public function test_warm_discovery_filters_unsupported_rows_without_live_requests(): void { $this->runScenario( 'warm' ); }
    public function test_fallback_discovery_uses_only_matching_cache_family(): void { $this->runScenario( 'fallback' ); }
    public function test_missing_instant_children_never_invent_services_or_wildcards(): void { $this->runScenario( 'missing' ); }
    public function test_controller_validates_and_roundtrips_explicit_services(): void { $this->runScenario( 'controller' ); }
    public function test_legacy_csv_does_not_opt_in_instant(): void { $this->runScenario( 'legacy' ); }
    public function test_cache_invalidation_clears_both_families_and_preserves_optional_fallbacks(): void { $this->runScenario( 'invalidation' ); }
    public function test_get_requires_permission(): void { $this->runScenario( 'get-permission' ); }
    public function test_save_requires_permission(): void { $this->runScenario( 'save-permission' ); }
    public function test_get_requires_valid_nonce(): void { $this->runScenario( 'get-nonce' ); }
    public function test_save_requires_valid_nonce(): void { $this->runScenario( 'save-nonce' ); }
    public function test_get_requires_nonce_present(): void { $this->runScenario( 'get-missing-nonce' ); }
    public function test_save_requires_nonce_present(): void { $this->runScenario( 'save-missing-nonce' ); }
}
