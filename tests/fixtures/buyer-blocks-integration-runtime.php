<?php
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
function add_action( $hook, $callback, $priority = 10 ) {}
require ABSPATH . 'inc/Blocks/BuyerCheckoutRegistration.php';
$registry = new class { public int $registered = 0; public function register( $integration ) { ++$this->registered; } };
$registration = new \KiriminAjaOfficial\Blocks\BuyerCheckoutRegistration();
$registration->register();
$registration->register_integration( $registry );
echo json_encode( array( 'registered' => $registry->registered, 'loaded' => class_exists( '\KiriminAjaOfficial\Blocks\BuyerCheckoutIntegration', false ) ) );
