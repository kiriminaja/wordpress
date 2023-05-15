<?php

/**
 * Admin hooks
 */
class KiriminAja_Hooks_Order
{

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
    public function __construct()
    {
        global $kiriminaja_helper;
        global $kiriminaja_core;
        $this->core = $kiriminaja_core;
        $this->setting = new KiriminAja_Setting();
        $this->helper = $kiriminaja_helper;

        if ($this->setting->get('enable')) {

            // setup.
            add_action('init', array($this, 'register_post_statuses'));
            add_filter('wc_order_statuses', array($this, 'register_order_statuses'));
            add_action('admin_footer', array($this, 'request_pickup_popup'));

            // order.
            add_filter('bulk_actions-edit-shop_order', array($this, 'register_bulk_action'));
            add_filter('woocommerce_shipping_address_map_url_parts', array($this, 'change_address_map_url'), 10, 2);

            // order detail.
            add_filter('woocommerce_order_actions', array($this, 'register_order_action'), 10, 2);
            add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hide_item_meta'));
            add_filter('woocommerce_order_item_display_meta_key', array($this, 'change_meta_keys'), 10, 3);
            add_filter('woocommerce_order_item_display_meta_value', array($this, 'change_meta_values'), 10, 3);
            add_action('woocommerce_admin_order_totals_after_discount', array($this, 'additional_shipping_info'));
            add_action('add_meta_boxes', array($this, 'custom_meta_box'), 10, 2);

            // status changes.
            add_action('woocommerce_order_status_pickup-request_to_cancelled', array($this, 'cancel_shipping'), 10, 2);

            // email.
            add_filter('woocommerce_email_classes', array($this, 'add_woocommerce_email'));
            add_filter('woocommerce_email_actions', array($this, 'register_email_actions'));

            // front.
            add_action('woocommerce_my_account_my_orders_column_order-status', array($this, 'order_shipping_status'));
            add_action('woocommerce_order_details_after_order_table', array($this, 'display_tracking'));

            // Let 3rd parties unhook the above via this hook.
            do_action('kiriminaja_hooks_order', $this);
        }
    }

    /**
     * Register new post statuses for order
     */
    public function register_post_statuses()
    {
        register_post_status('wc-pickup-request', array(
            'label' => _x('Waiting for Pickup', 'Order status', 'kiriminaja'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Waiting for Pickup <span class="count">(%s)</span>', 'Waiting for Pickup <span class="count">(%s)</span>', 'kiriminaja')
        ));
        register_post_status('wc-shipped', array(
            'label' => _x('Shipped', 'Order status', 'kiriminaja'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>', 'kiriminaja')
        ));
        register_post_status('wc-return', array(
            'label' => _x('Return', 'Order status', 'kiriminaja'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Return <span class="count">(%s)</span>', 'Return <span class="count">(%s)</span>', 'kiriminaja')
        ));
        register_post_status('wc-returned', array(
            'label' => _x('Returned', 'Order status', 'kiriminaja'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Returned <span class="count">(%s)</span>', 'Returned <span class="count">(%s)</span>', 'kiriminaja')
        ));
        register_post_status('wc-missing', array(
            'label' => _x('Claim Missing', 'Order status', 'kiriminaja'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Claim Missing <span class="count">(%s)</span>', 'Claim Missing <span class="count">(%s)</span>', 'kiriminaja')
        ));
        register_post_status('wc-missing-finished', array(
            'label' => _x('Claim Missing Finished', 'Order status', 'kiriminaja'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Claim Missing Finished <span class="count">(%s)</span>', 'Claim Missing Finished <span class="count">(%s)</span>', 'kiriminaja')
        ));
        register_post_status('wc-damaged', array(
            'label' => _x('Claim Damaged', 'Order status', 'kiriminaja'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Claim Damaged <span class="count">(%s)</span>', 'Claim Damaged <span class="count">(%s)</span>', 'kiriminaja')
        ));
        register_post_status('wc-damaged-finished', array(
            'label' => _x('Claim Damaged Finished', 'Order status', 'kiriminaja'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Claim Damaged Finished <span class="count">(%s)</span>', 'Claim Damaged Finished <span class="count">(%s)</span>', 'kiriminaja')
        ));
    }

    /**
     * Register new order statuses
     *
     * @param array $statuses Order statuses.
     */
    public function register_order_statuses($statuses)
    {
        $new_statuses = array();
        foreach ($statuses as $key => $value) {
            $new_statuses[$key] = $value;
            if ('wc-on-hold' === $key) {
                $new_statuses['wc-pickup-request'] = _x('Waiting for Pickup', 'Order status', 'kiriminaja');
                $new_statuses['wc-shipped'] = _x('Shipped', 'Order status', 'kiriminaja');
                $new_statuses['wc-return'] = _x('Return', 'Order status', 'kiriminaja');
                $new_statuses['wc-returned'] = _x('Returned', 'Order status', 'kiriminaja');
                $new_statuses['wc-missing'] = _x('Claim Missing', 'Order status', 'kiriminaja');
                $new_statuses['wc-missing-finished'] = _x('Claim Missing Finished', 'Order status', 'kiriminaja');
                $new_statuses['wc-damaged'] = _x('Claim Damaged', 'Order status', 'kiriminaja');
                $new_statuses['wc-damaged-finished'] = _x('Claim Damaged Finished', 'Order status', 'kiriminaja');
            }
        }
        return $new_statuses;
    }

    /**
     * Register new order bulk action
     *
     * @param array $actions Order bulk actions.
     * @return array          New order bulk actions.
     */
    public function register_bulk_action($actions)
    {
        $actions['request_pickup'] = __('Request Pickup', 'kiriminaja');
        $actions['cancel_shipment'] = __('Cancel Shipment', 'kiriminaja');
        return $actions;
    }

    /**
     * Register new order action
     *
     * @param array $actions Order actions.
     * @return array          New order actions
     */
    public function register_order_action($actions, $order)
    {
        if ($this->helper->is_order_available_for_pickup($order)) {
            $actions['request_pickup'] = __('Request pickup', 'kiriminaja');
        }
        $shipping = $this->helper->get_order_shipping($order);
        if (!$shipping) {
            return $actions;
        }
        $awb = $shipping->get_meta('awb');
        if (!empty($awb)) {
            $actions['cancel_shipment'] = __('Cancel shipment', 'kiriminaja');
        }
        return $actions;
    }

    /**
     * Generate request pickup pop-up
     */
    public function request_pickup_popup()
    {
        $screen = get_current_screen();
        if ('edit-shop_order' === $screen->id || 'shop_order' === $screen->id) {
            ?>
            <div id="kiriminaja-request-pickup" style="display:none;width:600px;">
                <div class="kiriminaja-request-pickup">
                    <div class="orders-list-wrap">
                        <h3><?php esc_html_e('Orders to Pickup', 'kiriminaja'); ?></h3>
                        <ul class="orders-list">
                        </ul>
                    </div>
                    <div class="pickup-time-wrapper">
                        <h3><?php esc_html_e('Select time to pickup', 'kiriminaja'); ?></h3>
                        <div class="pickup-schedules">
                        </div>
                        <button type="button" class="button button-primary"
                                id="kiriminaja-send-request-pickup"><?php esc_html_e('Send Request', 'kiriminaja') ?></button>
                    </div>
                </div>
            </div>
            <div id="kiriminaja-payment" style="display:none;width:300px;">
                <div class="kiriminaja-payment">
                    <img src="<?php echo KIRIMINAJA_PLUGIN_URL . '/assets/img/icon-qris.png' ?>" alt="QRIS"
                         class="qris-logo">
                    <h4 class="pickup-number"><?php esc_html_e('Pickup number:', 'kiriminaja') ?> <span></span></h4>
                    <div id="qrcode"></div>
                    <h3 class="amount"></h3>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Change Google Maps URL on orders table
     *
     * @param array $address Old address parts.
     * @param object $order WC_Order object.
     * @return array          New address parts.
     */
    public function change_address_map_url($address, $order)
    {
        $order_id = $order->get_id();
        $address['state'] = get_post_meta($order_id, '_shipping_state', true);
        $address['city'] = get_post_meta($order_id, '_shipping_city', true);
        if (isset($address['district'])) {
            $address['city'] = get_post_meta($order_id, '_shipping_district', true) . ', ' . $address['city'];
            unset($address['district']);
        }
        return $address;
    }

    /**
     * Hide custom item meta from order
     *
     * @param array $metas Item metas.
     * @return array        Item metas.
     */
    public function hide_item_meta($metas)
    {
        global $thepostid, $post;
        $thepostid = empty($thepostid) ? $post->ID : $thepostid;
        $metas[] = 'created_by_kiriminaja';
        $metas[] = 'courier';
        $metas[] = 'service';
        $metas[] = 'etd';
        $metas[] = 'cod';
        $metas[] = 'raw';
        $metas[] = 'base_cost';
        $metas[] = 'discount_amount';
        $metas[] = 'discount_percentage';
        $metas[] = 'insurance';
        $metas[] = 'cod_fee';
        if (!empty($thepostid)) {
            $order = wc_get_order($thepostid);
            if ('cod' !== $order->get_payment_method()) {
                $metas[] = 'cod_fee';
            }
        }
        return $metas;
    }

    /**
     * Change meta keys
     *
     * @param string $meta_key Original meta key.
     * @param object $meta Meta object.
     * @param object $item Item object.
     * @return string           Changed meta key.
     */
    public function change_meta_keys($meta_key, $meta, $item)
    {
        if ('insurance' === $meta->key) {
            $meta_key = __('Insurance Fee', 'kiriminaja');
        } elseif ('awb' === $meta->key) {
            $meta_key = __('AWB', 'kiriminaja');
        } elseif ('cod_fee' === $meta->key) {
            $meta_key = __('COD Fee', 'kiriminaja');
        }
        return $meta_key;
    }

    /**
     * Change meta values
     *
     * @param string $display_value Original value.
     * @param object $meta Meta object.
     * @param object $item Item object.
     * @return string                Changed value.
     */
    public function change_meta_values($display_value, $meta, $item)
    {
        if ('insurance' === $meta->key) {
            $display_value = wc_price($meta->value);
        }
        return $display_value;
    }

    /**
     * Show shipping weight & dimension on order
     *
     * @param integer $order_id Order ID.
     */
    public function additional_shipping_info($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order->get_shipping_methods()) {
            $dimension = $this->helper->get_order_dimension($order);
            ?>
            <tr>
                <td class="label"><?php esc_html_e('Shipping weight:', 'pok'); ?></td>
                <td width="1%"></td>
                <td class="total">
                    <span class="amount"><?php echo esc_html($this->helper->get_order_weight($order) . ' kg'); ?></span>
                </td>
            </tr>
            <tr>
                <td class="label"><?php esc_html_e('Shipping dimension:', 'pok'); ?></td>
                <td width="1%"></td>
                <td class="total">
                    <span
                        class="amount"><?php echo esc_html($dimension['length'] . ' x ' . $dimension['width'] . ' x ' . $dimension['height'] . ' cm'); ?></span>
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Register custom meta box
     *
     * @param string $post_type Post type.
     * @param object $post Post object.
     */
    public function custom_meta_box($post_type, $post)
    {
        if ('shop_order' === $post_type) {
            if ($shipping = $this->helper->get_order_shipping($post->ID)) {
                add_meta_box(
                    'ka_tracking',
                    __('Shipping', 'kiriminaja'),
                    array($this, 'render_order_shipping_metabox'),
                    'shop_order',
                    'side',
                    'core'
                );
            }
        }
    }

    /**
     * Render shipping tracking meta box
     *
     * @param WP_Post $post Post object.
     */
    public function render_order_shipping_metabox($post)
    {
        $order_id = get_post_meta($post->ID, '_ka_order_id', true);
        $pickup_id = get_post_meta($post->ID, '_ka_pickup_number', true);
        $shipping = $this->helper->get_order_shipping($post->ID);
        $tracking = $this->core->tracking($post->ID);
        include_once KIRIMINAJA_PLUGIN_PATH . 'views/admin/order-tracking.php';
    }

    /**
     * Cancel shipment
     *
     * @param integer $order_id Order ID.
     * @param object $order WC_Order.
     * @return mixed             API result.
     */
    public function cancel_shipping($order_id, $order)
    {
        $shipping = $this->helper->get_order_shipping($order_id);
        $pickup_id = get_post_meta($order_id, '_ka_pickup_id', true);
        if ($shipping->meta_exists('awb')) {
            $awb = $shipping->get_meta('awb', true);
            if (!empty($awb)) {
                return $this->core->cancel_shipment($awb, __('Shipment cancelled by admin', 'kiriminaja'));
            }
        }
        if (!empty($pickup_id)) {
            return $this->core->cancel_pickup_request($pickup_id);
        }
    }

    /**
     * Register Custom Email woocommerce for payment confirmation
     *
     * @param array $email_classes Email classes.
     */
    public function add_woocommerce_email($email_classes)
    {
        $email_classes['KiriminAja_Email_Shipped'] = include KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-email-shipped.php';
        $email_classes['KiriminAja_Email_Returned'] = include KIRIMINAJA_PLUGIN_PATH . 'inc/class-kiriminaja-email-returned.php';

        return $email_classes;
    }

    /**
     * Register email actions
     *
     * @param array $actions Actions.
     * @return array          Actions.
     */
    public function register_email_actions($actions)
    {
        $actions[] = 'woocommerce_order_status_pickup-request_to_shipped';
        $actions[] = 'woocommerce_order_status_shipped_to_returned';
        return $actions;
    }

    /**
     * Show last shipping status on order list on My Account
     *
     * @param WC_Order $order Order.
     */
    public function order_shipping_status($order)
    {
        echo esc_html(wc_get_order_status_name($order->get_status()));
        if ($status = $this->core->get_shipping_status_text($order->get_id())) {
            echo '<p class="ka-last-status"> ' . esc_html($status) . ' </p>';
        }
    }

    /**
     * Display order tracking on order detail on My Account
     *
     * @param WC_Order $order Order.
     */
    public function display_tracking($order)
    {
        if (is_account_page() && ($shipping = $this->helper->get_order_shipping($order)) && in_array($order->get_status(), array('shipped', 'completed', 'returned'))) {
            $tracking = $this->core->tracking($order->get_id());
            include_once KIRIMINAJA_PLUGIN_PATH . 'views/front/tracking.php';
        }
    }

}
