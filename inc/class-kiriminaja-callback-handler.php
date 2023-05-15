<?php

class KiriminAja_Callback_Handler
{

    /**
     * KiriminAja helper
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
        $this->helper = $kiriminaja_helper;
        add_action('woocommerce_api_kiriminaja_gateway', array($this, 'check_response'));
        add_action('kiriminaja_callback_request', array($this, 'handle_request'));
    }

    /**
     * Check if there is callback
     */
    public function check_response()
    {
        $posted = array();
        if (!isset($_POST['method']) || !isset($_POST['data'])) {
            $raw = file_get_contents('php://input');
            if (0 < strlen($raw) && $this->is_json($raw)) {
                $json = json_decode($raw, true);
                if (isset($json['method'], $json['data'])) {
                    $posted = $json;
                }
            }
        } else {
            $posted = array(
                'method' => sanitize_text_field(wp_unslash($_POST['method'])),
                'data' => wp_unslash($_POST['data'])
            );
        }

        do_action('kiriminaja_callback_request', $posted);
    }

    /**
     * Handle request
     *
     * @param array $posted Posted method and data.
     */
    public function handle_request($posted)
    {
        $posted['method'] = strtolower($posted['method']);
        if (method_exists($this, 'handle_' . $posted['method'])) {
            if (class_exists('WPMonolog')) {
                global $logger;
                $logger->addDebug('[WEBHOOK] ' . $posted['method'] . ' | ' . serialize($posted['data']));
            }
            call_user_func(array($this, 'handle_' . $posted['method']), $posted['data']);
        }
    }

    /**
     * Handle processed package
     *
     * @param array $data Array of items that contain order_id.
     */
    protected function handle_processed_packages($data)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                $order_id = $this->helper->get_order_by_ref_id($item['order_id']);
                if ($order = wc_get_order($order_id)) {
                    $pickup_id = get_post_meta($order_id, '_ka_pickup_id', true);
                    $pickup_number = get_post_meta($order_id, '_ka_pickup_number', true);
                    update_post_meta($pickup_id, 'status', 'paid');
                    if (isset($item['awb'])) {
                        $shipping = $this->helper->get_order_shipping($order);
                        $shipping->add_meta_data('awb', $item['awb'], true);
                        $shipping->save();
                    }
                    $order->set_status('pickup-request', sprintf(__('Pickup number: %s.', 'kiriminaja'), $pickup_number));
                    $order->add_order_note(__('Pickup being processed.', 'kiriminaja'));
                    $order->save();
                }
            }
        }
    }

    /**
     * Handle shipped package
     *
     * @param array $data Array of items that contain order_id.
     */
    protected function handle_shipped_packages($data)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                $order_id = $this->helper->get_order_by_ref_id($item['order_id']);
                if ($order = wc_get_order($order_id)) {
                    $pickup_id = get_post_meta($order_id, '_ka_pickup_id', true);
                    $pickup_number = get_post_meta($order_id, '_ka_pickup_number', true);
                    update_post_meta($pickup_id, 'status', 'picked');
                    $order->set_status('shipped', sprintf(__('Pickup number: %s.', 'kiriminaja'), $pickup_number));
                    $order->save();
                }
            }
        }
    }

    /**
     * Handle finished package
     *
     * @param array $data Array of items that contain order_id.
     */
    protected function handle_finished_packages($data)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                $order_id = $this->helper->get_order_by_ref_id($item['order_id']);
                if ($order = wc_get_order($order_id)) {
                    $pickup_id = get_post_meta($order_id, '_ka_pickup_id', true);
                    update_post_meta($pickup_id, 'status', 'finish');
                    $order->set_status('completed', __('Package received by customer.', 'kiriminaja'));
                    $order->save();
                }
            }
        }
    }

    /**
     * Handle return package
     *
     * @param array $data Array of items that contain order_id.
     */
    protected function handle_returned_packages($data)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                $order_id = $this->helper->get_order_by_ref_id($item['order_id']);
                if ($order = wc_get_order($order_id)) {
                    $pickup_id = get_post_meta($order_id, '_ka_pickup_id', true);
                    update_post_meta($pickup_id, 'status', 'finish');
                    $order->set_status('return', __('Customer returns the package.', 'kiriminaja'));
                    $order->save();
                }
            }
        }
    }

    /**
     * Handle rejected package
     *
     * @param array $data Array of items that contain order_id.
     */
    protected function handle_rejected_packages($data)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                $order_id = $this->helper->get_order_by_ref_id($item['order_id']);
                if ($order = wc_get_order($order_id)) {
                    $pickup_id = get_post_meta($order_id, '_ka_pickup_id', true);
                    update_post_meta($pickup_id, 'status', 'finish');
                    $order->set_status('processing', __('Pickup request was rejected.', 'kiriminaja'));
                    $order->save();
                }
            }
        }
    }

    /**
     * Handle return finished package
     *
     * @param array $data Array of items that contain order_id.
     */
    protected function handle_return_finished_packages($data)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                $order_id = $this->helper->get_order_by_ref_id($item['order_id']);
                if ($order = wc_get_order($order_id)) {
                    $pickup_id = get_post_meta($order_id, '_ka_pickup_id', true);
                    update_post_meta($pickup_id, 'status', 'finish');
                    $order->set_status('returned', __('Package returned by customer.', 'kiriminaja'));
                    $order->add_order_note(__('Package already returned.', 'kiriminaja'));
                    $order->save();
                }
            }
        }
    }

    /**
     * Handle corrected package
     *
     * @param array $data Array of items that contain order_id.
     */
    protected function handle_corrected_packages($data)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                $order_id = $this->helper->get_order_by_ref_id($item['order_id']);
                if ($order = wc_get_order($order_id) && $shipping = $this->helper->get_order_shipping($order_id)) {
                    $pickup_id = get_post_meta($order_id, '_ka_pickup_id', true);
                    $pickup_number = get_post_meta($order_id, '_ka_pickup_number', true);
                    update_post_meta($pickup_id, 'status', 'pending');
                    $order->add_order_note(sprintf(__('Pickup being processed with correction. Reason: %s.', 'kiriminaja'), $item['reason']));
                    $order->save();
                }
            }
        }
    }

    /**
     * Handle canceled package
     *
     * @param array $data Array of items that contain order_id.
     */
    protected function handle_canceled_packages($data)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                $order_id = $this->helper->get_order_by_ref_id($item['order_id']);
                if ($order = wc_get_order($order_id)) {
                    $order->set_status('on-hold', $item['cancel_note']);
                    $order->save();
                }
            }
        }
    }

    /**
     * Check if string is valid JSON.
     *
     * @param string $string String to check.
     * @return boolean         String is valid JSON.
     */
    private function is_json($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

}
