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
    public function controller_reuses_its_injected_repositories(): void
    {
        $controllerSource = file_get_contents( PLUGIN_DIR . '/inc/Controllers/EditOrderController.php' );

        $this->assertIsString( $controllerSource );
        $this->assertSame( 0, substr_count( $controllerSource, 'new TransactionRepository()' ) );
        $this->assertSame( 0, substr_count( $controllerSource, 'new SettingRepository()' ) );
        $this->assertSame( 0, substr_count( $controllerSource, 'new KiriminajaApiRepository()' ) );
        $this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\TransactionRepository', $controllerSource );
        $this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\SettingRepository', $controllerSource );
        $this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\KiriminajaApiRepository', $controllerSource );
    }

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
        $constructor           = $reflection->getConstructor();

        $this->assertNotNull( $constructor );
        $this->assertSame( 3, $constructor->getNumberOfRequiredParameters() );

        $this->assertSame( $transactionRepository, $reflection->getProperty( 'transactionRepository' )->getValue( $controller ) );
        $this->assertSame( $settingRepository, $reflection->getProperty( 'settingRepository' )->getValue( $controller ) );
        $this->assertSame( $apiRepository, $reflection->getProperty( 'kiriminajaApiRepository' )->getValue( $controller ) );
    }
}
