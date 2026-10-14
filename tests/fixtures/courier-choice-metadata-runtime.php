<?php
/** Render the production shipping template using the isolated Classic WC boundary. */
define( 'KIRIOF_URL', 'https://shop.example/wp-content/plugins/kiriminaja/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
ob_start();
require __DIR__ . '/classic-order-review-runtime.php';
ob_end_clean();

class CourierFixtureRate extends ReviewFixtureRate {
	private $method;
	private $meta;
	public function __construct( $id, $method, $meta = array(), $label = 'JNE <third-party> & service' ) {
		parent::__construct( $id, $label, 15000 );
		$this->method = $method;
		$this->meta = $meta;
	}
	public function get_id() { return $this->id; }
	public function get_method_id() { return $this->method; }
	public function get_meta( $key, $single = true ) { return $this->meta[ $key ] ?? ''; }
}
$rates = array(
	new CourierFixtureRate( 'kiriminaja-official_jne_REG', 'kiriminaja-official', array( 'kiriof_rate_service' => 'jne' ) ),
	new CourierFixtureRate( 'kiriminaja-instant:17:grab_express:instant', 'kiriminaja-instant', array( 'kiriof_instant_courier' => 'grab_express' ) ),
	new CourierFixtureRate( 'kiriminaja-official:8:jnt_cargo:REG', 'kiriminaja-official' ),
	new CourierFixtureRate( 'kiriminaja-official:8:idx:00', 'kiriminaja-official', array( 'kiriof_rate_service' => 'ID_EXPRESS' ) ),
	new CourierFixtureRate( 'kiriminaja-official_jne_REG', 'flat_rate', array( 'kiriof_rate_service' => 'jne' ) ),
	new CourierFixtureRate( 'flat_rate:2', 'flat_rate', array( 'kiriof_instant_courier' => 'gosend' ) ),
	new CourierFixtureRate( 'kiriminaja-official_unknown_REG', 'kiriminaja-official' ),
	new CourierFixtureRate( 'kiriminaja-official_jne_REG', 'kiriminaja-official', array( 'kiriof_rate_service' => '../../jne" onerror="alert(1)' ) ),
	new ReviewFixtureRate( 'kiriminaja-official:8:jne:REG', 'Legacy fixture JNE', 15000 ),
	new ReviewFixtureRate( 'kiriminaja-official_grab_express_instant', 'Legacy GrabExpress', 15000 ),
	new ReviewFixtureRate( 'kiriminaja-official_8_grab_express_instant', 'Legacy zone GrabExpress', 15000 ),
	new ReviewFixtureRate( 'thirdparty_jne_REG', 'JNE REG', 15000 ),
	new CourierFixtureRate( 'kiriminaja-official_jneevil_REG', 'kiriminaja-official' ),
	new CourierFixtureRate( 'kiriminaja-official:8:jne:REG', 'kiriminaja-instant' ),
	new CourierFixtureRate( 'kiriminaja-official_posindonesia_240', 'kiriminaja-official' ),
	new CourierFixtureRate( 'kiriminaja-official_jtcargo_REG', 'kiriminaja-official' ),
	new CourierFixtureRate( 'kiriminaja-official_jne_REG', null ),
	new CourierFixtureRate( 'kiriminaja-official_jne_REG', '', array( 'kiriof_rate_service' => 'jne' ) ),
);
$GLOBALS['wc']->rates = $rates;
$GLOBALS['hooks'] = array();
ob_start();
wc_cart_totals_shipping_html();
$html = ob_get_clean();
echo json_encode( array(
	'html' => $html,
	'codes' => array_map( array( \KiriminAjaOfficial\Services\CourierLogoAssets::class, 'forRate' ), $rates ),
	'urls' => \KiriminAjaOfficial\Services\CourierLogoAssets::urls(),
	'hooks' => $GLOBALS['hooks'],
), JSON_THROW_ON_ERROR );
