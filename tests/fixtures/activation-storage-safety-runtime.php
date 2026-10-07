<?php
/** Activation setup with an HPOS guard that throws on unsafe storage changes. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'vendor/autoload.php';
$input = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
$GLOBALS['options'] = array( 'woocommerce_custom_orders_table_enabled' => $input['hpos'] ?? 'yes', 'woocommerce_checkout_page_id' => 71, 'woocommerce_cart_page_id' => 72 );
$GLOBALS['writes'] = array(); $GLOBALS['pages'] = array();
function current_user_can( $cap ) { return true; }
function kiriof_check_woocommerce() { return true; }
function absint( $value ) { return abs( (int) $value ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) {
    if ( in_array( $key, array( 'woocommerce_custom_orders_table_enabled', 'woocommerce_custom_orders_table_data_sync_enabled' ), true ) ) { throw new RuntimeException( "The authoritative table for orders storage can't be changed while there are orders out of sync" ); }
    $GLOBALS['writes'][] = $key; $GLOBALS['options'][ $key ] = $value;
}
function get_post( $id ) { return in_array( $id, array( 71, 72 ), true ) ? (object) array( 'ID' => $id, 'post_type' => 'page', 'post_status' => 'publish' ) : null; }
function get_page_by_path( $slug ) { return (object) array( 'ID' => 'checkout' === $slug ? 81 : 82 ); }
function wp_update_post( $args ) { $GLOBALS['pages'][] = $args; }
$repository = new class implements \KiriminAjaOfficial\Contracts\TrackingPageRepositoryInterface {
    public function hasPublishedTrackingPage(): bool { return true; }
    public function findPublishedTrackingContent(): array { return array(); }
    public function findTrackingShortcodePages(): array { return array(); }
    public function findPreferredTrackingShortcodePage(): ?object { return (object) array( 'ID' => 31 ); }
};
$error = '';
try { ( new \KiriminAjaOfficial\Pages\AdminPost( $repository ) )->register(); }
catch ( Throwable $e ) { $error = $e->getMessage(); }
echo json_encode( array( 'error' => $error, 'options' => $GLOBALS['options'], 'writes' => $GLOBALS['writes'], 'pages' => $GLOBALS['pages'] ), JSON_THROW_ON_ERROR );
