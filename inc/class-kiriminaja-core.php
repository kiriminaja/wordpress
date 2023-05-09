<?php

/**
 * KiriminAja Core class
 */
class KiriminAja_Core {

	/**
	 * KiriminAja API
	 *
	 * @var object
	 */
	protected $api;

	/**
	 * Cache key prefix
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $kiriminaja_helper;
		$this->init();
		$this->prefix  = 'kiriminaja_data_';
		$this->cache   = true;
		$this->helper  = $kiriminaja_helper;
	}

	/**
	 * Init the core
	 */
	public function init() {
		$this->setting = new KiriminAja_Setting();
		$this->api     = new KiriminAja_API( $this->setting );
	}

	/**
	 * Check cache
	 *
	 * @param  string $key Cache key.
	 * @return boolean      Is exists or not
	 */
	private function is_cache_exists( $key ) {
		if ( $this->cache ) {
			$data = get_option( $this->prefix . sanitize_title_for_query( $key ), false );
			if ( $data && ! empty( $data ) ) {
				if ( false !== get_transient( $this->prefix . sanitize_title_for_query( $key ) ) ) {
					return true;
				}
			} else {
				delete_transient( $this->prefix . sanitize_title_for_query( $key ) );
			}
		}
		return false;
	}

	/**
	 * Cache requested data
	 *
	 * @param  string  $key        Cache key.
	 * @param  mixed   $new_value  Cache value.
	 * @param  integer $expiration Cache expiration in seconds.
	 * @return mixed               Cached data.
	 */
	private function cache_it( $key, $new_value = null, $expiration = 3600 ) {
		if ( ! is_null( $new_value ) ) {
			if ( $this->cache ) {
				update_option( $this->prefix . sanitize_title_for_query( $key ), $new_value, 'no' );
				set_transient( $this->prefix . sanitize_title_for_query( $key ), true, $expiration ); // we store data with option, so no need to set value on transient.
			}
			$return = $new_value;
		} else {
			$return = get_option( $this->prefix . sanitize_title_for_query( $key ), false );
		}
		return apply_filters( 'kiriminaja_get_cached_' . $key, $return );
	}

	/**
	 * Delete all cached data by type
	 *
	 * @param  string $key_type Key type.
	 */
	public function purge_cache( $key_type = '' ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", array(
					$this->prefix . $key_type . '%',
					'_transient_' . $this->prefix . $key_type . '%',
					'_transient_timeout_' . $this->prefix . $key_type . '%',
				)
			)
		);
	}

	/**
	 * Delete cache by key
	 *
	 * @param  string $key Cache key.
	 */
	public function delete_cache( $key ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", array(
					$this->prefix . sanitize_title_for_query( $key ),
					'_transient_' . $this->prefix . sanitize_title_for_query( $key ),
					'_transient_timeout_' . $this->prefix . sanitize_title_for_query( $key ),
				)
			)
		);
	}

	/**
	 * Get all couriers
	 *
	 * @return array All couriers.
	 */
	public function get_all_couriers() {
		return $this->setting->get_couriers();
	}

	/**
	 * Get province options
	 *
	 * @return array Province options
	 */
	public function get_province() {
		if ( ! $this->is_cache_exists( 'province' ) ) {
			$result = $this->api->get_provinces();
			if ( $result['status'] && isset( $result['data']->datas ) && is_array( $result['data']->datas ) ) {
				foreach ( $result['data']->datas as $data ) {
					$provinces[ $data->id ] = $data->provinsi_name;
				}
			}
		}
		return $this->cache_it( 'province', isset( $provinces ) && ! empty( $provinces ) ? $provinces : null );
	}

	/**
	 * Get city options based on province id
	 *
	 * @param  integer $province_id Province ID.
	 * @return array                City list.
	 */
	public function get_city( $province_id = 0 ) {
		if ( ! $this->is_cache_exists( 'city_' . $province_id ) ) {
			$result = $this->api->get_cities( $province_id );
			if ( $result['status'] && isset( $result['data']->datas ) && is_array( $result['data']->datas ) ) {
				foreach ( $result['data']->datas as $data ) {
					$type = isset( $data->type ) && 'Kota' === $data->type ? 'Kota' : 'Kab.';
					$cities[ $data->id ] = $type . ' ' . $data->kabupaten_name;
				}
			}
		}
		return $this->cache_it( 'city_' . $province_id, isset( $cities ) && ! empty( $cities ) ? $cities : null );
	}

	/**
	 * Get district by the City ID
	 *
	 * @param  integer $city_id City ID.
	 * @return array            District list.
	 */
	public function get_district( $city_id = 0 ) {
		if ( ! $this->is_cache_exists( 'district_' . $city_id ) ) {
			$result = $this->api->get_districts( $city_id );
			if ( $result['status'] && isset( $result['data']->datas ) && is_array( $result['data']->datas ) ) {
				foreach ( $result['data']->datas as $data ) {
					$districts[ $data->id ] = $data->kecamatan_name;
				}
			}
		}
		return $this->cache_it( 'district_' . $city_id, isset( $districts ) && ! empty( $districts ) ? $districts : null );
	}

	/**
	 * Search district by name
	 *
	 * @param  string $query Search query.
	 * @return array         District list.
	 */
	public function search_district( $query = '' ) {
		if ( ! $this->is_cache_exists( 'search_district_' . sanitize_title( $query ) ) ) {
			$result = $this->api->search_districts( $query );
			if ( $result['status'] && isset( $result['data']->data ) && is_array( $result['data']->data ) ) {
				$provinces = $this->get_province();
				foreach ( $result['data']->data as $data ) {
					$exp = explode(',', $data->text);
					$districts[ $data->id ] = array(
						'full'     => $data->text,
						'district' => trim( $exp[0] ),
						'city'     => trim( $exp[1] ),
						'state'    => is_array( $provinces ) ? array_search( trim( $exp[2] ), $provinces ) : trim( $exp[2] )
					);
				}
			}
		}
		return $this->cache_it( 'search_district_' . sanitize_title( $query ), isset( $districts ) && ! empty( $districts ) ? $districts : null );
	}

	/**
	 * Get shipping cost
	 *
	 * @param  integer $destination Destination ID (city or district).
	 * @param  integer $weight      Weight in kilograms.
	 * @return array                Costs.
	 */
	public function get_cost( $destination = 0, $weight = 1, $item_value = 0, $insurance = false ) {
		$weight     = $weight * 1000;
		$cache_name = "cost_{$destination}_{$weight}_{$item_value}_{$insurance}";
		if ( ! $this->is_cache_exists( $cache_name ) ) {
			$origin  = $this->setting->get( 'store_district' );
			$courier = $this->setting->get( 'couriers' );
			$result  = $this->api->get_costs( $origin, $destination, $weight, $courier, $item_value, $insurance );
			$costs   = array();
			if ( $result['status'] && isset( $result['data']->results ) && is_array( $result['data']->results ) ) {
				foreach ( $result['data']->results as $data ) {
					$cost = array(
						'name'    => $data->service_name,
						'courier' => $data->service,
						'service' => $data->service_type,
						'cost'    => intval( $data->cost ),
						'etd'     => $data->etd,
						'cod'     => $data->cod,
						'group'   => $data->group,
						'discount_amount' => isset( $data->discount_amount ) ? intval( $data->discount_amount ) : 0,
						'discount_percentage' => isset( $data->discount_percentage ) ? floatval( $data->discount_percentage ) : 0
					);

					// insurance.
					if ( $insurance ) {
						$insurance_fee      = isset( $data->setting ) && isset( $data->setting->insurance_fee ) ? floatval( $data->setting->insurance_fee ) : 0;
						$insurance_add_cost = isset( $data->setting ) && isset( $data->setting->insurance_add_cost ) ? intval( $data->setting->insurance_add_cost ) : 0;
						$cost['insurance']  = ceil( intval( $item_value ) * floatval( $insurance_fee ) ) + intval( $insurance_add_cost );
					} else {
						$cost['insurance'] = 0;
					}

					// cod fee.
					if ( true === boolval( $data->cod ) ) {
						$cod_fee         = isset( $data->setting ) && isset( $data->setting->cod_fee ) ? floatval( $data->setting->cod_fee ) : 0;
						$min_cod_fee     = isset( $data->setting ) && isset( $data->setting->minimum_cod_fee ) ? intval( $data->setting->minimum_cod_fee ) : 0;
						$final_cost      = isset( $data->discount_amount ) ? ( intval( $data->cost ) - intval( $data->discount_amount ) ) : intval( $data->cost );
						$cost['cod_fee'] = max( ceil( ( intval( $item_value ) + $final_cost + $cost['insurance'] ) * $cod_fee ), $min_cod_fee );
					} else {
						$cost['cod_fee'] = 0;
					}

					$costs[] = $cost;
				}
			}
		}
		return $this->cache_it( $cache_name, isset( $costs ) && ! empty( $costs ) ? $costs : null );
	}

	/**
	 * Get pickup schedules
	 * 
	 * @return array Schedules.
	 */
	public function get_pickup_schedules() {
		$result    = $this->api->get_pickup_schedules();
		$schedules = array();
		if ( $result['status'] && isset( $result['data']->schedules ) && is_array( $result['data']->schedules ) ) {
			foreach ( $result['data']->schedules as $data ) {
				$schedules[] = array(
					'time'  => $data->clock,
					'label' => date_i18n( 'l, Y-m-d H:i', strtotime( $data->clock ) )
				);
			}
		}
		return $schedules;
	}

	/**
	 * Send pickup request
	 * 
	 * @param  array   $order_ids Array of order ids.
	 * @param  string  $schedule  Selected schedule (datetime).
	 * @param  integer $pickup_id Pickup ID.
	 * @return array              Response.
	 */
	public function send_pickup_request( $order_ids, $schedule, $pickup_id = 0 ) {

		// if pickup_id are given, means this request is reschedule.
		$is_update = ! empty( $pickup_id);

		$args = array(
			'name'         => $this->setting->get('store_name'),
			'address'      => $this->setting->get('store_address'),
			'phone'        => $this->helper->format_phone_number( $this->setting->get('store_phone') ),
			'zipcode'      => $this->setting->get('store_zipcode'),
			'provinsi_id'  => $this->setting->get('store_province'),
			'kabupaten_id' => $this->setting->get('store_city'),
			'kecamatan_id' => $this->setting->get('store_district'),
			'schedule'     => $schedule,
			'packages'     => array()
		);

		if ( $is_update ) {
			$args['payment_id'] = get_the_title( $pickup_id ); // pickup number.
		}

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			$phone = $order->get_shipping_phone();
			if ( empty( $phone ) ) {
				$phone = $order->get_billing_phone();
			}
			$shipping_method = $this->helper->get_order_shipping( $order );
			$payment_method  = $order->get_payment_method();
			$order_dimension = $this->helper->get_order_dimension( $order );

			$data = array(
				'order_id'                 => $this->helper->get_order_ref_id( $order_id ),
				'destination_name'         => $order->get_formatted_shipping_full_name(),
				'destination_phone'        => $this->helper->format_phone_number( $phone ),
				'destination_address'      => substr( WC()->countries->get_formatted_address( $order->get_address('shipping'), ', ' ), 0, 200 ),
				'destination_kecamatan_id' => get_post_meta( $order->get_id(), '_shipping_district_id', true ),
				'weight'                   => $this->helper->get_order_weight( $order ) * 1000,
				'width'                    => $order_dimension['width'],
				'length'                   => $order_dimension['length'],
				'height'                   => $order_dimension['height'],
				'qty'                      => intval( $order->get_item_count() ),
				'item_value'               => intval( $order->get_subtotal() ),
				'shipping_cost'            => intval( $shipping_method->get_meta('base_cost') ),
				'discount_amount'          => intval( $shipping_method->get_meta('discount_amount') ),
				'discount_percentage'      => floatval( $shipping_method->get_meta('discount_percentage') ),
				'item_name'                => substr( $this->helper->get_order_items_name( $order ), 0, 50 ),
				'service'                  => $shipping_method->get_meta('courier'),
				'service_type'             => $shipping_method->get_meta('service'),
				'add_cost'                 => intval( $shipping_method->get_meta('cod_fee') ),
				'cod'                      => 0,
				'package_type_id'          => 1,
				'insurance_amount'         => intval( $shipping_method->get_meta('insurance') ),
			);
			if ( 'cod' === $payment_method ) {
				$data['cod'] = $data['item_value'] + $data['shipping_cost'] - $data['discount_amount'] + $data['insurance_amount'] + $data['add_cost'];
			}
			$args['packages'][] = $data;
		}

		$result = $this->api->send_pickup_request( $args );

		if ( $is_update && $result['status'] && isset( $result['data']->status ) && $result['data']->status ) { // reschedule.

			update_post_meta( $pickup_id, 'schedule', $schedule );

			return array(
				'status'        => true,
				'schedule'      => $schedule,
				'pickup_number' => get_the_title( $pickup_id )
			);

		} elseif ( $result['status'] && isset( $result['data']->details ) && is_array( $result['data']->details ) && isset( $result['data']->pickup_number ) ) {

			$pickup_id = wp_insert_post( array(
				'post_type'    => 'pickup_request',
				'post_title'   => $result['data']->pickup_number,
				'post_status'  => 'publish',
				'post_content' => json_encode( $result['data']->details )
			) );

			$order_ids = array();
			foreach ( $result['data']->details as $data ) {
				if ( $order_id = $this->helper->get_order_by_ref_id( $data->order_id ) ) {
					$order    = wc_get_order( $order_id );
					$shipping = $this->helper->get_order_shipping( $order );
					$shipping->add_meta_data( 'awb', $data->awb, true );
					// $shipping->add_meta_data( 'awb', '001294604288', true );
					$shipping->save();
					$order->set_status( 'pickup-request', sprintf( __( 'Pickup number: %s.', 'kiriminaja' ), $result['data']->pickup_number ) );
					$order->save();
					update_post_meta( $order_id, '_ka_pickup_number', $result['data']->pickup_number );
					update_post_meta( $order_id, '_ka_pickup_id', $pickup_id );
					$order_ids[] = $order_id;
				}
			}

			$status = isset( $result['data']->payment_status ) ? $result['data']->payment_status : 'pending';
			if ( 'unpaid' === $status ) {
				$status = 'pending';
			}
			update_post_meta( $pickup_id, 'order_ids', $order_ids );
			update_post_meta( $pickup_id, 'schedule', $schedule );
			update_post_meta( $pickup_id, 'status', $status );

			return array(
				'status'	     => true,
				'pickup_number'  => $result['data']->pickup_number,
				'order_ids'      => $order_ids,
				'details'        => $result['data']->details,
				'status'         => $status
			);
		}

		return array(
			'status'  => false,
			'message' => isset( $result['data'] ) ? $result['data'] : __( 'Unknown error', 'kiriminaja' )
		);
	}

	/**
	 * Cancel pickup request
	 * 
	 * @param  integer $pickup_id    Pickup ID.
	 * @return array                 Cancellation status.
	 */
	public function cancel_pickup_request( $pickup_id ) {
		$pickup_number = get_the_title( $pickup_id );
		$result = $this->api->cancel_pickup_request( $pickup_number );

		if ( $result['status'] && isset( $result['data']->packages ) && is_array( $result['data']->packages ) ) {
			$order_ids = array();
			foreach ( $result['data']->packages as $package ) {
				if ( $order_id = $this->helper->get_order_by_ref_id( $data->order_id ) ) {
					$order_ids[] = $order_id;
				}
			}

			update_post_meta( $pickup_id, 'status', 'cancel' );

			return array(
				'status'	    => true,
				'pickup_number' => $pickup_number,
				'order_ids'     => $order_ids,
				'packages'      => $result['data']->packages
			);
		}

		return array(
			'status'  => false,
			'message' => isset( $result['data'] ) ? $result['data'] : __( 'Unknown error', 'kiriminaja' )
		);
	}

	/**
	 * Cancels shipment of selected order
	 * 
	 * @param  integer $order_id Order ID.
	 * @param  string  $reason   Cancelation reason.
	 * @return array             Cancellation status.
	 */
	public function cancel_shipment( $order_id, $reason ) {
		$order    = wc_get_order( $order_id );
		$shipping = $this->helper->get_order_shipping( $order );
		$awb      = $shipping->get_meta('awb');
		if ( ! empty( $awb ) ) {
			$result = $this->api->cancel_shipment( $awb, $reason );
			if ( $result['status'] && isset( $result['data']->data ) ) {
				if ( 'success' === $result['data']->data->success ) {
					$order->set_status( 'on-hold', $result['data']->text );
					$order->save();
				} else {
					$order->add_order_note( $result['data']->text );
					$order->save();
				}

				return array(
					'status'  => true,
					'message' => $result['data']->text
				);
			}
		}

		return array(
			'status'  => false,
			'message' => isset( $result['data'] ) ? $result['data'] : __( 'Unknown error', 'kiriminaja' )
		);
	}

	/**
	 * Get payment by pickup number
	 * 
	 * @param  string $pickup_number Pickup number.
	 * @return array                 Order tracking result.
	 */
	public function get_payment( $pickup_number ) {
		$result = $this->api->get_payment( $pickup_number );
		if ( $result['status'] && isset( $result['data']->data ) ) {

			return array(
				'status'  => true,
				'payment' => $result['data']->data,
				'amount'  => wc_price( $result['data']->data->amount )
			);
		}

		return array(
			'status'  => false,
			'message' => isset( $result['data'] ) ? $result['data'] : __( 'Unknown error', 'kiriminaja' )
		);
	}

	/**
	 * Tracking order
	 * 
	 * @param  integer $order_id Order ID.
	 * @return array             Order tracking result.
	 */
	public function tracking( $order_id ) {
		if ( ! $this->helper->has_awb( $order_id ) ) {
			return array(
				'text'      => '',
				'details'   => array(),
				'histories' => array()
			);
		}
		if ( ! $this->is_cache_exists( 'tracking_' . $order_id ) ) {
			$ref_id = $this->helper->get_order_ref_id( $order_id );
			$result = $this->api->tracking( $ref_id );

			if ( $result['status'] && isset( $result['data']->details ) && isset( $result['data']->histories ) ) {
				$tracking = array(
					'text'      => $result['data']->text,
					'details'   => $result['data']->details,
					'histories' => $result['data']->histories
				);
			}
		}
		return $this->cache_it( 'tracking_' . $order_id, isset( $tracking ) && ! empty( $tracking ) ? $tracking : null );
	}

	/**
	 * Get last order shipping status text.
	 * 
	 * @param  integer        $order_id Order ID.
	 * @return string|boolean           Order last status.
	 */
	public function get_shipping_status_text( $order_id ) {
		$order_status    = str_replace( 'wc-', '', get_post_status( $order_id ) );
		$shipping_status = get_post_meta( $order_id, '_last_shipping_status', true );
		if ( 'completed' === $order_status ) {
			$shipping_status = get_post_meta( $order_id, '_last_shipping_status', true );
		} elseif ( 'shipped' === $order_status || 'pickup-request' === $order_status ) {
			if ( $tracking = $this->tracking( $order_id ) ) {
				$shipping_status = $tracking['text'];
				update_post_meta( $order_id, '_last_shipping_status', $shipping_status );
			}
		}
		return empty( $shipping_status ) ? false : $shipping_status;
	}

	/**
	 * Get perferences
	 * 
	 * @return array Perferences.
	 */
	public function get_perferences( $token ) {
		$result = $this->api->get_perferences( $token );
		if ( $result['status'] && isset( $result['data']->result ) ) {
			$perferences     = $result['data']->result;
			$active_couriers = array();
			foreach ( $perferences->selected_couriers as $courier ) {
				if ( $courier->enable ) {
					$active_couriers[] = $courier->code;
				}
			}
			$this->setting->set( 'token', $token );
			$this->setting->set( 'ref_prefix', $perferences->order_prefix . '-' );
			$this->setting->set_couriers( $perferences->selected_couriers );
			$this->setting->set( 'couriers', $active_couriers );
		}
		return $result;
	}

	/**
	 * Check if token is valid
	 * 
	 * @param  string  $token API Token.
	 * @return boolean        Token status.
	 */
	public function check_token( $token ) {
		return $this->api->check_token( $token );
	}

	/**
	 * Set callback URL.
	 */
	public function set_callback_url( $token ) {
		$url = add_query_arg( 'wc-api', 'kiriminaja_gateway', site_url() );
		return $this->api->set_callback_url( $url, $token );
	}
}
