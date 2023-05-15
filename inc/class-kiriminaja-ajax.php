<?php

/**
 * KiriminAja Ajax class
 */
class KiriminAja_Ajax
{

    /**
     * KiriminAja core function
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
     * Constructor
     */
    public function __construct()
    {
        global $kiriminaja_core;
        global $kiriminaja_helper;
        $this->core = $kiriminaja_core;
        $this->helper = $kiriminaja_helper;
        $this->setting = new KiriminAja_Setting();

        // checkout.
        add_action('wp_ajax_kiriminaja_change_country', array($this, 'change_country'));
        add_action('wp_ajax_nopriv_kiriminaja_change_country', array($this, 'change_country'));
        add_action('wp_ajax_kiriminaja_get_list_city', array($this, 'get_list_city'));
        add_action('wp_ajax_nopriv_kiriminaja_get_list_city', array($this, 'get_list_city'));
        add_action('wp_ajax_kiriminaja_get_list_district', array($this, 'get_list_district'));
        add_action('wp_ajax_nopriv_kiriminaja_get_list_district', array($this, 'get_list_district'));
        add_action('wp_ajax_kiriminaja_search_district', array($this, 'search_district'));
        add_action('wp_ajax_nopriv_kiriminaja_search_district', array($this, 'search_district'));
        add_action('wp_ajax_kiriminaja_get_cost', array($this, 'get_cost'));
        add_action('wp_ajax_nopriv_kiriminaja_get_cost', array($this, 'get_cost'));

        // setting.
        add_action('wp_ajax_kiriminaja_set_token', array($this, 'set_token'));
        add_action('wp_ajax_kiriminaja_delete_token', array($this, 'delete_token'));

        // orders.
        add_action('wp_ajax_kiriminaja_load_orders', array($this, 'load_pickup_orders'));
        add_action('wp_ajax_kiriminaja_load_schedules', array($this, 'load_pickup_schedules'));
        add_action('wp_ajax_kiriminaja_send_pickup_request', array($this, 'send_pickup_request'));
        add_action('wp_ajax_kiriminaja_send_pickup_reschedule', array($this, 'send_pickup_reschedule'));
        add_action('wp_ajax_kiriminaja_check_schedule', array($this, 'check_schedule'));
        add_action('wp_ajax_kiriminaja_load_payment', array($this, 'load_payment'));
        add_action('wp_ajax_kiriminaja_load_pickup_detail', array($this, 'load_pickup_detail'));
        add_action('wp_ajax_kiriminaja_cancel_pickup', array($this, 'cancel_pickup'));
        add_action('wp_ajax_kiriminaja_get_shipping_status', array($this, 'get_shipping_status'));
        add_action('wp_ajax_kiriminaja_cancel_shipment', array($this, 'cancel_shipment'));

        // profile.
        add_action('wp_ajax_kiriminaja_change_profile_country', array($this, 'change_profile_country'));
    }

    /**
     * Change country on checkout page
     */
    public function change_country()
    {
        check_ajax_referer('change_country', 'kiriminaja_action');
        $new_value = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : 'ID'; // Input var okay.
        $context = isset($_POST['context']) ? sanitize_text_field(wp_unslash($_POST['context'])) : 'billing'; // Input var okay.
        $customer = maybe_unserialize(WC()->session->get('customer'));
        if ('billing' === $context) {
            $session_name = 'country';
        } else {
            $session_name = 'shipping_country';
        }
        $old_value = isset($customer[$session_name]) ? $customer[$session_name] : 'ID';
        if ($old_value !== $new_value) {
            $customer[$session_name] = $new_value;
            WC()->session->set('customer', maybe_serialize($customer));
            if ('ID' === $old_value || 'ID' === $new_value) {
                echo 'reload';
            }
        }
        die();
    }

    /**
     * Get list city
     */
    public function get_list_city()
    {
        check_ajax_referer('get_list_city', 'kiriminaja_action');
        $province_id = isset($_POST['province_id']) ? sanitize_text_field(wp_unslash($_POST['province_id'])) : 0; // Input var okay.
        $city = $this->core->get_city($province_id);
        $r_city = array();

        if (is_array($city)) {
            foreach ($city as $key => $value) {
                $r_city[$key] = $value;
            }
        }

        wp_send_json($r_city);
        die;
    }

    /**
     * Get list district
     */
    public function get_list_district()
    {
        check_ajax_referer('get_list_district', 'kiriminaja_action');
        $city_id = isset($_POST['city_id']) ? sanitize_text_field(wp_unslash($_POST['city_id'])) : 0; // Input var okay.
        $district = $this->core->get_district($city_id);
        $r_district = array();

        if (is_array($district)) {
            foreach ($district as $key => $value) {
                $r_district[$key] = $value;
            }
        }

        wp_send_json($r_district);
        die;
    }

    public function search_district()
    {
        check_ajax_referer('get_list_district', 'kiriminaja_action');
        $search = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : ''; // Input var okay.
        $result = $this->core->search_district($search);
        $return = array();
        if (isset($result) && !empty($result)) {
            foreach ($result as $id => $res) {
                $return[] = array(
                    'id' => $id,
                    'text' => $res['full'],
                    'state' => $res['state'],
                    'city' => $res['city'],
                    'district' => $res['district'],
                );
            }
        }
        echo wp_json_encode($return);
        exit();
    }

    /**
     * Get list district
     */
    public function get_cost()
    {
        check_ajax_referer('get_cost', 'kiriminaja_action');
        $destination = isset($_POST['destination']) ? sanitize_text_field(wp_unslash($_POST['destination'])) : 0; // Input var okay.
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0; // Input var okay.
        $order = wc_get_order($order_id);
        $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : $this->helper->get_order_weight($order); // Input var okay.
        $origin = isset($_POST['origin']) ? intval($_POST['origin']) : 0; // Input var okay.
        $courier = isset($_POST['courier']) ? explode(':', $_POST['courier']) : array(); // Input var okay.

        $result = $this->core->get_cost($destination, $weight, $origin, $courier);

        $enable_insurance = $this->helper->is_enable_insurance($order);
        $enable_timber_packing = $this->helper->is_enable_timber_packing($order);

        $costs = array();
        if ($result) {
            foreach ($result as $cost) {
                $meta = array(
                    'created_by_kiriminaja' => true,
                    'courier' => $cost['courier'],
                    'etd' => $cost['time'],
                );

                // add timber packing.
                if (true === $enable_timber_packing) {
                    $meta['timber_packing'] = apply_filters('kiriminaja_timber_packing_fee', floatval($this->setting->get('timber_packing_multiplier')) * $cost['cost'], $cost['courier']);
                    $cost['cost'] += $meta['timber_packing'];
                }

                // add insurance fee.
                if (true === $enable_insurance) {
                    $meta['insurance'] = $this->helper->get_insurance($cost['courier'], $order->get_subtotal());
                    $cost['cost'] += $meta['insurance'];
                }

                // cost markup.
                $markups = $this->setting->get('markup');
                if (!empty($markups) && is_array($markups)) {
                    foreach ($markups as $markup) {
                        if ('' === $markup['courier'] || $cost['courier'] === $markup['courier']) {
                            if ('rajaongkir' === $this->setting->get('base_api') || !isset($markup['service']) || '' === $markup['service'] || ($markup['service'] === sanitize_title($cost['service']))) {
                                if (!isset($markup['amount']) || empty($markup['amount'])) {
                                    $markup['amount'] = 0;
                                }
                                $cost['cost'] += apply_filters('kiriminaja_custom_markup', floatval($markup['amount']), $cost);
                                if (0 > $cost['cost']) {
                                    $cost['cost'] = 0;
                                }
                            }
                        }
                    }
                }

                $meta['service'] = $this->helper->convert_service_name($cost['courier'], $cost['service']);
                $cost['cost'] = $this->helper->currency_convert($cost['cost']);
                $cost['courier_name'] = $this->helper->get_courier_name($cost['courier']);
                $cost['cost_display'] = wc_price($this->helper->currency_convert($cost['cost']));
                $cost['label'] = $cost['courier_name'] . ' - ' . $this->helper->convert_service_name($cost['courier'], $cost['service'], ('yes' === $this->setting->get('show_long_description') ? 'long' : 'short'));
                $cost['meta'] = $meta;
                $costs[] = $cost;
            }
        }
        echo wp_json_encode($costs);
        wp_die();
    }

    /**
     * Set token
     */
    public function set_token()
    {
        check_ajax_referer('set_token', 'kiriminaja_action');
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : ''; // Input var okay.
        $status = $this->core->get_perferences($token);
        $callback = $this->core->set_callback_url($token);
        wp_send_json($status);
        die;
    }

    /**
     * Delete token
     */
    public function delete_token()
    {
        check_ajax_referer('set_token', 'kiriminaja_action');
        $this->setting->set('token', '');
        wp_send_json(true);
        die;
    }

    /**
     * Load selected orders pickup request
     */
    public function load_pickup_orders()
    {
        check_ajax_referer('get_list_order', 'kiriminaja_action');
        $order_ids = isset($_POST['order_ids']) && is_array($_POST['order_ids']) ? array_map('intval', $_POST['order_ids']) : array();

        $orders = array();
        $html = '';
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            $status = $order->get_status();

            if ($this->helper->is_order_available_for_pickup($order)) {
                $orders[] = array(
                    'order_id' => $order_id,
                    'ref_id' => $this->helper->get_order_ref_id($order_id),
                    'status' => $status,
                    'shipping' => $order->get_shipping_method(),
                    'payment' => $order->get_payment_method()
                );
                ob_start();
                ?>
                <li>
                    <div class="order-list-header">
                        <div class="order-number">
                            <div class="order-id">#<?php echo esc_html($order_id); ?></div>
                            <div
                                class="order-ref-id"><?php echo esc_html($this->helper->get_order_ref_id($order_id)); ?></div>
                        </div>
                        <div class="order-shipping">
                            <?php echo esc_html($order->get_shipping_method()); ?>
                        </div>
                        <div class="order-payment">
                            <?php if ('cod' === $order->get_payment_method()) : ?>
                                <span class="cod">COD</span>
                            <?php else: ?>
                                <span class="non-cod">Non-COD</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <?php
                $html .= ob_get_clean();
            }
        }

        wp_send_json(array(
            'order_ids' => array_map(function ($order) {
                return $order['order_id'];
            }, $orders),
            'html' => $html
        ));
        die;
    }

    /**
     * Load pickup schedules
     */
    public function load_pickup_schedules()
    {
        check_ajax_referer('get_schedules', 'kiriminaja_action');

        $schedules = $this->core->get_pickup_schedules();
        $html = '';

        foreach ($schedules as $schedule) {
            ob_start();
            ?>
            <label>
                <input type="radio" name="pickup_schedule" value="<?php echo esc_attr($schedule['time']) ?>">
                <span><?php echo esc_html($schedule['label']) ?></span>
            </label>
            <?php
            $html .= ob_get_clean();
        }

        wp_send_json(array(
            'schedules' => $schedules,
            'html' => $html
        ));
        die;
    }

    /**
     * Send pickup request
     */
    public function send_pickup_request()
    {
        check_ajax_referer('pickup_request', 'kiriminaja_action');

        $order_ids = isset($_POST['order_ids']) ? array_map('intval', wp_unslash($_POST['order_ids'])) : array();
        $schedule = isset($_POST['schedule']) ? sanitize_text_field(wp_unslash($_POST['schedule'])) : '';

        if (!empty($order_ids) && !empty($schedule)) {
            $response = $this->core->send_pickup_request($order_ids, $schedule);
            wp_send_json($response);
            die;
        }

        wp_send_json(array(
            'status' => false,
            'message' => __('Orders is empty or schedule is not set', 'kiriminaja')
        ));
        die;
    }

    /**
     * Send pickup reschedule
     */
    public function send_pickup_reschedule()
    {
        check_ajax_referer('pickup_request', 'kiriminaja_action');

        $order_ids = isset($_POST['order_ids']) ? array_map('intval', wp_unslash($_POST['order_ids'])) : array();
        $schedule = isset($_POST['schedule']) ? sanitize_text_field(wp_unslash($_POST['schedule'])) : '';
        $pickup_id = isset($_POST['pickup_id']) ? intval($_POST['pickup_id']) : 0;

        if (!empty($order_ids) && !empty($schedule)) {
            $response = $this->core->send_pickup_request($order_ids, $schedule, $pickup_id);
            wp_send_json($response);
            die;
        }

        wp_send_json(array(
            'status' => false,
            'message' => __('Orders is empty or schedule is not set', 'kiriminaja')
        ));
        die;
    }

    /**
     * Check schedule
     */
    public function check_schedule()
    {
        check_ajax_referer('check_schedule', 'kiriminaja_action');
        $pickup_id = isset($_POST['pickup_id']) ? intval($_POST['pickup_id']) : '';
        $order_ids = get_post_meta($pickup_id, 'order_ids', true);
        $pickup_number = get_the_title($pickup_id);
        $schedule = get_post_meta($pickup_id, 'schedule', true);

        if (!empty($schedule)) {
            if (current_time('timestamp') > strtotime($schedule)) {
                wp_send_json(array(
                    'is_passed' => true,
                    'order_ids' => $order_ids,
                    'pickup_number' => $pickup_number
                ));
                die;
            }
        }
        wp_send_json(array(
            'is_passed' => false,
            'pickup_number' => $pickup_number
        ));
        die;
    }

    /**
     * Load payment
     */
    public function load_payment()
    {
        check_ajax_referer('load_payment', 'kiriminaja_action');
        $pickup_number = isset($_POST['pickup_number']) ? sanitize_text_field(wp_unslash($_POST['pickup_number'])) : '';
        $response = $this->core->get_payment($pickup_number);
        wp_send_json($response);
        die;
    }

    /**
     * Load pickup detail
     */
    public function load_pickup_detail()
    {
        check_ajax_referer('load_detail', 'kiriminaja_action');
        $pickup_id = isset($_POST['pickup_id']) ? intval($_POST['pickup_id']) : '';

        if ('' === ($status = get_post_meta($pickup_id, 'status', true))) {
            $status = 'pending';
        }
        $payment_status = get_post_meta($pickup_id, 'payment_status', true);
        if ('paid' === $payment_status && 'new' === $status) { // backward compatibilty.
            $status = 'paid';
        }

        $order_ids = get_post_meta($pickup_id, 'order_ids', true);
        $orders = '';
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            ob_start();
            ?>
            <li>
                <div class="order-list-header">
                    <div class="order-number">
                        <a class="order-id" href="<?php echo get_edit_post_link($order_id); ?>"
                           target="_blank">#<?php echo esc_html($order_id); ?></a>
                        <div
                            class="order-ref-id"><?php echo esc_html($this->helper->get_order_ref_id($order_id)); ?></div>
                    </div>
                    <div class="order-shipping">
                        <?php echo esc_html($order->get_shipping_method()); ?>
                    </div>
                    <div class="order-payment">
                        <?php if ('cod' === $order->get_payment_method()) : ?>
                            <span class="cod">COD</span>
                        <?php else: ?>
                            <span class="non-cod">Non-COD</span>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
            <?php
            $orders .= ob_get_clean();
        }
        if ($schedule = get_post_meta($pickup_id, 'schedule', true)) {
            $schedule = date('Y/m/d H:i', strtotime($schedule));
        }
        $response = array(
            'pickup_number' => get_the_title($pickup_id),
            'orders' => $orders,
            'schedule' => $schedule,
            'status' => $this->helper->get_pickup_status_label($pickup_id),
            'requested' => get_the_date('Y/m/d H:i', $pickup_id),
            'need_payment' => in_array($status, array('new', 'pending')),
            'need_reschedule' => current_time('timestamp') > strtotime($schedule),
            'is_picked' => 'picked' === $status
        );
        if ($response['need_payment']) {
            $response['payment'] = $this->core->get_payment($response['pickup_number']);
        }
        wp_send_json($response);
        die;
    }

    /**
     * Cancel pickup (deprecated)
     */
    public function cancel_pickup()
    {
        check_ajax_referer('cancel_pickup', 'kiriminaja_action');
        $pickup_id = isset($_POST['pickup_id']) ? intval($_POST['pickup_id']) : '';
        $response = $this->core->cancel_pickup_request($pickup_id);
        if ($response['status'] && !empty($response['order_ids'])) {
            foreach ($response['order_ids'] as $order_id) {
                $order = wc_get_order($order_id);
                $order->set_status('processing', sprintf(__('Cancelled pickup number: %s.', 'kiriminaja'), get_the_title($pickup_id)));
                $order->save();
            }
        }
        wp_send_json($response);
        die;
    }

    /**
     * Get order last shipping status text
     */
    public function get_shipping_status()
    {
        check_ajax_referer('get_shipping_status', 'kiriminaja_action');
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        $status = false;
        if (!empty($order_id)) {
            $status = $this->core->get_shipping_status_text($order_id);
        }
        wp_send_json($status);
        die;
    }

    /**
     * Cancel shipping. Order must have AWB
     */
    public function cancel_shipment()
    {
        check_ajax_referer('cancel_shipment', 'kiriminaja_action');
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '-';

        $response = $this->core->cancel_shipment($order_id, $reason);
        wp_send_json($response);
        die;
    }

    /**
     * Change country on profile page
     */
    public function change_profile_country()
    {
        check_ajax_referer('change_country', 'kiriminaja_action');
        $new_value = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : 'ID'; // Input var okay.
        $context = isset($_POST['context']) ? sanitize_text_field(wp_unslash($_POST['context'])) : 'billing'; // Input var okay.
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0; // Input var okay.

        update_user_meta($user_id, $context . '_country', $new_value);
        echo 'reload';
        die();
    }

}
