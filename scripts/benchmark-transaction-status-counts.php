<?php
/** Run with an optional production source path; stdout is the raw measurement JSON. */
declare( strict_types=1 );

if ( 'cli' !== PHP_SAPI ) {
    exit;
}

require_once dirname( __DIR__ ) . '/tests/fixtures/transaction-status-counts-performance.php';

$options = array( 'warmups' => 3, 'samples' => 15, 'orders' => 2000 );
if ( isset( $argv[1] ) ) {
    $options['source'] = $argv[1];
}
echo json_encode( transaction_status_counts_run( $options ), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR ) . PHP_EOL;
