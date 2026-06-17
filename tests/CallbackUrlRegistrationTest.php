<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CallbackUrlRegistrationTest extends TestCase
{
    #[Test]
    public function setup_registers_feed_query_callback_url(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/SettingService.php');
        $setupStart = strpos($content, 'function processingSetupKey');
        $this->assertNotFalse($setupStart, 'Setup-key processing method must exist');
        $setupBody = substr($content, $setupStart, 1400);

        $this->assertStringContainsString(
            "add_query_arg( 'feed', 'kiriminaja-callback', home_url( '/' ) )",
            $content,
            'Default callback URL should use the WordPress feed query route'
        );

        $this->assertStringContainsString(
            "'callback_url' => \$this->getDefaultCallbackUrl()",
            $setupBody,
            'Setup-key registration must send the canonical feed query callback URL'
        );

        $this->assertStringNotContainsString(
            "home_url() . '/kiriminaja-callback'",
            $setupBody,
            'Setup-key registration must not send the pretty permalink callback URL'
        );
    }
}
