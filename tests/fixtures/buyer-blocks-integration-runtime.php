<?php
$root = dirname( __DIR__, 2 ) . '/';
$placement = 'placement' === ( $argv[1] ?? '' );
// register() includes this WordPress file; keep the isolated shim outside the repo.
$temp = $placement ? sys_get_temp_dir() . '/kiriof-block-registration-' . getmypid() : null;
if ( $temp ) {
    mkdir( $temp . '/wp-admin/includes', 0777, true );
    file_put_contents( $temp . '/wp-admin/includes/plugin.php', '<?php function is_plugin_active($plugin) { return true; }' );
    register_shutdown_function( static function () use ( $temp ) {
        unlink( $temp . '/wp-admin/includes/plugin.php' );
        rmdir( $temp . '/wp-admin/includes' );
        rmdir( $temp . '/wp-admin' );
        rmdir( $temp );
    } );
}
define( 'ABSPATH', $temp ? $temp . '/' : $root );
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['filters'][] = array( 'hook' => $hook, 'callback' => is_array( $callback ) ? $callback[1] : $callback, 'priority' => $priority, 'acceptedArgs' => $accepted_args );
}
require $root . 'inc/Blocks/BuyerCheckoutRegistration.php';
$registry = new class { public int $registered = 0; public function register( $integration ) { ++$this->registered; } };
$registration = new \KiriminAjaOfficial\Blocks\BuyerCheckoutRegistration();
$registration->register();
$registration->register_integration( $registry );
if ( $placement ) {
    define( 'KIRIOF_DIR', $root );
    function register_block_type_from_metadata( $path, $args ) { $GLOBALS['blocks'][] = array( 'metadata' => json_decode( file_get_contents( $path . '/block.json' ), true ), 'html' => call_user_func( $args['render_callback'] ) ); }
    require $root . 'inc/Controllers/CheckoutController.php';
    $controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
    $controller->register();
    $controller->kiriof_register_district_checkout_block();
    echo json_encode( array( 'blocks' => $GLOBALS['blocks'], 'filters' => $GLOBALS['filters'], 'insertionMethodExists' => method_exists( $controller, 'kiriof_insert_shipping_checkout_blocks' ), 'cartLoaded' => function_exists( 'WC' ) ) );
    exit;
}
echo json_encode( array( 'registered' => $registry->registered, 'loaded' => class_exists( '\KiriminAjaOfficial\Blocks\BuyerCheckoutIntegration', false ) ) );
