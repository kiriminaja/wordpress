<?php
/** Execute the real detail template with isolated authorization/read boundaries. */
namespace KiriminAjaOfficial\Repositories {
    class TransactionRepository {
        public function getUniqueTransactionByWCOrderId( $id ) {
            $GLOBALS['lookups'][] = $id;
            $matches = array_values( array_filter( $GLOBALS['input']['rows'] ?? array(), static fn( $row ) => (int) $row['wp_wc_order_stat_order_id'] === $id ) );
            return count( $matches ) === 1 ? (object) $matches[0] : false;
        }
        public function getTransactionById( $id ) { throw new \RuntimeException( 'Internal ID lookup forbidden.' ); }
    }
}
namespace KiriminAjaOfficial\Services {
    class TransactionDetailPageData {
        public function prepare( $row ) {
            if ( ! empty( $GLOBALS['input']['bootstrap_fail'] ) ) { throw new \RuntimeException( 'Fixture bootstrap failure.' ); }
            return array( 'transaction' => array( 'id' => $row->id, 'orderId' => $row->wp_wc_order_stat_order_id ) );
        }
        public function prepareFallback( $row, $message ) { return array( 'transaction' => array( 'id' => $row->id, 'orderId' => $row->wp_wc_order_stat_order_id ), 'bootstrapError' => $message ); }
    }
}
namespace {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
    define( 'KIRIOF_DIR', ABSPATH );
    $GLOBALS['input'] = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
    $GLOBALS['lookups'] = array(); $GLOBALS['logs'] = array();
    $_GET = array( 'id' => $GLOBALS['input']['id'] ?? '' );
    class RouteResponse extends \RuntimeException {}
    function current_user_can( $cap ) { return $GLOBALS['input']['authorized'] ?? true; }
    function wp_die( $message ) { throw new RouteResponse( 'denied' ); }
    function wp_safe_redirect( $url ) { throw new RouteResponse( 'redirect' ); }
    function admin_url( $url ) { return '/admin/' . $url; }
    function wp_unslash( $value ) { return stripslashes( $value ); }
    function esc_html__( $text, $domain ) { return $text; }
    function __( $text, $domain ) { return $text; }
    function esc_attr__( $text, $domain ) { return $text; }
    function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
    function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
    function kiriof_log( $level, $message, $context ) { $GLOBALS['logs'][] = $context; }
    ob_start(); $outcome = 'rendered';
    try { include ABSPATH . 'templates/transaction-detail/index.php'; }
    catch ( RouteResponse $error ) { $outcome = $error->getMessage(); }
    $html = ob_get_clean();
    echo json_encode( array( 'outcome' => $outcome, 'lookups' => $GLOBALS['lookups'], 'logs' => $GLOBALS['logs'], 'bootstrap' => $kiriof_transaction_detail_bootstrap ?? null, 'html' => $html ), JSON_THROW_ON_ERROR );
}
