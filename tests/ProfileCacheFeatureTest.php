<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProfileCacheFeatureTest extends TestCase
{
    #[Test]
    public function api_service_caches_profile_result_for_one_minute(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/KiriminajaApiService.php');

        $this->assertStringContainsString(
            'KIRIOF_PROFILE_CACHE_KEY',
            $content,
            'Profile API service must define a dedicated cache key for profile data'
        );

        $this->assertStringContainsString(
            'KIRIOF_PROFILE_CACHE_TTL',
            $content,
            'Profile API service must define a dedicated cache TTL for profile data'
        );

        $this->assertMatchesRegularExpression(
            '/const\s+KIRIOF_PROFILE_CACHE_TTL\s*=\s*60\s*;/',
            $content,
            'Profile cache TTL must be 60 seconds to match the API throttle reset window'
        );

        $this->assertMatchesRegularExpression(
            '/get_transient\s*\(\s*self::KIRIOF_PROFILE_CACHE_KEY\s*\)/',
            $content,
            'getProfile must read the cached profile before calling the remote API'
        );

        $this->assertMatchesRegularExpression(
            '/set_transient\s*\(\s*self::KIRIOF_PROFILE_CACHE_KEY\s*,\s*\$profile\s*,\s*self::KIRIOF_PROFILE_CACHE_TTL\s*\)/',
            $content,
            'getProfile must cache successful profile responses for 60 seconds'
        );
    }

    #[Test]
    public function api_service_falls_back_to_cached_profile_when_api_is_throttled(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/KiriminajaApiService.php');

        $this->assertStringContainsString(
            'KIRIOF_PROFILE_LAST_SUCCESS_CACHE_KEY',
            $content,
            'Profile API service must keep a last-success profile cache for throttled responses'
        );

        $this->assertMatchesRegularExpression(
            '/get_transient\s*\(\s*self::KIRIOF_PROFILE_LAST_SUCCESS_CACHE_KEY\s*\)/',
            $content,
            'getProfile must read the last successful profile when the remote API fails'
        );

        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*false\s*!==\s*\$cachedProfile\s*\)/',
            $content,
            'getProfile must return cached profile data instead of an error when throttling happens'
        );

        $this->assertMatchesRegularExpression(
            '/set_transient\s*\(\s*self::KIRIOF_PROFILE_LAST_SUCCESS_CACHE_KEY\s*,\s*\$profile\s*,\s*DAY_IN_SECONDS\s*\)/',
            $content,
            'Last successful profile must live longer than the 60-second hot cache so throttled profile loads can still show account info'
        );
    }
}
