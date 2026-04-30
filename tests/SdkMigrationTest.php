<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates the SDK migration in KiriminajaApiRepository:
 * - All SDK-backed methods return the legacy array format ['status' => bool, 'data' => object]
 * - The toLegacy bridge preserves backward-compatible data shapes
 * - ShippingPriceData model is built correctly from array payloads
 * - RequestPickupData model is built correctly from array payloads
 * - Legacy methods (processSetupKey, getPrintAwb) remain unchanged
 * - SDK bootstrap initializer exists and has required methods
 */
final class SdkMigrationTest extends TestCase
{
    private static string $repoFile;
    private static string $repoContent;
    private static string $bootstrapFile;
    private static string $bootstrapContent;
    private static string $mainPluginFile;
    private static string $mainPluginContent;

    public static function setUpBeforeClass(): void
    {
        self::$repoFile = PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php';
        self::$repoContent = file_get_contents(self::$repoFile);

        self::$bootstrapFile = PLUGIN_DIR . '/inc/Base/KiriminAjaSdkBootstrap.php';
        self::$bootstrapContent = file_get_contents(self::$bootstrapFile);

        self::$mainPluginFile = PLUGIN_DIR . '/kiriminaja.php';
        self::$mainPluginContent = file_get_contents(self::$mainPluginFile);
    }

    // ─── Bootstrap ───────────────────────────────────────────────────

    #[Test]
    public function bootstrap_file_exists(): void
    {
        $this->assertFileExists(self::$bootstrapFile);
    }

    #[Test]
    public function bootstrap_has_init_method(): void
    {
        $this->assertStringContainsString(
            'public static function init()',
            self::$bootstrapContent,
            'KiriminAjaSdkBootstrap must have a public static init() method'
        );
    }

    #[Test]
    public function bootstrap_has_refresh_api_key_method(): void
    {
        $this->assertStringContainsString(
            'public static function refreshApiKey(string $apiKey)',
            self::$bootstrapContent,
            'KiriminAjaSdkBootstrap must have a refreshApiKey() method'
        );
    }

    #[Test]
    public function bootstrap_sets_production_mode(): void
    {
        $this->assertStringContainsString(
            'Mode::Production',
            self::$bootstrapContent,
            'Bootstrap must set Mode::Production'
        );
    }

    #[Test]
    public function bootstrap_disables_cache(): void
    {
        $this->assertStringContainsString(
            'disableCache',
            self::$bootstrapContent,
            'Bootstrap must disable file cache for WordPress compatibility'
        );
    }

    #[Test]
    public function bootstrap_reads_api_key_from_settings(): void
    {
        $this->assertStringContainsString(
            "getSettingByKey('api_key')",
            self::$bootstrapContent,
            'Bootstrap must read API key from SettingRepository'
        );
    }

    #[Test]
    public function main_plugin_file_calls_bootstrap(): void
    {
        $this->assertStringContainsString(
            'KiriminAjaSdkBootstrap::init()',
            self::$mainPluginContent,
            'Main plugin file must call KiriminAjaSdkBootstrap::init()'
        );
    }

    // ─── Repository uses SDK ─────────────────────────────────────────

    #[Test]
    public function repository_imports_sdk_classes(): void
    {
        $required = [
            'use KiriminAja\Services\KiriminAja;',
            'use KiriminAja\Models\ShippingPriceData;',
            'use KiriminAja\Models\RequestPickupData;',
            'use KiriminAja\Models\PackageData;',
            'use KiriminAja\Responses\ServiceResponse;',
        ];

        foreach ($required as $import) {
            $this->assertStringContainsString(
                $import,
                self::$repoContent,
                "Repository must import: {$import}"
            );
        }
    }

    #[Test]
    public function repository_has_toLegacy_bridge(): void
    {
        $this->assertStringContainsString(
            'private function toLegacy(ServiceResponse $response): array',
            self::$repoContent,
            'Repository must have a toLegacy() bridge method'
        );
    }

    // ─── SDK method delegation ───────────────────────────────────────

    #[Test]
    public function sub_district_search_uses_sdk(): void
    {
        $this->assertStringContainsString(
            'KiriminAja::getDistrictByName($search)',
            self::$repoContent,
            'sub_district_search must delegate to KiriminAja::getDistrictByName()'
        );
    }

    #[Test]
    public function setCallback_uses_sdk(): void
    {
        $this->assertStringContainsString(
            'KiriminAja::setCallback($callbackUrl)',
            self::$repoContent,
            'setCallback must delegate to KiriminAja::setCallback()'
        );
    }

    #[Test]
    public function getPayment_uses_sdk(): void
    {
        $this->assertStringContainsString(
            "KiriminAja::getPayment(\$payload['payment_id'])",
            self::$repoContent,
            'getPayment must delegate to KiriminAja::getPayment()'
        );
    }

    #[Test]
    public function getTracking_uses_sdk(): void
    {
        $this->assertStringContainsString(
            "KiriminAja::getTracking(\$payload['order_id'])",
            self::$repoContent,
            'getTracking must delegate to KiriminAja::getTracking()'
        );
    }

    #[Test]
    public function getPricing_uses_sdk(): void
    {
        $this->assertStringContainsString(
            'KiriminAja::getPrice($data)',
            self::$repoContent,
            'getPricing must delegate to KiriminAja::getPrice()'
        );
    }

    #[Test]
    public function getPricing_builds_ShippingPriceData(): void
    {
        $this->assertStringContainsString(
            'new ShippingPriceData()',
            self::$repoContent,
            'getPricing must create a ShippingPriceData model'
        );
    }

    #[Test]
    public function getPricing_maps_origin_field(): void
    {
        $this->assertStringContainsString(
            "\$data->origin      = (int) \$payload['subdistrict_origin']",
            self::$repoContent,
            'getPricing must map subdistrict_origin to origin'
        );
    }

    #[Test]
    public function getPricing_maps_destination_field(): void
    {
        $this->assertStringContainsString(
            "\$data->destination  = (int) \$payload['subdistrict_destination']",
            self::$repoContent,
            'getPricing must map subdistrict_destination to destination'
        );
    }

    #[Test]
    public function getRequestPickupSchedule_uses_sdk(): void
    {
        $this->assertStringContainsString(
            'KiriminAja::getSchedules()',
            self::$repoContent,
            'getRequestPickupSchedule must delegate to KiriminAja::getSchedules()'
        );
    }

    #[Test]
    public function sendPickupRequest_uses_sdk(): void
    {
        $this->assertStringContainsString(
            'KiriminAja::requestPickup($data)',
            self::$repoContent,
            'sendPickupRequest must delegate to KiriminAja::requestPickup()'
        );
    }

    #[Test]
    public function sendPickupRequest_builds_RequestPickupData(): void
    {
        $this->assertStringContainsString(
            'new RequestPickupData()',
            self::$repoContent,
            'sendPickupRequest must create a RequestPickupData model'
        );
    }

    #[Test]
    public function sendPickupRequest_builds_PackageData(): void
    {
        $this->assertStringContainsString(
            'new PackageData()',
            self::$repoContent,
            'sendPickupRequest must create PackageData models for each package'
        );
    }

    #[Test]
    public function sendPickupRequest_sets_platform_name(): void
    {
        $this->assertStringContainsString(
            "\$data->platform_name = 'wordpress'",
            self::$repoContent,
            'sendPickupRequest must set platform_name to wordpress'
        );
    }

    #[Test]
    public function get_couriers_uses_sdk(): void
    {
        $this->assertStringContainsString(
            'KiriminAja::getCouriers()',
            self::$repoContent,
            'get_couriers must delegate to KiriminAja::getCouriers()'
        );
    }

    // ─── Legacy methods preserved ────────────────────────────────────

    #[Test]
    public function processSetupKey_still_uses_legacy_http(): void
    {
        $this->assertStringContainsString(
            "this->post('/api/service/api-request/integrate'",
            self::$repoContent,
            'processSetupKey must still use the legacy HTTP client (no SDK equivalent)'
        );
    }

    #[Test]
    public function getPrintAwb_still_uses_legacy_http(): void
    {
        $this->assertStringContainsString(
            "this->post('/api/mitra/v6.1/awb/print'",
            self::$repoContent,
            'getPrintAwb must still use the legacy HTTP client (no SDK equivalent)'
        );
    }

    #[Test]
    public function repository_still_extends_legacy_api(): void
    {
        $this->assertStringContainsString(
            'extends KiriminAjaApi',
            self::$repoContent,
            'Repository must still extend KiriminAjaApi for legacy methods'
        );
    }

    // ─── toLegacy bridge correctness ─────────────────────────────────

    #[Test]
    public function toLegacy_converts_arrays_to_objects(): void
    {
        $this->assertStringContainsString(
            'json_decode(wp_json_encode($data))',
            self::$repoContent,
            'toLegacy must convert arrays to stdClass via JSON round-trip'
        );
    }

    #[Test]
    public function toLegacy_preserves_status_field(): void
    {
        $this->assertStringContainsString(
            '$wrapper->status = $response->status',
            self::$repoContent,
            'toLegacy wrapper must include a status field for backward compat'
        );
    }

    #[Test]
    public function toLegacy_preserves_text_field(): void
    {
        $this->assertStringContainsString(
            '$wrapper->text   = $response->message',
            self::$repoContent,
            'toLegacy wrapper must map SDK message to text field for backward compat'
        );
    }

    // ─── Caller files still reference repository ─────────────────────

    #[Test]
    public function all_callers_use_repository_not_sdk_directly(): void
    {
        $callerFiles = [
            'inc/Services/KiriminajaApiService.php',
            'inc/Services/SettingService.php',
            'inc/Services/KiriminAjaTrackingService.php',
            'inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php',
            'inc/Services/TransactionProcessServices/GetRequestPickupScheduleService.php',
            'inc/Services/ShippingProcessServices/GetShippingProcessPayment.php',
            'inc/Controllers/EditOrderController.php',
            'inc/Controllers/ShippingProcessController.php',
            'inc/Services/CheckoutServices/CheckoutCalculationService.php',
            'inc/Services/CheckoutServices/OngkirPricingService.php',
        ];

        foreach ($callerFiles as $relativePath) {
            $filePath = PLUGIN_DIR . '/' . $relativePath;
            if (!file_exists($filePath)) {
                continue;
            }
            $content = file_get_contents($filePath);

            $this->assertStringNotContainsString(
                'KiriminAja::',
                $content,
                "{$relativePath} must not call SDK directly — use KiriminajaApiRepository instead"
            );
        }
    }

    // ─── No removed SDK methods in old const ─────────────────────────

    #[Test]
    public function no_default_pickup_option_const(): void
    {
        $this->assertStringNotContainsString(
            'DEFAULT_PICKUP_OPTION',
            self::$repoContent,
            'Removed constant DEFAULT_PICKUP_OPTION should not be in repository (SDK handles defaults)'
        );
    }

    // ─── Verify all SDK-backed methods go through toLegacy ───────────

    #[Test]
    public function all_sdk_methods_use_toLegacy(): void
    {
        $sdkMethods = [
            'sub_district_search',
            'setCallback',
            'getPayment',
            'getTracking',
            'getPricing',
            'getRequestPickupSchedule',
            'sendPickupRequest',
            'get_couriers',
        ];

        // Count toLegacy calls — should be at least as many as SDK methods
        $toLegacyCount = substr_count(self::$repoContent, '$this->toLegacy(');
        $this->assertGreaterThanOrEqual(
            count($sdkMethods),
            $toLegacyCount,
            'Each SDK-backed method must route through toLegacy()'
        );
    }
}
