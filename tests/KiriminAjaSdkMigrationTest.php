<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KiriminAjaSdkMigrationTest extends TestCase
{
    #[Test]
    public function composer_requires_the_official_sdk(): void
    {
        $composer = json_decode(
            file_get_contents(PLUGIN_DIR . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('^2.1', $composer['require']['kiriminaja/kiriminaja-php'] ?? null);
    }

    #[Test]
    public function mitra_requests_use_the_sdk_transport(): void
    {
        $api = file_get_contents(PLUGIN_DIR . '/inc/Base/KiriminAjaApi.php');

        $this->assertStringContainsString('use KiriminAja\\Base\\Api\\Api;', $api);
        $this->assertStringContainsString('use KiriminAja\\Base\\Config\\KiriminAjaConfig;', $api);
        $this->assertStringContainsString('new Api()', $api);
        $this->assertStringNotContainsString('wp_remote_get', $api);
    }

    #[Test]
    public function repository_uses_sdk_facade_for_supported_operations(): void
    {
        $repository = file_get_contents(PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php');

        foreach (
            array(
                'KiriminAja::getDistrictByName(',
                'KiriminAja::setCallback(',
                'KiriminAja::getPayment(',
                'KiriminAja::getTracking(',
                'KiriminAja::getSchedules()',
                'KiriminAja::getCouriers()',
                'KiriminAja::getProvince()',
                'KiriminAja::getCity(',
                'KiriminAja::cancelShipment(',
                'KiriminAja::getCreditBalance()',
            ) as $sdk_call
		) {
            $this->assertStringContainsString($sdk_call, $repository);
        }
    }

    #[Test]
    public function region_fetch_script_does_not_use_curl_directly(): void
    {
        $script = file_get_contents(PLUGIN_DIR . '/scripts/fetch-regions.php');

        $this->assertStringContainsString('KiriminAja::getProvince()', $script);
        $this->assertStringContainsString('KiriminAja::getCity( $id )', $script);
        $this->assertStringNotContainsString('curl_init', $script);
        $this->assertStringNotContainsString('curl_exec', $script);
    }
}
