<?php

namespace KiriminAjaOfficial\Infrastructure;

use KiriminAjaOfficial\Contracts\DatabaseTransactionManagerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WordPressDatabaseTransactionManager implements DatabaseTransactionManagerInterface
{
    private $wpdb;

    public function __construct( $wpdb = null )
    {
        if ( null === $wpdb ) {
            global $wpdb;
        }

        $this->wpdb = $wpdb;
    }

    public function begin(): void
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->wpdb->query( 'START TRANSACTION' );
    }

    public function commit(): void
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->wpdb->query( 'COMMIT' );
    }

    public function rollback(): void
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->wpdb->query( 'ROLLBACK' );
    }
}
