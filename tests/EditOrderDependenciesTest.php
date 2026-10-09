<?php

declare(strict_types=1);

use KiriminAjaOfficial\Controllers\EditOrderController;
use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EditOrderDependenciesTest extends TestCase
{
    #[Test]
    public function constructor_accepts_required_injected_instances(): void
    {
        if ( ! defined( 'ABSPATH' ) ) {
            define( 'ABSPATH', PLUGIN_DIR . '/' );
        }
        if ( ! defined( 'KIRIOF_NONCE' ) ) {
            define( 'KIRIOF_NONCE', 'test-nonce' );
        }

        require_once PLUGIN_DIR . '/vendor/autoload.php';

        $transactionRepository = ( new ReflectionClass( TransactionRepository::class ) )->newInstanceWithoutConstructor();
        $settingRepository     = ( new ReflectionClass( SettingRepository::class ) )->newInstanceWithoutConstructor();
        $apiRepository         = ( new ReflectionClass( KiriminajaApiRepository::class ) )->newInstanceWithoutConstructor();
        $controller            = new EditOrderController( $transactionRepository, $settingRepository, $apiRepository );
        $reflection            = new ReflectionClass( $controller );
        $this->assertSame( $transactionRepository, $reflection->getProperty( 'transactionRepository' )->getValue( $controller ) );
        $this->assertSame( $settingRepository, $reflection->getProperty( 'settingRepository' )->getValue( $controller ) );
        $this->assertSame( $apiRepository, $reflection->getProperty( 'kiriminajaApiRepository' )->getValue( $controller ) );
    }
}
