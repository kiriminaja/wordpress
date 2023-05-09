<?php

/**
 * Admin hooks
 */
class KiriminAja_Hooks_Product {

	/**
	 * KiriminAja Core
	 *
	 * @var object
	 */
	protected $core;

	/**
	 * KiriminAja Setting
	 *
	 * @var object
	 */
	protected $setting;

	/**
	 * KiriminAja Helper
	 *
	 * @var object
	 */
	protected $helper;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $kiriminaja_helper;
		global $kiriminaja_core;
		$this->core     = $kiriminaja_core;
		$this->setting  = new KiriminAja_Setting();
		$this->helper   = $kiriminaja_helper;

		if ( $this->setting->get('enable') ) {

			// setup.
			add_filter( 'woocommerce_is_purchasable', array( $this, 'set_product_purchasable' ), 10, 2 );

			// Let 3rd parties unhook the above via this hook.
			do_action( 'kiriminaja_hooks_product', $this );
		}
	}

	/**
	 * Make product not purchasable if weight and dimension is not set
	 * 
	 * @param boolean $is_purchasable Is product purchasable.
	 * @param object  $product        Product object.
	 */
	public function set_product_purchasable( $is_purchasable, $product ) {
		if ( $product->needs_shipping() ) {
			$weight = floatval( $product->get_weight() );
			$length = floatval( $product->get_length() );
			$width  = floatval( $product->get_width() );
			$height = floatval( $product->get_height() );
			if ( empty( $weight ) || empty( $length ) || empty( $width ) || empty( $height ) ) {
				return false;
			}
		}
		return $is_purchasable;
	}

}
