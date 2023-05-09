<?php

/**
 * KiriminAja API class
 */
class KiriminAja_API {

	/**
	 * API base url
	 *
	 * @var string
	 */
	protected $base_url;

	/**
	 * API default args
	 *
	 * @var array
	 */
	protected $default_args;

	/**
	 * Constructor
	 */
	public function __construct( $setting ) {
		global $wp_version;
        $this->base_url = 'https://kiriminaja.com';
		$this->default_args = array(
			'timeout'     => 30,
			'redirection' => 5,
			'httpversion' => '1.0',
			'user-agent'  => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ),
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $setting->get('token'),
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json'
			),
			'cookies'     => array(),
			'body'        => null,
			'compress'    => false,
			'decompress'  => true,
			'sslverify'   => false,
			'stream'      => false,
			'filename'    => null,
		);
		// $this->setting      = new POK_Setting();
	}

	/**
	 * Get province list
	 *
	 * @return array Province options.
	 */
	public function get_provinces() {
		return $this->post( '/api/mitra/province' );
	}

	/**
	 * Get cities by province
	 *
	 * @param  integer $province_id Province ID.
	 * @return array                City list.
	 */
	public function get_cities( $province_id = 0 ) {
		$body = array(
			'provinsi_id' => $province_id
		);
		return $this->post( '/api/mitra/city', $body );
	}

	/**
	 * Get disctricts by city
	 *
	 * @param  integer $city_id City ID.
	 * @return array            District list
	 */
	public function get_districts( $city_id = 0 ) {
		$body = array(
			'kabupaten_id' => $city_id
		);
		return $this->post( '/api/mitra/kecamatan', $body );
	}

	/**
	 * Search district
	 *
	 * @param  string $query Search query.
	 * @return array         District list
	 */
	public function search_districts( $query = '' ) {
		$body = array(
			'search' => $query
		);
		return $this->post( '/api/mitra/v2/get_address_by_name', $body );
	}

	/**
	 * Set services
	 *
	 * @param  array $services Services.
	 * @return object          Set service status.
	 */
	public function set_services( $services ) {
		$body = array(
			'services' => $services
		);
		return $this->post( '/api/mitra/v3/set_whitelist_services', $body );
	}

	/**
	 * Get shipping cost
	 *
	 * @param  integer $origin      Origin city ID.
	 * @param  integer $destination Destination ID.
	 * @param  integer $weight      Weight in grams.
	 * @param  array   $courier     Selected couriers.
	 * @param  integer $item_value  Item value if using insurance.
	 * @return array                Shipping costs.
	 */
	public function get_costs( $origin, $destination, $weight, $courier = array(), $item_value = 0, $insurance = false ) {
		$body = array(
			'origin'      => $origin,
			'destination' => $destination,
			'weight'      => $weight
		);
		if ( ! empty( $courier ) && is_array( $courier ) ) {
			$body['courier'] = $courier;
		}
		if ( $insurance && 0 < intval( $item_value ) ) {
			$body['insurance']  = 1;
			$body['item_value'] = intval( $item_value );
		}
		return $this->post( '/api/mitra/v5/shipping_price', $body );
	}

	/**
	 * Get pickup schedules
	 * 
	 * @return array Pickup Schedules.
	 */
	public function get_pickup_schedules() {
		return $this->post( '/api/mitra/v2/schedules' );
	}

	/**
	 * Send pickup request
	 * 
	 * @param  array  $args Args.
	 * @return object       Submit status.
	 */
	public function send_pickup_request( $args ) {
		return $this->post( '/api/mitra/v5/request_pickup', $args );
	}

	/**
	 * Cancel pickup request
	 * 
	 * @param  string $args Pickup number.
	 * @return object       Payment info including QR code.
	 */
	public function cancel_pickup_request( $pickup_number ) {
		$body = array(
			'pickup_number' => $pickup_number
		);
		return $this->post( '/api/mitra/cancel_pickup', $body );
	}

	/**
	 * Cancel shipment
	 * 
	 * @param  string $awb    Shipment AWB.
	 * @param  string $reason Cancel reason.
	 * @return object         Cancellation status.
	 */
	public function cancel_shipment( $awb, $reason ) {
		$body = array(
			'awb'    => $awb,
			'reason' => $reason
		);
		return $this->post( '/api/mitra/v3/cancel_shipment', $body );
	}

	/**
	 * Get payment
	 * 
	 * @param  string $args Pickup number.
	 * @return array        Payment info including QR code.
	 */
	public function get_payment( $pickup_number ) {
		$body = array(
			'pickup_number' => $pickup_number
		);
		return $this->post( '/api/mitra/v2/get_payment', $body );
	}

	/**
	 * Tracking order
	 * 
	 * @param  string $order_id KiriminAja's order ID.
	 * @return array            Tracking history.
	 */
	public function tracking( $order_id ) {
		$body = array(
			'order_id' => $order_id
		);
		return $this->post( '/api/mitra/tracking', $body );
	}

	/**
	 * Check if token is valid
	 * 
	 * @param  string  $token API Token.
	 * @return boolean        Token status.
	 */
	public function check_token( $token ) {
		$args = $this->default_args;
		$args['headers']['Authorization'] = 'Bearer ' . $token;
		$response = wp_remote_post( $this->base_url . '/api/mitra/province', $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body );
		if ( false === $body->status ) {
			return false;
		}
		return true;
	}

	/**
	 * Set callback URL
	 * 
	 * @param string $url Callback URL.
	 */
	public function set_callback_url( $url, $token = '' ) {
		$body = array(
			'url' => $url
		);
		if ( ! empty( $token ) ) {
			$this->default_args['headers']['Authorization'] = 'Bearer ' . $token;
		}
		return $this->post( '/api/mitra/set_callback', $body );
	}

	/**
	 * Get perferences
	 */
	public function get_perferences( $token = '' ) {
		$args = $this->default_args;
		if ( ! empty( $token ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}
		$response = wp_remote_get( $this->base_url . '/api/mitra/preference', $args );
		if ( class_exists( 'WPMonolog' ) ) {
			global $logger;
			$logger->addDebug( '[GET] ' . $this->base_url . '/api/mitra/preference' . ' | ' . serialize( $args ) . ' | ' . serialize( $this->populate_output( $response ) ) );
		}
		return $this->populate_output( $response );
	}

	/**
	 * POST request wrapper
	 * 
	 * @param  string $endpoint API endpoint.
	 * @param  array  $body     API body args.
	 * @return array            See populate_output.
	 */
	private function post( $endpoint, $body = array() ) {
		$args     = wp_parse_args( array( 'body' => json_encode( $body ) ), $this->default_args );
		$response = wp_remote_post( $this->base_url . $endpoint, $args );
		if ( class_exists( 'WPMonolog' ) ) {
			global $logger;
			$logger->addDebug( '[POST] ' . $this->base_url . $endpoint . ' | ' . serialize( $args ) . ' | ' . serialize( $this->populate_output( $response ) ) );
		}
		return $this->populate_output( $response );
	}

	/**
	 * GET request wrapper
	 * 
	 * @param  string $endpoint API endpoint.
	 * @param  array  $body     API body args.
	 * @return array            See populate_output.
	 */
	private function get( $endpoint, $body = array() ) {
		$args     = wp_parse_args( array( 'body' => $body ), $this->default_args );
		$response = wp_remote_get( $this->base_url . $endpoint, $args );
		if ( class_exists( 'WPMonolog' ) ) {
			global $logger;
			$logger->addDebug( '[GET] ' . $this->base_url . $endpoint . ' | ' . serialize( $args ) . ' | ' . serialize( $this->populate_output( $response ) ) );
		}
		return $this->populate_output( $response );
	}

	/**
	 * Populate output from API response
	 *
	 * @param  object $url  WP remote request.
	 * @return array        Sanitized API response.
	 */
	private function populate_output( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => false,
				'data'   => $response->get_error_message()
			);
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array(
				'status' => false,
				'data'   => 'Error ' . wp_remote_retrieve_response_code( $response )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body );
		if ( false === $body->status ) {
			return array(
				'status' => false,
				'data'   => isset( $body->text ) ? $body->text : 'Unknown error'
			);
		}
		return array(
			'status' => true,
			'data'   => $body
		);
	}
}