<?php
/** A process-local Woo/session/transient boundary exercising the production cache. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'inc/Services/CheckoutServices/PricingCacheService.php';

use KiriminAjaOfficial\Services\CheckoutServices\PricingCacheService;

$warnings = array();
set_error_handler( static function ( $severity, $message ) use ( &$warnings ) {
	$warnings[] = $message;
	return true;
} );
final class PricingIsolationSession {
	public array $values = array();
	public function get( $key, $default = null ) { return $this->values[ $key ] ?? $default; }
	public function set( $key, $value ) { $this->values[ $key ] = $value; }
}
$wc = (object) array( 'session' => new PricingIsolationSession() );
$transients = array();
function WC() { return $GLOBALS['wc']; }
function wp_json_encode( $value ) { return json_encode( $value, JSON_THROW_ON_ERROR ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $entry, $ttl ) { $GLOBALS['transients'][ $key ] = $entry; return true; }
function reset_runtime(): void {
	$property = new ReflectionProperty( PricingCacheService::class, 'runtime_cache' );
	$property->setValue( null, array() );
}
function reset_cache(): void {
	reset_runtime();
	WC()->session->values = array();
	$GLOBALS['transients'] = array();
}
function pricing(): stdClass {
	return (object) array( 'status' => true, 'results' => array(
		(object) array( 'service' => 'jne', 'service_type' => 'REG', 'type' => 'express', 'cost' => 12000, 'setting' => (object) array( 'cod_fee' => 2500 ), 'extras' => array( (object) array( 'label' => 'original' ) ) ),
		(object) array( 'service' => 'ninja', 'service_type' => 'Standard', 'type' => 'express', 'cost' => 10000 ),
	) );
}
$payload = array( 'subdistrict_origin' => 123, 'subdistrict_destination' => 456, 'weight' => 1000, 'length' => 10, 'width' => 20, 'height' => 30, 'insurance' => 1, 'item_value' => 20000, 'pickup_option' => array( 'PICKUP' ), 'courier' => array( 'jne', 'ninja' ) );
$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
$result = array();
switch ( $input['scenario'] ) {
	case 'isolation':
		$source = pricing();
		PricingCacheService::put( $payload, $source );
		if ( in_array( $input['tier'], array( 'session', 'transient' ), true ) ) { reset_runtime(); }
		if ( 'transient' === $input['tier'] ) { WC()->session->values = array(); }
		$subset = array_replace( $payload, array( 'courier' => array( 'jne' ) ) );
		$mutable = 'put' === $input['tier'] ? $source : PricingCacheService::get( 'compatible' === $input['tier'] ? $subset : $payload );
		$mutable->results[0]->cost = 1;
		$mutable->results[0]->setting->cod_fee = 2;
		$mutable->results[0]->extras[0]->label = 'mutated';
		$mutable->results = array( $mutable->results[0] );
		// Read again from the same tier, not only the newly populated runtime cache.
		if ( in_array( $input['tier'], array( 'session', 'transient' ), true ) ) { reset_runtime(); }
		if ( 'transient' === $input['tier'] ) { WC()->session->values = array(); }
		$full = PricingCacheService::get( $payload );
		$result = array(
			'objects_preserved' => $full instanceof stdClass && $full->results[0] instanceof stdClass && $full->results[0]->setting instanceof stdClass && $full->results[0]->extras[0] instanceof stdClass,
			'services' => array_map( static fn( $row ) => $row->service, $full->results ),
			'cost' => $full->results[0]->cost, 'nested_fee' => $full->results[0]->setting->cod_fee, 'nested_label' => $full->results[0]->extras[0]->label,
			'subset_hit' => null !== PricingCacheService::get( $subset ),
		);
		break;
	case 'scope':
		$cases = array(
			array( null, array( 'jne' ) ), array( array( 'jne', 'ninja' ), array( 'jne' ) ), array( array( ' NINJA ', 'JNE', 'jne' ), array( 'ninja', 'jne' ) ),
			array( array( 'jne' ), array( 'jne', 'ninja' ) ), array( array( 'jne' ), null ), array( array( 'jne' ), array( 'ninja' ) ),
		);
		foreach ( $cases as [ $cached, $requested ] ) {
			reset_cache();
			$data = pricing();
			if ( array( 'jne' ) === $cached ) { $data->results = array( $data->results[0] ); }
			PricingCacheService::put( array_replace( $payload, array( 'courier' => $cached ) ), $data );
			$result['hits'][] = null !== PricingCacheService::get( array_replace( $payload, array( 'courier' => $requested ) ) );
		}
		break;
	case 'identity':
		PricingCacheService::put( $payload, pricing() );
		foreach ( array( 'subdistrict_origin', 'subdistrict_destination', 'weight', 'length', 'width', 'height', 'insurance', 'item_value', 'pickup_option' ) as $field ) {
			$changed = $payload;
			$changed[ $field ] = 'pickup_option' === $field ? array( 'DROP' ) : $payload[ $field ] + 1;
			$result['changed_hits'][] = null !== PricingCacheService::get( $changed );
		}
		$normalized = $payload;
		foreach ( array( 'subdistrict_origin', 'subdistrict_destination', 'weight', 'length', 'width', 'height', 'insurance', 'item_value' ) as $field ) { $normalized[ $field ] = (string) $payload[ $field ]; }
		$result['normalized_hit'] = null !== PricingCacheService::get( $normalized );
		$result['pin_hit'] = null !== PricingCacheService::get( array_merge( $payload, array( 'latitude' => '-6.2', 'longitude' => '106.8' ) ) );
		break;
	case 'invalid':
		PricingCacheService::put( $payload, pricing() );
		$cache = WC()->session->get( 'kiriof_shipping_price_cache' );
		$key = key( $cache );
		$valid = $cache[ $key ];
		$bad = array();
		foreach ( array( 'data', 'couriers', 'base_key' ) as $field ) { $entry = $valid; unset( $entry[ $field ] ); $bad[] = $entry; }
		foreach ( array( null, new stdClass(), (object) array( 'status' => true, 'results' => 'invalid' ), (object) array( 'status' => false, 'results' => array() ), (object) array( 'status' => true, 'results' => array( null ) ) ) as $data ) { $entry = $valid; $entry['data'] = $data; $bad[] = $entry; }
		$entry = $valid; $entry['stored_at'] = time() - 301; $bad[] = $entry;
		foreach ( $bad as $entry ) {
			reset_cache();
			WC()->session->set( 'kiriof_shipping_price_cache', array( $key => $entry ) );
			$result['hits'][] = null !== PricingCacheService::get( $payload );
		}
		foreach ( array( new stdClass(), (object) array( 'status' => false, 'results' => array() ), (object) array( 'status' => true, 'results' => 'invalid' ), (object) array( 'status' => true, 'results' => array( null ) ), null ) as $data ) {
			reset_cache(); PricingCacheService::put( $payload, $data );
			$result['put_hits'][] = null !== PricingCacheService::get( $payload );
		}
		break;
}
$result['warnings'] = $warnings;
echo json_encode( $result, JSON_THROW_ON_ERROR );
