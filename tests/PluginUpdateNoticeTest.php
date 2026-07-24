<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PluginUpdateNoticeTest extends TestCase
{
    #[Test]
    public function update_notice_service_uses_wordpress_org_metadata_cache(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/PluginUpdateNoticeService.php');
        $init = file_get_contents(PLUGIN_DIR . '/inc/Init.php');

        $this->assertStringContainsString('Services\\PluginUpdateNoticeService::class', $init);
        $this->assertStringContainsString("private const CACHE_TTL = 2 * HOUR_IN_SECONDS;", $content);
        $this->assertStringContainsString("get_site_transient( self::CACHE_KEY )", $content);
        $this->assertStringContainsString('set_site_transient( self::CACHE_KEY, $info, self::CACHE_TTL )', $content);
        $this->assertStringContainsString("plugins_api(", $content);
        $this->assertStringContainsString("'plugin_information'", $content);
        $this->assertStringContainsString("'downloads.wordpress.org'", $content);
        $this->assertStringNotContainsString('wp_remote_get', $content);
        $this->assertStringNotContainsString('wp_remote_post', $content);
    }

    #[Test]
    public function update_notice_is_scoped_dismissible_and_manual_install_copy_is_readable(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/PluginUpdateNoticeService.php');

        $this->assertStringContainsString("current_user_can( 'manage_woocommerce' ) || current_user_can( 'update_plugins' )", $content);
        $this->assertStringContainsString("'plugins' === \$screen_id", $content);
        $this->assertStringContainsString("false !== strpos( \$screen_id, 'kiriminaja' )", $content);
        $this->assertStringContainsString('update_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, $version )', $content);
        $this->assertStringContainsString("KiriminAja Official %s is available.", $content);
        $this->assertStringContainsString("If you do not see this version in Plugins, open the WordPress.org plugin page and install it manually.", $content);
        $this->assertStringContainsString("WordPress.org can take up to 24 hours to publish new plugin releases through the update system.", $content);
        $this->assertStringContainsString("View on WordPress.org", $content);
        $this->assertStringContainsString("Open Plugins screen", $content);
    }
}
