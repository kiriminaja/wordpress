<?php

/**
 * KiriminAja Setting
 */
class KiriminAja_Setting
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->option_name_setting = 'kiriminaja_setting';
        $this->setting = get_option($this->option_name_setting, $this->get_defaults());
    }

    /**
     * Get default values
     *
     * @param string $index Defaults index.
     * @return mixed         Default value.
     */
    public function get_defaults($index = null)
    {
        $defaults = apply_filters(
            'kiriminaja_setting_defaults', array(
                'enable' => true,
                'store_name' => get_bloginfo('name'),
                'store_address' => '',
                'store_province' => '',
                'store_city' => '',
                'store_district' => '',
                'store_zipcode' => '',
                'store_phone' => '',
                'couriers' => array('jne', 'jnt', 'sicepat', 'sap', 'jx', 'idx', 'ncs', 'anteraja'),
                'token' => '',
                'ref_prefix' => 'DEV-',
                'default_weight' => 1000,
            )
        );
        if (!is_null($index)) {
            if (isset($defaults[$index])) {
                return $defaults[$index];
            } else {
                return false;
            }
        }
        return $defaults;
    }

    /**
     * Save settings
     *
     * @param array $settings Setting values.
     */
    public function save($settings = array())
    {
        $new_value = wp_parse_args($settings, $this->get_defaults());
        update_option($this->option_name_setting, $new_value, true);
        $this->setting = get_option($this->option_name_setting, $this->get_defaults());
    }

    /**
     * Reset settings
     */
    public function reset()
    {
        $this->save($this->get_defaults());
    }

    /**
     * Get all settings into one array
     *
     * @return array Setting values
     */
    public function get_all()
    {
        return wp_parse_args($this->setting, $this->get_defaults());
    }

    /**
     * Get single setting
     *
     * @param string $index Setting index.
     * @return mixed         Setting value if setting exists.
     */
    public function get($index = '')
    {
        if (isset($this->setting[$index])) {
            $value = $this->setting[$index];
        } else {
            $value = $this->get_defaults($index);
        }
        return apply_filters('kiriminaja_setting_get_' . $index, $value);
    }

    /**
     * Set single setting
     *
     * @param string $index Setting index.
     * @param string $value Setting value.
     */
    public function set($index = '', $value = '')
    {
        if (in_array($index, array_keys($this->setting), true)) {
            $this->setting[$index] = isset($value) ? $value : $this->get_defaults($index);
            update_option($this->option_name_setting, $this->setting, true);
            $this->setting = get_option($this->option_name_setting, $this->get_defaults());
        }
    }

    /**
     * Get couriers
     *
     * @return array Couriers.
     */
    public function get_couriers()
    {
        return get_option('kiriminaja_couriers', array());
    }

    /**
     * Set couriers
     *
     * @param array $couriers Couriers.
     */
    public function set_couriers($couriers)
    {
        return update_option('kiriminaja_couriers', $couriers);
    }

}
