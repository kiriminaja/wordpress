<?php
/** Keep the translation stub and production class isolated from other runtime tests. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require_once ABSPATH . 'vendor/autoload.php';
function __( $text, $domain = '' ) {
	if ( 'kiriminaja-official' !== $domain ) { throw new RuntimeException( 'Unexpected translation domain.' ); }
	return $text;
}
$cases = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
$labels = array();
foreach ( $cases as $case ) {
	$row = $case['row'];
	if ( $case['object'] ) {
		if ( isset( $row['setting'] ) ) { $row['setting'] = (object) $row['setting']; }
		$row = (object) $row;
	}
	$labels[] = \KiriminAjaOfficial\Services\CheckoutRatePresentation::insuranceLabel( $row, $case['requested'] );
}
echo json_encode( $labels, JSON_THROW_ON_ERROR );
