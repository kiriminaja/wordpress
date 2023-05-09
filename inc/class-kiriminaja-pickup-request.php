<?php

/**
 * KiriminAja Pickup Request class
 */
class KiriminAja_Pickup_Request {

	/**
	 * KiriminAja Helper
	 *
	 * @var object
	 */
	protected $helper;

	/**
	 * Construtor
	 */
	public function __construct() {
		global $kiriminaja_helper;
		$this->helper = $kiriminaja_helper;

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'manage_pickup_request_posts_columns', array( $this, 'set_custom_column' ) );
		add_action( 'manage_pickup_request_posts_custom_column' , array( $this, 'display_custom_column' ), 20, 2 );
		add_action( 'admin_footer', array( $this, 'register_popups' ) );
	}

	/**
	 * Register custom post type
	 */
	public function register_post_type() {
		$labels = array(
			'name'                => _x( 'Pickup Requests', 'Post Type General Name', 'kiriminaja' ),
			'singular_name'       => _x( 'Pickup Request', 'Post Type Singular Name', 'kiriminaja' ),
			'menu_name'           => __( 'Pickup Requests', 'kiriminaja' ),
			'parent_item_colon'   => __( 'Parent Pickup Request', 'kiriminaja' ),
			'all_items'           => __( 'Pickup Requests', 'kiriminaja' ),
			'view_item'           => __( 'View Pickup Request', 'kiriminaja' ),
			'add_new_item'        => __( 'Add New Pickup Request', 'kiriminaja' ),
			'add_new'             => __( 'Add New', 'kiriminaja' ),
			'edit_item'           => __( 'Edit Pickup Request', 'kiriminaja' ),
			'update_item'         => __( 'Update Pickup Request', 'kiriminaja' ),
			'search_items'        => __( 'Search Pickup Request', 'kiriminaja' ),
			'not_found'           => __( 'Not Found', 'kiriminaja' ),
			'not_found_in_trash'  => __( 'Not found in Trash', 'kiriminaja' ),
		);
		
		$args = array(
			'label'               => __( 'Pickup Requests', 'kiriminaja' ),
			'description'         => __( 'Pickup Requests', 'kiriminaja' ),
			'labels'              => $labels,
			'supports'            => array( 'title' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => current_user_can( 'edit_others_shop_orders' ) ? 'woocommerce' : true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'capabilities'		  => array(),
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'shop_order',
		);
		
		// Registering your Custom Post Type
		register_post_type( 'pickup_request', $args );
	}

	/**
	 * Set custom column to the Pickup Request table
	 * 
	 * @param  array $columns Columns.
	 * @return array          Columns.
	 */
	public function set_custom_column( $columns ) {
		$columns = array(
			'number'   => __( 'Pickup Number', 'kiriminaja' ),
			'schedule' => __( 'Pickup Schedule', 'kiriminaja' ),
			'orders'   => __( 'Orders', 'kiriminaja' ),
			'status'   => __( 'Status', 'kiriminaja' ),
			'action'   => ''
		);
		return $columns;
	}

	/**
	 * Display custom column
	 * 
	 * @param  string  $column  Column name.
	 * @param  integer $post_id Post ID.
	 */
	public function display_custom_column( $column, $post_id ) {
		if ( '' === ( $status = get_post_meta( $post_id, 'status', true ) ) ) {
			$status = 'pending';
		}
		$payment_status = get_post_meta( $post_id, 'payment_status', true );
		if ( 'paid' === $payment_status && 'new' === $status ) { // backward compatibilty.
			$status = 'paid';
		}
		switch ( $column ) {
			case 'number':
				printf( '<strong>%s</strong><br>', get_the_title( $post_id ) );
				printf( __( '<small>Requested: %s</small>', 'kiriminaja' ), get_the_date( 'Y/m/d H:i', $post_id ) );
				break;

			case 'orders':
				$order_ids = get_post_meta( $post_id, 'order_ids', true );
				echo count( $order_ids ) . ' ' . _n( 'Order', 'Orders', count( $order_ids ) );
				// printf( '%d orders (<a class="view-orders" data-id="%d" href="#">View</a>)', count( $order_ids ), $post_id );
				// foreach ( $order_ids as $order_id ) {
				// 	printf( '<a href="%s">#%d</a> (%s)<br>', get_edit_post_link( $order_id ), $order_id, $this->helper->get_order_ref_id( $order_id ) );
				// }
				break;

			case 'schedule':
				if ( $schedule = get_post_meta( $post_id, 'schedule', true ) ) {
					echo date( 'Y/m/d H:i', strtotime( $schedule ) );
				}
				break;

			case 'status':
				printf( '<span class="pickup-status status-%s">%s</span>', $status, $this->helper->get_pickup_status_label( $post_id ) );
				break;
				
			case 'action':
				printf( __( '<button type="button" class="button ka-detail" data-id="%s">Details</button> ' , 'kiriminaja' ), $post_id );
				if ( in_array( $status, array( 'new', 'pending' ) ) ) {
					if ( current_time('timestamp') > strtotime( get_post_meta( $post_id, 'schedule', true ) ) ) {
						printf( __( '<button type="button" class="button button-primary ka-pay" data-id="%s" data-number="%s">Reschedule & Pay</button> ' , 'kiriminaja' ), $post_id, get_the_title( $post_id ) );
					} else {
						printf( __( '<button type="button" class="button button-primary ka-pay" data-id="%s" data-number="%s">Pay</button> ' , 'kiriminaja' ), $post_id, get_the_title( $post_id ) );
					}
				}
				break;
		}
	}

	public function register_popups() {
		$screen = get_current_screen();
		if ( 'edit-pickup_request' === $screen->id ) {
			?>
			<div id="kiriminaja-pickup-details" style="display:none;width:600px;">
				<div class="kiriminaja-request-pickup-detail">
					<div class="pickup-detail">
						<h3><?php esc_html_e( 'Details', 'kiriminaja' ); ?></h3>
						<p>
							<label><?php echo esc_html( 'Pickup Number', 'kiriminaja' ); ?></label>
							<span class="detail-number"></span>
						</p>
						<p>
							<label><?php echo esc_html( 'Status', 'kiriminaja' ); ?></label>
							<span class="detail-status"></span>
						</p>
						<p>
							<label><?php echo esc_html( 'Schedule', 'kiriminaja' ); ?></label>
							<span class="detail-schedule"></span>
						</p>
						<p>
							<label><?php echo esc_html( 'Requested on', 'kiriminaja' ); ?></label>
							<span class="detail-requested"></span>
						</p>
						<div class="detail-actions">
							<button type="button" class="button button-primary ka-detail-pay" data-code=""><?php esc_html_e( 'Pay', 'kiriminaja' ) ?></button>
							<!-- <button type="button" class="button ka-detail-cancel" data-id=""><?php esc_html_e( 'Cancel Pickup', 'kiriminaja' ) ?></button> -->
						</div>
					</div>
					<div class="orders-list-wrap">
						<h3><?php esc_html_e( 'Orders to Pickup', 'kiriminaja' ); ?></h3>
						<ul class="orders-list">
						</ul>
					</div>
				</div>
			</div>?>
			<div id="kiriminaja-reschedule-pickup" style="display:none;width:300px;">
				<div class="kiriminaja-request-pickup">
					<div class="pickup-time-wrapper">
						<h3><?php esc_html_e( 'Select time to pickup', 'kiriminaja' ); ?></h3>
						<div class="pickup-schedules">
						</div>
						<button type="button" class="button button-primary" id="kiriminaja-send-reschedule-pickup"><?php esc_html_e( 'Pick Schedule', 'kiriminaja' ) ?></button>
					</div>
				</div>
			</div>
			<div id="kiriminaja-payment" style="display:none;width:300px;">
				<div class="kiriminaja-payment">
					<img src="<?php echo KIRIMINAJA_PLUGIN_URL . '/assets/img/icon-qris.png' ?>" alt="QRIS" class="qris-logo">
					<h4 class="pickup-number"><?php esc_html_e( 'Pickup number:', 'kiriminaja' ) ?> <span></span></h4>
					<div id="qrcode"></div>
					<h3 class="amount"></h3>
					<div class="text-center">
						<button type="button" class="button button-primary" id="kiriminaja-reload"><?php esc_html_e( 'OK', 'kiriminaja' ) ?></button>
					</div>
				</div>
			</div>
			<?php
		}
	}

}