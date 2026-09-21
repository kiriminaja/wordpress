<?php

use KiriminAjaOfficial\Contracts\TransactionPrintRepositoryInterface;
use KiriminAjaOfficial\Controllers\ShippingProcessController;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) {
        return '2026-09-21 16:00:00';
    }
}

require_once PLUGIN_DIR . '/inc/Contracts/TransactionPrintRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Repositories/TransactionRepository.php';
require_once PLUGIN_DIR . '/inc/Controllers/ShippingProcessController.php';

final class TransactionPrintRepositoryRuntimeTest extends TestCase
{
    private $previous_wpdb;

    protected function setUp(): void
    {
        global $wpdb;
        $this->previous_wpdb = $wpdb ?? null;
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->previous_wpdb;
    }

    #[Test]
    public function repository_marks_requested_transactions_as_printed(): void
    {
        global $wpdb;
        $wpdb = new TransactionPrintWpdbFake();

        $result = ( new TransactionRepository() )->markPrintedByOrderIds( array( 'KA-1', 'KA-2' ) );

        $this->assertTrue( $result );
        $this->assertStringContainsString( 'WHERE order_id IN (%s,%s)', $wpdb->prepared_query );
        $this->assertSame(
            array( 1, '2026-09-21 16:00:00', 'KA-1', 'KA-2' ),
            $wpdb->prepared_values
        );
        $this->assertSame( $wpdb->prepared_query, $wpdb->executed_query );
    }

    #[Test]
    public function repository_skips_database_for_empty_order_ids(): void
    {
        global $wpdb;
        $wpdb = new TransactionPrintWpdbFake();

        $result = ( new TransactionRepository() )->markPrintedByOrderIds( array() );

        $this->assertTrue( $result );
        $this->assertSame( 0, $wpdb->query_count );
    }

    #[Test]
    public function controller_delegates_print_state_to_repository_contract(): void
    {
        $repository = new TransactionPrintRepositoryFake();
        $controller = new ShippingProcessController( $repository );
        $method     = new ReflectionMethod( $controller, 'markTransactionsPrinted' );

        $method->invoke( $controller, array( 'KA-7', 'KA-8' ) );

        $this->assertSame( array( 'KA-7', 'KA-8' ), $repository->order_ids );
    }
}

final class TransactionPrintWpdbFake
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public string $prepared_query = '';
    public array $prepared_values = array();
    public string $executed_query = '';
    public int $query_count = 0;

    public function prepare( $query, $values )
    {
        $this->prepared_query  = $query;
        $this->prepared_values = $values;

        return $query;
    }

    public function query( $query )
    {
        $this->executed_query = $query;
        ++$this->query_count;

        return 2;
    }
}

final class TransactionPrintRepositoryFake implements TransactionPrintRepositoryInterface
{
    public array $order_ids = array();

    public function markPrintedByOrderIds( array $order_ids ): bool
    {
        $this->order_ids = $order_ids;

        return true;
    }
}
