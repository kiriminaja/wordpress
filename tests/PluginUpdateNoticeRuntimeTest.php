<?php
use PHPUnit\Framework\TestCase;

final class PluginUpdateNoticeRuntimeTest extends TestCase {
    private function runScenario(array $scenario = array()): array {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/plugin-update-notice-runtime.php') . ' ' . escapeshellarg(json_encode($scenario, JSON_THROW_ON_ERROR)) . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_contract_uses_actual_version_canonical_page_and_exact_release(): void {
        $result = $this->runScenario(array('version' => 'v2.4.6', 'old_cache' => true));
        $update = $result['update'];
        $this->assertSame('New Update v2.4.6 Available', $update['label']);
        $this->assertSame('2.4.6', $update['version']);
        $this->assertSame('• fix(payments): reconcile QRIS webhook payment status (#290)\n\nFull Changelog: https://example.test/compare', str_replace("\n", '\n', $update['changelog']));
        $this->assertSame('Release changelog', $update['changelogLabel']);
        $this->assertSame('Release notes are not available yet. View the plugin page for details.', $update['changelogUnavailable']);
        $this->assertSame('https://wordpress.org/plugins/kiriminaja-official/', $update['primaryUrl']);
        $this->assertTrue($update['primaryExternal']);
        $this->assertSame('View Plugin Page', $update['primaryLabel']);
        $this->assertSame('Dismiss', $update['dismissLabel']);
        $this->assertSame('Close', $update['closeLabel']);
        $this->assertCount(1, $result['calls']);
        $this->assertTrue($result['calls'][0]['args']['fields']['sections']);
        $this->assertSame(array(array('key' => 'kiriof_wordpress_org_plugin_info_v2', 'ttl' => 7200)), $result['writes']);
    }

    public function test_allowed_capabilities_and_screens_gate_api_access(): void {
        foreach (array(array('caps' => array()), array('screen' => 'dashboard'), array('screen' => 'none')) as $scenario) {
            $result = $this->runScenario($scenario);
            $this->assertNull($result['update']);
            $this->assertSame(array(), $result['calls']);
        }
        foreach (array('plugins', 'plugins-network', 'woocommerce_page_kiriminaja') as $screen) {
            $this->assertNotNull($this->runScenario(array('screen' => $screen, 'caps' => array('manage_woocommerce')))['update']);
        }
    }

    public function test_dismiss_nonce_and_version_scope(): void {
        $valid = $this->runScenario(array('nonce' => 'valid'));
        $this->assertSame('2.4.6', $valid['dismissed']);
        $this->assertArrayHasKey('redirect', $valid);
        foreach (array(array('nonce' => 'invalid'), array('nonce' => 'valid', 'caps' => array())) as $scenario) {
            $this->assertSame('', $this->runScenario($scenario)['dismissed']);
        }
        $this->assertNull($this->runScenario(array('dismissed' => '2.4.6'))['update']);
        $this->assertNotNull($this->runScenario(array('dismissed' => '2.4.5'))['update']);
        $this->assertNull($this->runScenario(array('version' => '2.4.3'))['update']);
    }

    public function test_dismiss_url_roundtrips_raw_query_keys_and_saves_version(): void {
        $result = $this->runScenario();
        $url = $result['update']['dismissUrl'];
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(array(
            'kiriof_dismiss_update_notice' => '1',
            'kiriof_update_notice_version' => '2.4.6',
            '_wpnonce' => 'valid',
        ), $query);
        $this->assertStringNotContainsString('&amp;', $url);
        $this->assertStringContainsString('&amp;_wpnonce=valid', $result['rendered']);
        $dismissed = $this->runScenario(array('dismiss_url' => $url));
        $this->assertSame('2.4.6', $dismissed['dismissed']);
        $this->assertArrayHasKey('redirect', $dismissed);
    }

    public function test_cached_metadata_uses_the_same_identity_validation_as_fetch(): void {
        $valid = array('slug' => 'kiriminaja-official', 'version' => '2.4.6', 'download_link' => 'https://downloads.wordpress.org/plugin/kiriminaja-official.zip');
        $cached = $this->runScenario(array('cached_info' => $valid));
        $this->assertNotNull($cached['update']);
        $this->assertSame(array(), $cached['calls']);
        $this->assertSame(array(), $cached['writes']);
        foreach (array(
            array('version' => '9.0.0'),
            array_merge($valid, array('slug' => 'other')),
            array_merge($valid, array('download_link' => 'https://evil.example/plugin.zip')),
            array_merge($valid, array('download_link' => 'not a URL')),
        ) as $info) {
            $result = $this->runScenario(array('cached_info' => $info));
            $this->assertSame('2.4.6', $result['update']['version']);
            $this->assertCount(1, $result['calls']);
            $this->assertCount(1, $result['writes']);
        }
    }

    public function test_release_preserves_intro_and_subsections_until_next_version_heading(): void {
        foreach (array('', '<p>Introduction</p>') as $intro) {
            $html = '<h4>2.4.6</h4>' . $intro . '<h5>Fixes</h5><ul><li>Payment fix</li></ul><h5>Improvements</h5><p>Faster</p><h3>v2.4.5 - 2026-05-01</h3><p>OLD</p>';
            $expected = ($intro ? "Introduction\n\n" : '') . "Fixes\n\n• Payment fix\n\nImprovements\nFaster";
            $this->assertSame($expected, $this->runScenario(array('html' => $html))['update']['changelog']);
        }
        $html = '<h2>v2.4.6-beta.1 (2026-05-19)</h2><h5>Fixes</h5><p>Preview</p><h4>2.4.5+build.2</h4>OLD';
        $this->assertSame("Fixes\nPreview", $this->runScenario(array('version' => '2.4.6-beta.1', 'html' => $html))['update']['changelog']);
    }

    public function test_missing_malformed_or_oversized_notes_are_cached_without_showing_other_releases(): void {
        foreach (array('', '<h4>2.4.5</h4><p>OTHER</p>', '<h4>2.4.6</h4><p>Text</p><h4>broken', '<h4>2.4.6</h4><h5>Fixes</h4>Text', '<h4>2.4.6</h4><h5>Fixes<h6>Nested</h6></h5>', '<h4>2.4.6</h4>one<h4>2.4.6</h4>two', '<h4>2.4.6</h4><script>broken', str_repeat('x', 131073), '<h4>2.4.6</h4>' . str_repeat('x', 16385)) as $html) {
            $result = $this->runScenario(array('html' => $html));
            $this->assertSame('', $result['update']['changelog']);
            $this->assertCount(1, $result['calls']);
        }
    }

    public function test_all_heading_levels_select_only_exact_target_not_first_or_prefix_match(): void {
        foreach (range(1, 6) as $level) {
            $html = '<h4>2.4.60</h4><p>WRONG PREFIX</p><h' . $level . '>2.4.6</h' . $level . '><p>Target release</p><h4>2.4.5</h4><p>OLD</p>';
            $this->assertSame('Target release', $this->runScenario(array('html' => $html))['update']['changelog']);
        }
    }

    public function test_untrusted_markup_is_plain_text_and_version_header_accepts_date(): void {
        $result = $this->runScenario(array('html' => '<h2>v2.4.6 - 2026-05-19</h2><script>evil()</script><style>EVIL CSS</style><iframe>EVIL FRAME</iframe><ul><li onclick="evil()">Fix &amp; improve <a href="javascript:evil()">payments</a></li></ul><p>&lt;img src=x onerror=evil()&gt;Done</p><h2>2.4.5</h2>OTHER'));
        $text = $result['update']['changelog'];
        $this->assertStringContainsString('• Fix & improve payments', $text);
        $this->assertStringContainsString('Done', $text);
        foreach (array('<', '>', 'evil', 'EVIL', 'OTHER', 'onclick', 'javascript:') as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $text);
        }
    }

    public function test_failed_or_invalid_metadata_produces_no_update(): void {
        foreach (array(array('failure' => true), array('slug' => 'other'), array('download' => 'https://evil.example/plugin.zip')) as $scenario) {
            $result = $this->runScenario($scenario);
            $this->assertNull($result['update']);
            $this->assertSame(array(), $result['writes']);
        }
    }

    public function test_native_and_propagating_notices_have_only_primary_and_dismiss_links(): void {
        foreach (array(false, true) as $native) {
            $result = $this->runScenario(array('native' => $native));
            $this->assertSame($native, $result['update']['hasNativeUpdate']);
            $this->assertSame(2, substr_count($result['rendered'], '<a '));
            $this->assertStringContainsString($native ? 'Update Now' : 'View Plugin Page', $result['rendered']);
            $this->assertStringNotContainsString('Open Plugins screen', $result['rendered']);
            if ($native) {
                $this->assertSame('Review the release notes, then select Update Now to continue with the WordPress updater.', $result['update']['description']);
                $this->assertStringNotContainsString('target="_blank"', $result['rendered']);
                $this->assertStringNotContainsString('rel=', $result['rendered']);
            } else {
                $this->assertStringContainsString('has not received the update yet', $result['update']['description']);
                $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $result['rendered']);
            }
        }
    }

    public function test_native_action_has_exact_raw_core_url_and_nonce_without_exposing_package(): void {
        $package = 'https://vendor.example/private-package.zip';
        $result = $this->runScenario(array('native' => true, 'native_response' => array('new_version' => '2.4.6', 'package' => $package, 'requires_php' => '8.1', 'requires' => '6.0')));
        $update = $result['update'];
        $this->assertTrue($update['hasNativeUpdate']);
        $this->assertFalse($update['primaryExternal']);
        $this->assertSame('Update Now', $update['primaryLabel']);
        $this->assertSame('https://site.test/wp-admin/update.php', strtok($update['primaryUrl'], '?'));
        parse_str(parse_url($update['primaryUrl'], PHP_URL_QUERY), $query);
        $this->assertSame(array('action' => 'upgrade-plugin', 'plugin' => 'kiriminaja-official/kiriminaja-official.php', '_wpnonce' => 'upgrade-valid'), $query);
        $this->assertStringNotContainsString('&amp;', $update['primaryUrl']);
        $this->assertStringContainsString('&amp;_wpnonce=upgrade-valid', $result['rendered']);
        $this->assertContains('upgrade-plugin_kiriminaja-official/kiriminaja-official.php', $result['nonce_actions']);
        $this->assertSame(array('php' => '8.1', 'wp' => '6.0'), $result['compatibility_calls']);
        $this->assertStringNotContainsString($package, json_encode($update));
        $this->assertCount(1, $result['calls']);
        $this->assertCount(1, $result['writes']);
    }

    public function test_native_action_falls_back_for_permissions_package_version_and_compatibility(): void {
        $response = array('new_version' => '2.4.6', 'package' => 'https://downloads.wordpress.org/plugin/plugin.zip');
        $scenarios = array(
            array('caps' => array('manage_woocommerce')),
            array('native_response' => array('new_version' => '2.4.6')),
            array('php_compatible' => false),
            array('wp_compatible' => false),
            array('multisite' => true),
        );
        foreach (array('', ' ', false, array('url'), 123) as $package) {
            $scenarios[] = array('native_response' => array_merge($response, array('package' => $package)));
        }
        foreach (array('2.4.5', '2.4.7') as $version) {
            $scenarios[] = array('native_response' => array_merge($response, array('new_version' => $version)));
        }
        foreach ($scenarios as $scenario) {
            $result = $this->runScenario(array_merge(array('native' => true), $scenario));
            $update = $result['update'];
            $this->assertSame('https://wordpress.org/plugins/kiriminaja-official/', $update['primaryUrl']);
            $this->assertSame('View Plugin Page', $update['primaryLabel']);
            $this->assertTrue($update['primaryExternal']);
            $this->assertNotContains('upgrade-plugin_kiriminaja-official/kiriminaja-official.php', $result['nonce_actions']);
            $older = ($scenario['native_response']['new_version'] ?? '') === '2.4.5';
            $this->assertSame(!$older, $update['hasNativeUpdate']);
            $this->assertStringContainsString($older ? 'has not received the update yet' : 'then update from Plugins', $update['description']);
            $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $result['rendered']);
        }
    }

    public function test_network_admin_routes_native_action_to_network_updater(): void {
        $result = $this->runScenario(array('native' => true, 'multisite' => true, 'network_admin' => true));
        $this->assertFalse($result['update']['primaryExternal']);
        $this->assertSame('https://site.test/wp-admin/network/update.php', strtok($result['update']['primaryUrl'], '?'));
        $this->assertContains('network_admin_notices', $result['hooks']);
        $this->assertStringContainsString('Update Now', $result['rendered']);
        $this->assertStringContainsString('wp-admin/network/update.php?', $result['rendered']);
    }

    public function test_optional_compatibility_helpers_and_missing_requirements(): void {
        $result = $this->runScenario(array('native' => true));
        $this->assertSame(array('php' => null, 'wp' => null), $result['compatibility_calls']);
        $this->assertSame('Update Now', $result['update']['primaryLabel']);
        $result = $this->runScenario(array('native' => true, 'compatibility_helpers_unavailable' => true));
        $this->assertSame(array(), $result['compatibility_calls']);
        $this->assertSame('Update Now', $result['update']['primaryLabel']);
    }
}
