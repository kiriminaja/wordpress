<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoggingFeatureTest extends TestCase
{
    #[Test]
    public function logger_utility_exists_and_uses_woocommerce_logger_shortcuts(): void
    {
        $path = PLUGIN_DIR . '/inc/Utils/Logger.php';
        $this->assertFileExists($path, 'Logger utility file must exist');

        $content = file_get_contents($path);
        $this->assertStringContainsString('class Logger', $content);
        $this->assertStringContainsString('wc_get_logger()', $content);
        $this->assertStringContainsString("'kiriof_logger_threshold'", $content);
        $this->assertStringNotContainsString('->log(', $content, 'Logger utility should use level shortcut methods instead of WC_Logger::log()');
    }

    #[Test]
    public function main_plugin_bootstraps_logging_helper_and_filters(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');

        $this->assertStringContainsString('function kiriof_log', $content);
        $this->assertStringContainsString("woocommerce_log_directory", $content);
        $this->assertStringContainsString("woocommerce_logger_log_message", $content);
        $this->assertStringContainsString("kiriof_logger_suppressed_messages", $content);
    }

    #[Test]
    public function legacy_error_log_calls_are_replaced_in_core_logging_surfaces(): void
    {
        $files = [
            PLUGIN_DIR . '/inc/Base/BaseInit.php',
            PLUGIN_DIR . '/inc/Base/KiriminAjaApi.php',
            PLUGIN_DIR . '/inc/Repositories/CodFeeApiRepository.php',
            PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php',
            PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php',
        ];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('error_log(', $content, basename($file) . ' should not use raw error_log() anymore');
            $this->assertStringNotContainsString('WPMonolog', $content, basename($file) . ' should not depend on WPMonolog anymore');
        }
    }

    #[Test]
    public function settings_and_webhook_flows_use_structured_logging(): void
    {
        $settingsContent = file_get_contents(PLUGIN_DIR . '/inc/Services/SettingService.php');
        $callbackControllerContent = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CallbackController.php');
        $callbackServiceContent = file_get_contents(PLUGIN_DIR . '/inc/Services/CallbackHandlerService.php');
        $regionCacheContent = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountRegionCacheService.php');

        $this->assertStringContainsString('kiriof_log(', $settingsContent);
        $this->assertStringContainsString('kiriof_log(', $callbackControllerContent);
        $this->assertStringContainsString('logWebhookEvent(', $callbackServiceContent);
        $this->assertStringContainsString("'kiriminaja_import'", $regionCacheContent);
        $this->assertStringNotContainsString('update_option( \'kiriof_processed_packages\'', $callbackServiceContent, 'Webhook debug state should be logged, not persisted to options');
    }

    #[Test]
    public function technical_section_downloads_only_plugin_logs_with_consent_disclaimer(): void
    {
        $controllerContent = file_get_contents(PLUGIN_DIR . '/inc/Controllers/SettingController.php');
        $indexContent = file_get_contents(PLUGIN_DIR . '/templates/setting/setuped/index.php');
        $technicalContent = file_get_contents(PLUGIN_DIR . '/templates/setting/setuped/section-technical.php');

        $this->assertFileDoesNotExist(PLUGIN_DIR . '/templates/setting/setuped/section-cache.php');
        $this->assertStringContainsString("'cache' === \$kiriof_section", $indexContent);
        $this->assertStringContainsString("section=technical", $indexContent);
        $this->assertStringContainsString('admin_post_kiriof_download_plugin_logs', $controllerContent);
        $this->assertStringContainsString('getPluginLogSources', $controllerContent);
        $this->assertStringContainsString("'kiriminaja_request_pickup'", $controllerContent);
        $this->assertStringContainsString("'kiriminaja_webhook'", $controllerContent);
        $this->assertStringContainsString("'shipping_discount_coupon'", $controllerContent);
        $this->assertStringContainsString('redactLogContent', $controllerContent);
        $this->assertStringContainsString('kiriof_couriers_list_v2', $technicalContent);
        $this->assertStringContainsString('kiriof-cache-updated', $technicalContent);
        $this->assertStringContainsString('kiriof-cache-valid-until', $technicalContent);
        $this->assertStringContainsString('kiriof-couriers-cache-updated', $technicalContent);
        $this->assertStringContainsString('kiriof-couriers-cache-valid-until', $technicalContent);
        $this->assertStringContainsString('Download Log', $technicalContent);
        $this->assertStringContainsString('KiriminAja does not collect this diagnostic data automatically', $technicalContent);
    }
}
