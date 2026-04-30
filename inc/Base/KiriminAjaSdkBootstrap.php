<?php
namespace KiriminAjaOfficial\Base;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAja\Base\Config\Cache\Mode;
use KiriminAja\Base\Config\KiriminAjaConfig;

class KiriminAjaSdkBootstrap
{
    private static bool $initialized = false;

    /**
     * Initialize the KiriminAja SDK with stored credentials.
     * Safe to call multiple times — only runs once.
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        KiriminAjaConfig::setMode(Mode::Production);

        $apiKey = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('api_key')->value ?? '';
        if (!empty($apiKey)) {
            KiriminAjaConfig::setApiTokenKey($apiKey);
        }

        KiriminAjaConfig::disableCache();

        self::$initialized = true;
    }

    /**
     * Re-initialize with a fresh API key (e.g. after processSetupKey).
     */
    public static function refreshApiKey(string $apiKey): void
    {
        KiriminAjaConfig::setApiTokenKey($apiKey);
    }

    /**
     * Reset initialization flag (for testing purposes).
     */
    public static function reset(): void
    {
        self::$initialized = false;
    }
}
