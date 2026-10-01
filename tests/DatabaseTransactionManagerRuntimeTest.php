<?php

declare(strict_types=1);

use KiriminAjaOfficial\Infrastructure\WordPressDatabaseTransactionManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Contracts/DatabaseTransactionManagerInterface.php';
require_once PLUGIN_DIR . '/inc/Infrastructure/WordPressDatabaseTransactionManager.php';

final class DatabaseTransactionManagerRuntimeTest extends TestCase
{
    #[Test]
    public function it_issues_wordpress_transaction_queries_in_order(): void
    {
        $wpdb    = new DatabaseTransactionManagerWpdbFake();
        $manager = new WordPressDatabaseTransactionManager( $wpdb );

        $manager->begin();
        $manager->rollback();
        $manager->begin();
        $manager->commit();

        $this->assertSame(
            array( 'START TRANSACTION', 'ROLLBACK', 'START TRANSACTION', 'COMMIT' ),
            $wpdb->queries
        );
    }
}

final class DatabaseTransactionManagerWpdbFake
{
    public array $queries = array();

    public function query( string $query ): void
    {
        $this->queries[] = $query;
    }
}
